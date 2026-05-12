<?php

function site_settings_defaults(): array
{
    $gymName = defined('SITE_NAME') ? (string) SITE_NAME : 'FITNESS DESTINATION';

    return [
        'gym_name' => $gymName,
        'phone' => '+1 234 567 890',
        'email' => 'info@powerhousegym.com',
        'address' => "123 Fitness Street\nGym City",
        'facebook_url' => 'https://facebook.com',
        'instagram_url' => 'https://instagram.com',
        'twitter_url' => 'https://twitter.com',
        'linkedin_url' => 'https://linkedin.com',
        // Business Hours defaults
        'hours_weekday' => '6:00 AM - 10:00 PM',
        'hours_saturday' => '8:00 AM - 8:00 PM',
        'hours_sunday' => '8:00 AM - 2:00 PM',
        // About Us defaults
        'about_title'        => 'Our Story',
        'about_subtitle'     => 'More Than Just A Gym',
        'about_description'  => 'Founded in 2010, FITNESS DESTINATION started with a simple mission: to provide a welcoming, high-energy environment for people of all fitness levels.',
        'about_description2' => "Over the years, we've grown from a small neighborhood facility to a state-of-the-art fitness center spanning 10,000 square feet.",
        'about_mission'      => 'Empower individuals to achieve fitness goals.',
        'about_vision'       => 'Create a healthier, stronger community.',
        'about_image'        => '',
        // Team defaults
        'team_section_enabled' => '1',
        'team_ceo_name'      => 'John Doe',
        'team_ceo_title'     => 'Founder & CEO',
        'team_ceo_image'     => '',
        'team_manager_name'  => 'Jane Smith',
        'team_manager_title' => 'General Manager',
        'team_manager_image' => '',
        'team_head_trainer_name'  => 'Mike Johnson',
        'team_head_trainer_title' => 'Head Trainer',
        'team_head_trainer_image' => '',
    ];
}

function site_settings_ensure_schema(PDO $pdo): array
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                setting_group VARCHAR(50),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $columns = $pdo->query("SHOW COLUMNS FROM site_settings")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('setting_group', $columns, true)) {
            $pdo->exec("ALTER TABLE site_settings ADD COLUMN setting_group VARCHAR(50) NULL AFTER setting_value");
        }

        if (!in_array('updated_at', $columns, true)) {
            $pdo->exec("ALTER TABLE site_settings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }

        $columns = $pdo->query("SHOW COLUMNS FROM site_settings")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $columns = [];
    }

    return $columns;
}

function site_settings_social_fields(): array
{
    return [
        'facebook_url' => [
            'label' => 'Facebook URL',
            'icon' => 'fa-facebook-f',
            'placeholder' => 'https://facebook.com/your-page',
        ],
        'instagram_url' => [
            'label' => 'Instagram URL',
            'icon' => 'fa-instagram',
            'placeholder' => 'https://instagram.com/your-handle',
        ],
        'twitter_url' => [
            'label' => 'X / Twitter URL',
            'icon' => 'fa-twitter',
            'placeholder' => 'https://twitter.com/your-handle',
        ],
        'linkedin_url' => [
            'label' => 'LinkedIn URL',
            'icon' => 'fa-linkedin-in',
            'placeholder' => 'https://linkedin.com/in/your-profile',
        ],
    ];
}

function site_settings_optional_keys(): array
{
    return array_merge(
        array_keys(site_settings_social_fields()),
        [
            'hours_weekday',
            'hours_saturday',
            'hours_sunday',
            'about_description2',
            'about_mission',
            'about_vision',
            'about_image',
            'team_section_enabled',
            'team_ceo_name',
            'team_ceo_title',
            'team_ceo_image',
            'team_manager_name',
            'team_manager_title',
            'team_manager_image',
            'team_head_trainer_name',
            'team_head_trainer_title',
            'team_head_trainer_image',
        ]
    );
}

function site_settings_get_all(?PDO $pdo = null, bool $forceReload = false): array
{
    static $cache = null;

    if ($forceReload) {
        $cache = null;
    }

    if ($cache !== null) {
        return $cache;
    }

    if ($pdo === null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    }

    $settings = site_settings_defaults();

    if (!$pdo instanceof PDO) {
        return $settings;
    }

    try {
        site_settings_ensure_schema($pdo);
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
    } catch (PDOException $e) {
        // Fall back to defaults if settings cannot be loaded.
        return $settings;
    }

    $cache = $settings;
    return $cache;
}

