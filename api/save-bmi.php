<?php
ini_set('display_errors', '0');
ob_start();

require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/bmi_helper.php';
require_once '../helpers/notification_helper.php';

function bmi_json_response(int $statusCode, array $payload): void
{
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bmi_json_response(405, ['status' => 'error', 'message' => 'Invalid request method.']);
}

if (empty($_SESSION['user_id'])) {
    bmi_json_response(401, ['status' => 'error', 'message' => 'Please log in to save BMI records.']);
}

// CSRF check
if (empty($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
    bmi_json_response(419, ['status' => 'error', 'message' => 'Your session expired. Refresh the page and try again.']);
}

$user_id = $_SESSION['user_id'];
$age = isset($_POST['age']) ? (int) $_POST['age'] : null;
$gender = isset($_POST['gender']) ? strtolower(trim((string) $_POST['gender'])) : null;
$height = isset($_POST['height']) ? trim((string) $_POST['height']) : null;
$weight = isset($_POST['weight']) ? trim((string) $_POST['weight']) : null;

// Validate inputs server-side using the helper
$validationError = bmi_validate_measurements($height, $weight);
if ($validationError) {
    bmi_json_response(422, ['status' => 'error', 'message' => $validationError]);
}
 
$demographicError = bmi_validate_demographics($age, $gender);
if ($demographicError) {
    bmi_json_response(422, ['status' => 'error', 'message' => $demographicError]);
}

// Calculate BMI and get metadata server-side
$bmi      = bmi_calculate_value((float) $height, (float) $weight);
$meta     = bmi_get_meta($bmi);
$category = $meta['category'];

try {
    // Ensure the table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS bmi_records (
        bmi_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        age INT DEFAULT NULL,
        gender ENUM('male', 'female', 'other') DEFAULT NULL,
        weight_kg DECIMAL(5,2) NOT NULL,
        height_cm DECIMAL(5,2) NOT NULL,
        bmi_value DECIMAL(4,2) NOT NULL,
        category ENUM('Underweight', 'Normal', 'Overweight', 'Obese') NOT NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    $stmt = $pdo->prepare("
        INSERT INTO bmi_records (user_id, age, gender, weight_kg, height_cm, bmi_value, category)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $age, $gender, $weight, $height, $bmi, $category]);

    if ($stmt->rowCount() !== 1) {
        throw new PDOException('BMI insert did not complete.');
    }

    create_notification(
        $pdo,
        $user_id,
        'New BMI record saved',
        'Your latest BMI reading is ' . number_format($bmi, 1) . ' (' . $category . ').',
        'success'
    );

    // Build record object for JS to prepend to history table
    $record = [
        'age'            => $age,
        'age_label'      => bmi_format_age($age),
        'gender_label'   => bmi_format_gender($gender),
        'bmi_value'      => number_format($bmi, 1),
        'height_cm'      => number_format((float)$height, 1),
        'weight_kg'      => number_format((float)$weight, 1),
        'category'       => $category,
        'badge_class'    => $meta['badge_class'],
        'recorded_label' => date('M d, Y h:i A'),
    ];

    bmi_json_response(200, [
        'status'  => 'success',
        'message' => 'BMI saved to your dashboard!',
        'record'  => $record,
        'redirect_url' => SITE_URL . '/user/dashboard.php?bmi_saved=1',
    ]);
} catch (PDOException $e) {
    error_log('BMI save failed: ' . $e->getMessage());
    bmi_json_response(500, [
        'status' => 'error',
        'message' => 'Could not save BMI right now. Please try again in a moment.',
    ]);
}
?>
