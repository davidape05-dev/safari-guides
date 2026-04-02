<?php
session_start();
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

header('Content-Type: application/json');

// Check if user is logged in and is a guide
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$guide_id = $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit();
}

// Verify booking belongs to this guide and is paid
$check = $conn->prepare("
    SELECT id, tourist_id 
    FROM bookings 
    WHERE id = ? AND guide_id = ? AND status = 'confirmed' AND payment_status = 'paid'
");
$check->bind_param("ii", $booking_id, $guide_id);
$check->execute();
$booking = $check->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or cannot be completed']);
    exit();
}

// Update booking status to completed
$stmt = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tour marked as completed']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>