function site_settings_get(string $key, string $fallback = ''): string
{
    $defaults = site_settings_defaults();
    $settings = site_settings_get_all();

    if (array_key_exists($key, $settings)) {
        $value = (string) $settings[$key];

        // Optional settings like social links may intentionally be blank.
        if ($value !== '' || in_array($key, site_settings_optional_keys(), true)) {
            return $value;
        }
    }

    if ($fallback !== '') {
        return $fallback;
    }

    return (string) ($defaults[$key] ?? '');
}

function site_settings_upsert(PDO $pdo, array $values, array $groups = []): void
{
    $groupMap = [
        'gym_name' => 'general',
        'phone'    => 'contact',
        'email'    => 'contact',
        'address'  => 'contact',
        'hours_weekday'  => 'contact',
        'hours_saturday' => 'contact',
        'hours_sunday'   => 'contact',
        'about_title'        => 'about',
        'about_subtitle'     => 'about',
        'about_description'  => 'about',
        'about_description2' => 'about',
        'about_mission'      => 'about',
        'about_vision'       => 'about',
        'about_image'        => 'about',
        'team_section_enabled' => 'about',
        'team_ceo_name'      => 'about',
        'team_ceo_title'     => 'about',
        'team_ceo_image'     => 'about',
        'team_manager_name'  => 'about',
        'team_manager_title' => 'about',
        'team_manager_image' => 'about',
        'team_head_trainer_name'  => 'about',
        'team_head_trainer_title' => 'about',
        'team_head_trainer_image' => 'about',
    ];

    foreach (array_keys(site_settings_social_fields()) as $key) {
        $groupMap[$key] = 'social';
    }

    $columns = site_settings_ensure_schema($pdo);
    $hasSettingGroup = in_array('setting_group', $columns, true);

    if ($hasSettingGroup) {
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value, setting_group)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)"
        );
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
    }

    foreach ($values as $key => $value) {
        $params = [$key, trim((string) $value)];
        if ($hasSettingGroup) {
            $params[] = $groups[$key] ?? $groupMap[$key] ?? 'general';
        }
        $stmt->execute($params);
    }

    site_settings_get_all($pdo, true);
}

