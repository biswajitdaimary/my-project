<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'trainer';
$_SESSION['full_name'] = 'Test';
require '/Applications/XAMPP/xamppfiles/htdocs/powerhousegym/trainer/availability.php';
