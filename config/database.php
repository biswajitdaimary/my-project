<?php
// Database PDO Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Keep site settings available on older local databases so admin settings
    // and public contact details can be saved without manual SQL imports.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            setting_id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_group VARCHAR(50),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $siteSettingsColumns = $pdo->query("SHOW COLUMNS FROM site_settings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('setting_group', $siteSettingsColumns, true)) {
        $pdo->exec("ALTER TABLE site_settings ADD COLUMN setting_group VARCHAR(50) NULL AFTER setting_value");
    }
    if (!in_array('updated_at', $siteSettingsColumns, true)) {
        $pdo->exec("ALTER TABLE site_settings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    $pdo->exec("
        INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES
        ('gym_name', 'FITNESS DESTINATION', 'general'),
        ('phone', '+1 234 567 890', 'contact'),
        ('email', 'info@powerhousegym.com', 'contact'),
        ('address', '123 Fitness Street, Gym City', 'contact'),
        ('facebook_url', 'https://facebook.com', 'social'),
        ('instagram_url', 'https://instagram.com', 'social'),
        ('twitter_url', 'https://twitter.com', 'social'),
        ('linkedin_url', 'https://linkedin.com', 'social')
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auth_remember_tokens (
            token_id INT AUTO_INCREMENT PRIMARY KEY,
            account_type ENUM('user', 'trainer') NOT NULL,
            account_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_auth_remember_token_hash (token_hash),
            KEY idx_auth_remember_lookup (account_type, account_id, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Keep essential app tables available on older local databases, but only
    // after their referenced tables exist so a fresh empty DB doesn't fatal.
    $hasUsersTableStmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = (bool) $hasUsersTableStmt->fetchColumn();
    $hasPlansTableStmt = $pdo->query("SHOW TABLES LIKE 'membership_plans'");
    $hasPlansTable = (bool) $hasPlansTableStmt->fetchColumn();

    if ($hasUsersTable && $hasPlansTable) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payments (
                payment_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                plan_id INT DEFAULT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(10) DEFAULT 'INR',
                gateway ENUM('razorpay', 'stripe', 'cash', 'other') NOT NULL DEFAULT 'razorpay',
                gateway_order_id VARCHAR(100),
                gateway_payment_id VARCHAR(100),
                gateway_signature VARCHAR(255),
                status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
                payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                receipt_path VARCHAR(255),
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (plan_id) REFERENCES membership_plans(plan_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $paymentColumns = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('currency', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN currency VARCHAR(10) DEFAULT 'INR' AFTER amount");
        }
        if (!in_array('gateway', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN gateway ENUM('razorpay', 'stripe', 'cash', 'other') NOT NULL DEFAULT 'razorpay' AFTER currency");
        }
        if (!in_array('gateway_order_id', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_order_id VARCHAR(100) NULL AFTER gateway");
        }
        if (!in_array('gateway_payment_id', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_payment_id VARCHAR(100) NULL AFTER gateway_order_id");
        }
        if (!in_array('gateway_signature', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_signature VARCHAR(255) NULL AFTER gateway_payment_id");
        }
        if (!in_array('status', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending' AFTER gateway_signature");
        }
        if (!in_array('payment_date', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status");
        }
        if (!in_array('receipt_path', $paymentColumns, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN receipt_path VARCHAR(255) NULL AFTER payment_date");
        }
    }

    if ($hasUsersTable) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT,
                type ENUM('info','success','warning','danger') DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bmi_records (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $bmiColumns = [];
        $bmiColumnRows = $pdo->query("SHOW COLUMNS FROM bmi_records")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bmiColumnRows as $column) {
            $bmiColumns[$column['Field']] = $column;
        }

        $legacyBmiRenames = [
            'height' => ['new' => 'height_cm', 'definition' => 'DECIMAL(5,2) NOT NULL'],
            'weight' => ['new' => 'weight_kg', 'definition' => 'DECIMAL(5,2) NOT NULL'],
            'bmi' => ['new' => 'bmi_value', 'definition' => 'DECIMAL(4,2) NOT NULL'],
            'date' => ['new' => 'recorded_at', 'definition' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
        ];

        foreach ($legacyBmiRenames as $oldColumn => $renameConfig) {
            if (isset($bmiColumns[$oldColumn]) && !isset($bmiColumns[$renameConfig['new']])) {
                $pdo->exec(sprintf(
                    "ALTER TABLE bmi_records CHANGE COLUMN `%s` `%s` %s",
                    $oldColumn,
                    $renameConfig['new'],
                    $renameConfig['definition']
                ));
            }
        }

        $bmiColumns = [];
        $bmiColumnRows = $pdo->query("SHOW COLUMNS FROM bmi_records")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bmiColumnRows as $column) {
            $bmiColumns[$column['Field']] = $column;
        }

        if (!isset($bmiColumns['user_id'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN user_id INT DEFAULT NULL AFTER bmi_id");
        }
        if (!isset($bmiColumns['age'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN age INT DEFAULT NULL AFTER user_id");
        }
        if (!isset($bmiColumns['gender'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN gender ENUM('male', 'female', 'other') DEFAULT NULL AFTER age");
        }
        if (!isset($bmiColumns['weight_kg'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN weight_kg DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER gender");
        }
        if (!isset($bmiColumns['height_cm'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN height_cm DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER weight_kg");
        }
        if (!isset($bmiColumns['bmi_value'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN bmi_value DECIMAL(4,2) NOT NULL DEFAULT 0.00 AFTER height_cm");
        }
        if (!isset($bmiColumns['category'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN category ENUM('Underweight', 'Normal', 'Overweight', 'Obese') NOT NULL DEFAULT 'Normal' AFTER bmi_value");
        }
        if (!isset($bmiColumns['recorded_at'])) {
            $pdo->exec("ALTER TABLE bmi_records ADD COLUMN recorded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER category");
        }

        $bmiForeignKey = $pdo->prepare("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'bmi_records'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME = 'users'
            LIMIT 1
        ");
        $bmiForeignKey->execute();

        if (!$bmiForeignKey->fetchColumn()) {
            try {
                $pdo->exec("
                    ALTER TABLE bmi_records
                    ADD CONSTRAINT fk_bmi_records_user
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ");
            } catch (PDOException $e) {
                // Leave older local databases usable even if they cannot add the FK automatically.
            }
        }
    }

    // ── availability_slots table (date-based scheduler) ──────────────────────
    $hasTrainersTable = (bool)$pdo->query("SHOW TABLES LIKE 'trainers'")->fetchColumn();
    if ($hasTrainersTable) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS availability_slots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                trainer_id INT NOT NULL,
                date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                status ENUM('available', 'booked', 'blocked') DEFAULT 'available',
                FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Add slot_id to trainer_bookings if it doesn't exist
        $hasBookingsTable = (bool)$pdo->query("SHOW TABLES LIKE 'trainer_bookings'")->fetchColumn();
        if ($hasBookingsTable) {
            $bookingCols = $pdo->query("SHOW COLUMNS FROM trainer_bookings")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('slot_id', $bookingCols, true)) {
                $pdo->exec("ALTER TABLE trainer_bookings ADD COLUMN slot_id INT NULL AFTER trainer_id");
            }
        }
    }

    // ── Holidays table (holiday calendar system) ─────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS holidays (
            holiday_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            holiday_date DATE NOT NULL,
            description TEXT,
            type ENUM('full', 'partial') DEFAULT 'full',
            target_type ENUM('all', 'specific') DEFAULT 'all',
            trainer_ids JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_holidays_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Trainer Client Notes table ──────────────────────────────────
    if ($hasUsersTable && $hasTrainersTable) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS trainer_client_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                trainer_id INT NOT NULL,
                user_id INT NOT NULL,
                note_text TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY trainer_user_unique (trainer_id, user_id),
                FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // ── Client Workout Plans schema updates ───────────────────────────────────
    $hasWorkoutPlansTableStmt = $pdo->query("SHOW TABLES LIKE 'client_workout_plans'");
    if ((bool)$hasWorkoutPlansTableStmt->fetchColumn()) {
        $planCols = $pdo->query("SHOW COLUMNS FROM client_workout_plans")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('file_path', $planCols, true)) {
            $pdo->exec("ALTER TABLE client_workout_plans ADD COLUMN file_path VARCHAR(255) NULL AFTER plan_content");
        }
        if (!in_array('file_type', $planCols, true)) {
            $pdo->exec("ALTER TABLE client_workout_plans ADD COLUMN file_type VARCHAR(50) NULL AFTER file_path");
        }
        if (!in_array('original_filename', $planCols, true)) {
            $pdo->exec("ALTER TABLE client_workout_plans ADD COLUMN original_filename VARCHAR(255) NULL AFTER file_type");
        }
        if (!in_array('video_link', $planCols, true)) {
            $pdo->exec("ALTER TABLE client_workout_plans ADD COLUMN video_link VARCHAR(255) NULL AFTER original_filename");
        }
    }

    // ── Automatically Expire Memberships ──────────────────────────────────────────
    $hasMembershipsTable = (bool)$pdo->query("SHOW TABLES LIKE 'user_memberships'")->fetchColumn();
    if ($hasMembershipsTable) {
        $pdo->exec("UPDATE user_memberships SET status = 'expired' WHERE end_date < CURDATE() AND status = 'active'");
    }

    // ── Calendar System Tables ────────────────────────────────────────────────────
    // gym_events: Gym-wide events — classes, announcements, programs, specials
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gym_events (
            event_id        INT AUTO_INCREMENT PRIMARY KEY,
            title           VARCHAR(200) NOT NULL,
            description     TEXT,
            event_date      DATE NOT NULL,
            start_time      TIME DEFAULT NULL,
            end_time        TIME DEFAULT NULL,
            all_day         TINYINT(1) DEFAULT 1,
            category        ENUM('announcement','class','program','special','reminder') DEFAULT 'announcement',
            color           VARCHAR(20) DEFAULT '#0ea5e9',
            visibility      ENUM('all','members','trainers','admin') DEFAULT 'all',
            trainer_id      INT DEFAULT NULL,
            max_capacity    INT DEFAULT NULL,
            is_recurring    TINYINT(1) DEFAULT 0,
            recurrence_rule VARCHAR(100) DEFAULT NULL,
            created_by      INT NOT NULL DEFAULT 0,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_gym_events_date (event_date),
            KEY idx_gym_events_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // event_registrations: Client sign-ups for classes/programs
    if ($hasUsersTable) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_registrations (
                reg_id          INT AUTO_INCREMENT PRIMARY KEY,
                event_id        INT NOT NULL,
                user_id         INT NOT NULL,
                registered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status          ENUM('registered','cancelled','attended') DEFAULT 'registered',
                UNIQUE KEY uq_event_user (event_id, user_id),
                FOREIGN KEY (event_id) REFERENCES gym_events(event_id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // calendar_reminders: Personal reminders for all roles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS calendar_reminders (
            reminder_id     INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT DEFAULT NULL,
            trainer_id      INT DEFAULT NULL,
            title           VARCHAR(200) NOT NULL,
            reminder_date   DATETIME NOT NULL,
            is_done         TINYINT(1) DEFAULT 0,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reminders_date (reminder_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Extend holidays table with color column (safe ALTER)
    try {
        $holCols = $pdo->query("SHOW COLUMNS FROM holidays")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('color', $holCols, true)) {
            $pdo->exec("ALTER TABLE holidays ADD COLUMN color VARCHAR(20) DEFAULT '#ef4444' AFTER type");
        }
    } catch (PDOException $e) { /* holidays table may not exist on very old installs */ }

    // ── Unique ID System ──────────────────────────────────────────────────────
    // id_counters: atomic counter table — one row per prefix (CLT / TRN / ADM)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS id_counters (
            prefix   VARCHAR(10) PRIMARY KEY,
            next_val INT UNSIGNED NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add custom_id to users table (clients + admins)
    if ($hasUsersTable) {
        $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('custom_id', $userCols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN custom_id VARCHAR(12) NULL UNIQUE AFTER user_id");
        }

        // Backfill: assign CLT-XXXX to existing clients and ADM-XXXX to existing admins
        // that do not yet have a custom_id, in order of their user_id
        $untaggedUsers = $pdo->query("SELECT user_id, role FROM users WHERE custom_id IS NULL ORDER BY user_id ASC")->fetchAll();
        foreach ($untaggedUsers as $u) {
            $prefix = ($u['role'] === 'admin') ? 'ADM' : 'CLT';
            // Get & increment counter atomically
            $pdo->exec("INSERT INTO id_counters (prefix, next_val) VALUES ('{$prefix}', 1) ON DUPLICATE KEY UPDATE next_val = next_val");
            $pdo->exec("UPDATE id_counters SET next_val = LAST_INSERT_ID(next_val) + 1 WHERE prefix = '{$prefix}'");
            $seq = (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            if ($seq < 1) {
                // fallback: read the current counter directly
                $seq = (int)$pdo->query("SELECT next_val - 1 FROM id_counters WHERE prefix = '{$prefix}'")->fetchColumn();
            }
            $cid = $prefix . '-' . str_pad($seq ?: 1, 4, '0', STR_PAD_LEFT);
            try {
                $pdo->prepare("UPDATE users SET custom_id = ? WHERE user_id = ? AND custom_id IS NULL")->execute([$cid, $u['user_id']]);
            } catch (PDOException $e) { /* skip on rare duplicate — won't happen in practice */ }
        }
    }

    // Add custom_id to trainers table
    $hasTrainersTableForId = (bool)$pdo->query("SHOW TABLES LIKE 'trainers'")->fetchColumn();
    if ($hasTrainersTableForId) {
        $trainerCols = $pdo->query("SHOW COLUMNS FROM trainers")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('custom_id', $trainerCols, true)) {
            $pdo->exec("ALTER TABLE trainers ADD COLUMN custom_id VARCHAR(12) NULL UNIQUE AFTER trainer_id");
        }

        // Backfill: assign TRN-XXXX to existing trainers without a custom_id
        $untaggedTrainers = $pdo->query("SELECT trainer_id FROM trainers WHERE custom_id IS NULL ORDER BY trainer_id ASC")->fetchAll();
        foreach ($untaggedTrainers as $t) {
            $prefix = 'TRN';
            $pdo->exec("INSERT INTO id_counters (prefix, next_val) VALUES ('{$prefix}', 1) ON DUPLICATE KEY UPDATE next_val = next_val");
            $pdo->exec("UPDATE id_counters SET next_val = LAST_INSERT_ID(next_val) + 1 WHERE prefix = '{$prefix}'");
            $seq = (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
            if ($seq < 1) {
                $seq = (int)$pdo->query("SELECT next_val - 1 FROM id_counters WHERE prefix = '{$prefix}'")->fetchColumn();
            }
            $cid = $prefix . '-' . str_pad($seq ?: 1, 4, '0', STR_PAD_LEFT);
            try {
                $pdo->prepare("UPDATE trainers SET custom_id = ? WHERE trainer_id = ? AND custom_id IS NULL")->execute([$cid, $t['trainer_id']]);
            } catch (PDOException $e) { /* skip on rare duplicate */ }
        }
    }
    // ── End Unique ID System ──────────────────────────────────────────────────

} catch (\PDOException $e) {
    // In production, log error instead of displaying
    die("Database Connection Failed: " . $e->getMessage());
}