function site_settings_validate_contact_payload(array $input): array
{
    $data = [
        'gym_name' => trim((string) ($input['gym_name'] ?? '')),
        'phone' => trim((string) ($input['phone'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'address' => preg_replace("/\r\n?/", "\n", trim((string) ($input['address'] ?? ''))),
        'hours_weekday' => trim((string) ($input['hours_weekday'] ?? '')),
        'hours_saturday' => trim((string) ($input['hours_saturday'] ?? '')),
        'hours_sunday' => trim((string) ($input['hours_sunday'] ?? '')),
    ];

    foreach (array_keys(site_settings_social_fields()) as $key) {
        $data[$key] = trim((string) ($input[$key] ?? ''));
    }

    $errors = [];

    if ($data['gym_name'] === '') {
        $errors['gym_name'] = 'Gym name is required.';
    } elseif (strlen($data['gym_name']) > 100) {
        $errors['gym_name'] = 'Gym name must be 100 characters or fewer.';
    }

    if ($data['phone'] === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s().]{7,25}$/', $data['phone'])) {
        $errors['phone'] = 'Enter a valid phone number using digits and + - ( ) characters.';
    }

    if ($data['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($data['address'] === '') {
        $errors['address'] = 'Office address is required.';
    } elseif (strlen($data['address']) > 500) {
        $errors['address'] = 'Office address must be 500 characters or fewer.';
    }

    if (strlen($data['hours_weekday']) > 100) {
        $errors['hours_weekday'] = 'Weekday hours must be 100 characters or fewer.';
    }
    if (strlen($data['hours_saturday']) > 100) {
        $errors['hours_saturday'] = 'Saturday hours must be 100 characters or fewer.';
    }
    if (strlen($data['hours_sunday']) > 100) {
        $errors['hours_sunday'] = 'Sunday hours must be 100 characters or fewer.';
    }

    foreach (site_settings_social_fields() as $key => $meta) {
        if ($data[$key] === '') {
            continue;
        }

        $isValidUrl = filter_var($data[$key], FILTER_VALIDATE_URL);
        $scheme = parse_url($data[$key], PHP_URL_SCHEME);

        if (!$isValidUrl || !in_array($scheme, ['http', 'https'], true)) {
            $errors[$key] = 'Enter a valid full URL starting with http:// or https://.';
        }
    }

    return [$data, $errors];
}

function site_settings_validate_about_payload(array $input): array
{
    $normalizeText = static function ($value): string {
        return preg_replace("/\r\n?/", "\n", trim((string) $value));
    };

    $data = [
        'about_title' => trim((string) ($input['about_title'] ?? '')),
        'about_subtitle' => trim((string) ($input['about_subtitle'] ?? '')),
        'about_description' => $normalizeText($input['about_description'] ?? ''),
        'about_description2' => $normalizeText($input['about_description2'] ?? ''),
        'about_mission' => $normalizeText($input['about_mission'] ?? ''),
        'about_vision' => $normalizeText($input['about_vision'] ?? ''),
        'team_section_enabled' => (!empty($input['team_section_enabled']) && $input['team_section_enabled'] == '1') ? '1' : '0',
        'team_ceo_name' => trim((string) ($input['team_ceo_name'] ?? '')),
        'team_ceo_title' => trim((string) ($input['team_ceo_title'] ?? '')),
        'team_manager_name' => trim((string) ($input['team_manager_name'] ?? '')),
        'team_manager_title' => trim((string) ($input['team_manager_title'] ?? '')),
        'team_head_trainer_name' => trim((string) ($input['team_head_trainer_name'] ?? '')),
        'team_head_trainer_title' => trim((string) ($input['team_head_trainer_title'] ?? '')),
    ];

    $errors = [];

    if ($data['about_title'] === '') {
        $errors['about_title'] = 'Section label is required.';
    } elseif (strlen($data['about_title']) > 60) {
        $errors['about_title'] = 'Section label must be 60 characters or fewer.';
    }

    if ($data['about_subtitle'] === '') {
        $errors['about_subtitle'] = 'Main heading is required.';
    } elseif (strlen($data['about_subtitle']) > 100) {
        $errors['about_subtitle'] = 'Main heading must be 100 characters or fewer.';
    }

    if ($data['about_description'] === '') {
        $errors['about_description'] = 'Main description is required.';
    } elseif (strlen($data['about_description']) > 3000) {
        $errors['about_description'] = 'Main description must be 3000 characters or fewer.';
    }

    if ($data['about_description2'] !== '' && strlen($data['about_description2']) > 3000) {
        $errors['about_description2'] = 'Second description must be 3000 characters or fewer.';
    }

    if ($data['about_mission'] !== '' && strlen($data['about_mission']) > 500) {
        $errors['about_mission'] = 'Mission statement must be 500 characters or fewer.';
    }

    if ($data['about_vision'] !== '' && strlen($data['about_vision']) > 500) {
        $errors['about_vision'] = 'Vision statement must be 500 characters or fewer.';
    }

    $teamFields = [
        'team_ceo_name' => 'CEO Name',
        'team_ceo_title' => 'CEO Title',
        'team_manager_name' => 'Manager Name',
        'team_manager_title' => 'Manager Title',
        'team_head_trainer_name' => 'Head Trainer Name',
        'team_head_trainer_title' => 'Head Trainer Title',
    ];
    foreach ($teamFields as $key => $label) {
        if ($data[$key] !== '' && strlen($data[$key]) > 100) {
            $errors[$key] = $label . ' must be 100 characters or fewer.';
        }
    }

    return [$data, $errors];
}

function site_settings_phone_href(string $phone): string
{
    $normalized = preg_replace('/[^0-9+]/', '', $phone);
    return $normalized !== '' ? 'tel:' . $normalized : '#';
}

function site_settings_whatsapp_href(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    return $digits !== '' ? 'https://wa.me/' . $digits : '#';
}
