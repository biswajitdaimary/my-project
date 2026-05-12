<?php
/**
 * id_helper.php — Unique Custom ID Generator
 *
 * Generates permanent, sequential, prefixed IDs for each account type.
 *   Clients  → CLT-0001, CLT-0002, …
 *   Trainers → TRN-0001, TRN-0002, …
 *   Admins   → ADM-0001, ADM-0002, …
 *
 * Uses a dedicated `id_counters` table with FOR UPDATE locking to prevent
 * race conditions and guarantee uniqueness under concurrent registrations.
 */

/**
 * Generate the next unique custom ID for a given prefix.
 *
 * @param PDO    $pdo     Active database connection
 * @param string $prefix  'CLT', 'TRN', or 'ADM'
 * @return string         e.g. 'CLT-0042'
 */
function generate_custom_id(PDO $pdo, string $prefix): string
{
    $pdo->beginTransaction();
    try {
        // Lock the counter row for this prefix so concurrent requests wait
        $stmt = $pdo->prepare(
            "SELECT next_val FROM id_counters WHERE prefix = ? FOR UPDATE"
        );
        $stmt->execute([$prefix]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $nextVal = (int) $row['next_val'];
            $pdo->prepare("UPDATE id_counters SET next_val = next_val + 1 WHERE prefix = ?")
                ->execute([$prefix]);
        } else {
            // First-ever ID for this prefix
            $nextVal = 1;
            $pdo->prepare("INSERT INTO id_counters (prefix, next_val) VALUES (?, 2)")
                ->execute([$prefix]);
        }

        $pdo->commit();
        return $prefix . '-' . str_pad($nextVal, 4, '0', STR_PAD_LEFT);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Assign a custom_id to an existing account that does not yet have one.
 * Safe to call multiple times — skips if custom_id already exists.
 *
 * @param PDO    $pdo      Active database connection
 * @param string $table    'users' or 'trainers'
 * @param string $pkCol    Primary key column name, e.g. 'user_id'
 * @param int    $pkVal    Primary key value
 * @param string $prefix   'CLT', 'TRN', or 'ADM'
 */
function assign_custom_id_if_missing(PDO $pdo, string $table, string $pkCol, int $pkVal, string $prefix): void
{
    $check = $pdo->prepare("SELECT custom_id FROM `{$table}` WHERE `{$pkCol}` = ?");
    $check->execute([$pkVal]);
    $existing = $check->fetchColumn();

    if (empty($existing)) {
        $newId = generate_custom_id($pdo, $prefix);
        $pdo->prepare("UPDATE `{$table}` SET custom_id = ? WHERE `{$pkCol}` = ?")
            ->execute([$newId, $pkVal]);
    }
}
