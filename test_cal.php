<?php
session_start();
$_SESSION['role'] = 'user';
$_SESSION['user_id'] = 2;
$_GET['action'] = 'events';
$_GET['start'] = '2026-05-01';
$_GET['end'] = '2026-05-31';

require 'api/calendar.php';
