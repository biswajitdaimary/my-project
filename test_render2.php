<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = ['user_id' => 1, 'role' => 'trainer', 'full_name' => 'Test Trainer'];
chdir('trainer'); // Change directory to trainer!
require_once '../config/config.php';
$content = file_get_contents('availability.php');
$content = preg_replace('/require_trainer\(\);/', '', $content);
$content = preg_replace('/require_once \'\.\.\/helpers\/auth_check\.php\';/', '', $content);
file_put_contents('availability_test.php', $content);
require_once 'availability_test.php';
