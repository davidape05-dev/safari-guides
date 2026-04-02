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
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$booking_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Verify booking belongs to this guide
$check = $conn->prepare("SELECT id, tourist_id, tourist_name, tourist_email, total_price FROM bookings WHERE id = ? AND guide_id = ? AND status = 'pending'");
$check->bind_param("ii", $booking_id, $guide_id);
$check->execute();
$booking = $check->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or already processed']);
    exit();
}

switch ($action) {
    case 'accept':
        // Update booking status
        $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        
        if ($stmt->execute()) {
            // Create notification for tourist (if notifications table exists)
            $notify = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, link) 
                VALUES (?, 'booking', 'Booking Confirmed!', 
                        'Your booking has been accepted! Please complete payment to secure your tour.',
                        'payment.php?booking_id=?)
            ");
            $link = "payment.php?booking_id=" . $booking_id;
            $notify->bind_param("is", $booking['tourist_id'], $link);
            $notify->execute();
            
            echo json_encode(['success' => true, 'message' => 'Booking accepted! Tourist can now make payment.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;
        
    case 'decline':
        // Update booking status
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Booking declined']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>