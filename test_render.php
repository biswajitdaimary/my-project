<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = ['user_id' => 1, 'role' => 'trainer', 'full_name' => 'Test Trainer'];
require_once 'config/config.php';
// bypass require_trainer by redefining it or just executing the file without auth_check
$content = file_get_contents('trainer/availability.php');
$content = preg_replace('/require_trainer\(\);/', '', $content);
$content = preg_replace('/require_once \'\.\.\/helpers\/auth_check\.php\';/', '', $content);
file_put_contents('trainer/availability_test.php', $content);
require_once 'trainer/availability_test.php';
