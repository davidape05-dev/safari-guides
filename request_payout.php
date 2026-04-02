<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: guide_dashboard.php");
    exit();
}

$guide_id = $_SESSION['user_id'];
$amount = floatval($_POST['amount']);
$payment_method = $_POST['payment_method'];
$phone = $_POST['phone'] ?? '';
$bank_details = $_POST['bank_details'] ?? '';

// Validate amount
if ($amount < 100) {
    $_SESSION['error'] = "Minimum payout amount is KES 100";
    header("Location: guide_dashboard.php#earnings");
    exit();
}

// Check if guide has enough earnings
$earnings_query = $conn->prepare("SELECT COALESCE(SUM(total_price * 0.9), 0) as available FROM bookings WHERE guide_id = ? AND status = 'completed' AND payment_status = 'paid'");
$earnings_query->bind_param("i", $guide_id);
$earnings_query->execute();
$available = $earnings_query->get_result()->fetch_assoc()['available'];

if ($amount > $available) {
    $_SESSION['error'] = "Insufficient funds. Available: KES " . number_format($available);
    header("Location: guide_dashboard.php#earnings");
    exit();
}

// Insert payout request
$insert = $conn->prepare("
    INSERT INTO payout_requests (guide_id, amount, payment_method, phone_number, bank_details, status) 
    VALUES (?, ?, ?, ?, ?, 'pending')
");
$insert->bind_param("idsss", $guide_id, $amount, $payment_method, $phone, $bank_details);

if ($insert->execute()) {
    $_SESSION['success'] = "Payout request submitted successfully! Admin will process it within 24-48 hours.";
} else {
    $_SESSION['error'] = "Failed to submit payout request. Please try again.";
}

header("Location: guide_dashboard.php#earnings");
exit();
?>