<?php
/**
 * PowerHouse Gym — Chatbot API Endpoint
 * POST /chatbot/api.php  { message: string }
 * Returns JSON: { response, action, quick_replies, intent }
 */

require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/site_settings_helper.php';
require_once __DIR__ . '/knowledge_base.php';

header('Content-Type: application/json; charset=utf-8');

// Rate limiting via session (max 30 messages per minute)
if (!isset($_SESSION['chatbot_count'])) {
    $_SESSION['chatbot_count'] = 0;
    $_SESSION['chatbot_reset'] = time() + 60;
}
if (time() > $_SESSION['chatbot_reset']) {
    $_SESSION['chatbot_count'] = 0;
    $_SESSION['chatbot_reset'] = time() + 60;
}
$_SESSION['chatbot_count']++;
if ($_SESSION['chatbot_count'] > 30) {
    echo json_encode(['error' => 'Too many messages. Please wait a moment.']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$raw     = file_get_contents('php://input');
$body    = json_decode($raw, true);
$message = trim($body['message'] ?? $_POST['message'] ?? '');

if (strlen($message) === 0) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}
if (strlen($message) > 300) {
    $message = substr($message, 0, 300);
}

// Log conversation in session (last 10)
if (!isset($_SESSION['chatbot_history'])) {
    $_SESSION['chatbot_history'] = [];
}
$_SESSION['chatbot_history'][] = ['role' => 'user', 'text' => $message, 'ts' => time()];
if (count($_SESSION['chatbot_history']) > 20) {
    array_shift($_SESSION['chatbot_history']);
}

// Load gym name dynamically from site settings
$gymName   = site_settings_get('gym_name', SITE_NAME);

// Match intent
$intents    = chatbot_get_intents($gymName);
$intentKey  = chatbot_match_intent($message, $intents);
$intent     = chatbot_enrich_response($intentKey, $intents[$intentKey], $pdo);

// Personalise greeting if logged in
$response = $intent['response'];
if ($intentKey === 'greeting' && !empty($_SESSION['full_name'])) {
    $name  = htmlspecialchars($_SESSION['full_name']);
    $first = explode(' ', $name)[0];
    $response = "👋 Welcome back, **{$first}**! Great to see you at {$gymName}. How can I help you today?";
}

// Handle quick_replies links
$qr = $intent['quick_replies'] ?? [];
$qrMap = [
    'Back to Home'     => SITE_URL . '/index.php',
    'View Plans'       => SITE_URL . '/plans.php',
    'View Membership Plans' => SITE_URL . '/plans.php',
    'Book a Trainer'   => SITE_URL . '/user/book-trainer.php',
    'Calculate My BMI' => SITE_URL . '/bmi-calculator.php',
    'Calculate BMI'    => SITE_URL . '/bmi-calculator.php',
    'See Class Schedule' => SITE_URL . '/schedule.php',
    'View Schedule'    => SITE_URL . '/schedule.php',
    'Class Schedule'   => SITE_URL . '/schedule.php',
    'My Bookings'      => SITE_URL . '/user/bookings.php',
    'My Dashboard'     => SITE_URL . '/user/dashboard.php',
    'My Membership'    => SITE_URL . '/user/membership.php',
    'Renew Membership' => SITE_URL . '/plans.php',
    'BMI History'      => SITE_URL . '/user/bmi-history.php',
    'My BMI History'   => SITE_URL . '/user/bmi-history.php',
    'View Trainers'    => SITE_URL . '/trainers.php',
    'Meet Trainers'    => SITE_URL . '/trainers.php',
    'Login'            => SITE_URL . '/auth/login.php',
    'Register'         => SITE_URL . '/auth/register.php',
    'Forgot Password?' => SITE_URL . '/auth/forgot-password.php',
    'Contact Us'       => SITE_URL . '/contact.php',
    'Contact Support'  => SITE_URL . '/contact.php',
    'About Us'         => SITE_URL . '/about.php',
    'View Gallery'     => SITE_URL . '/gallery.php',
    'Payment Failed?'  => SITE_URL . '/contact.php',
    'How do I pay?'    => SITE_URL . '/plans.php',
    'Payment History'  => SITE_URL . '/user/payments.php',
    'Terms of Service' => SITE_URL . '/terms.php',
];

$quickRepliesOut = array_map(fn($label) => [
    'label' => $label,
    'url'   => $qrMap[$label] ?? null,
], $qr);

$_SESSION['chatbot_history'][] = ['role' => 'bot', 'text' => $response, 'ts' => time()];

echo json_encode([
    'intent'        => $intentKey,
    'response'      => $response,
    'action'        => $intent['action'],
    'quick_replies' => $quickRepliesOut,
], JSON_UNESCAPED_UNICODE);
