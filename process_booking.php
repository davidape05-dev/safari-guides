<?php
session_start();
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: find_guide.php");
    exit();
}

try {
    $guideId = $_POST['guide_id'] ?? 0;
    $touristName = $_POST['tourist_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $numPeople = $_POST['num_people'] ?? 1;
    $specialRequests = $_POST['special_requests'] ?? '';
    
    // Validate required fields
    if (!$guideId || !$touristName || !$email || !$startDate || !$endDate) {
        $_SESSION['error'] = 'All required fields must be filled';
        header("Location: book_guide.php?id=" . $guideId);
        exit();
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
        header("Location: book_guide.php?id=" . $guideId);
        exit();
    }
    
    // Calculate duration
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    $duration = $interval->days; // Don't add +1 since duration_days is calculated
    
    // Validate dates
    if ($end <= $start) {
        $_SESSION['error'] = 'End date must be after start date';
        header("Location: book_guide.php?id=" . $guideId);
        exit();
    }
    
    // Get guide price
    $stmt = $conn->prepare("SELECT price_per_day FROM guide_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $guideId);
    $stmt->execute();
    $result = $stmt->get_result();
    $guide = $result->fetch_assoc();
    
    if (!$guide) {
        $_SESSION['error'] = 'Guide not found';
        header("Location: find_guide.php");
        exit();
    }
    
    $pricePerDay = $guide['price_per_day'];
    $totalPrice = $pricePerDay * $duration * $numPeople;
    
    // Check if tourist exists or create a new user
    $touristId = null;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingUser = $result->fetch_assoc();
    
    if ($existingUser) {
        $touristId = $existingUser['id'];
    } else {
        // Create a new tourist account
        $tempPassword = password_hash(uniqid(), PASSWORD_DEFAULT);
        $firstName = explode(' ', $touristName)[0];
        $lastName = count(explode(' ', $touristName)) > 1 ? explode(' ', $touristName)[1] : '';
        
        $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'tourist', 'active')");
        $stmt->bind_param("sssss", $email, $tempPassword, $firstName, $lastName, $phone);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create user account");
        }
        $touristId = $conn->insert_id;
    }
    
    // Insert booking
    $stmt = $conn->prepare("
        INSERT INTO bookings (
            tourist_id, guide_id, tourist_name, tourist_email, tourist_phone,
            num_people, start_date, end_date, total_price, special_requests, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->bind_param(
        "iisssissds",
        $touristId, $guideId, $touristName, $email, $phone,
        $numPeople, $startDate, $endDate, $totalPrice, $specialRequests
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create booking: " . $stmt->error);
    }
    
    $bookingId = $conn->insert_id;
    
    // Create notification for guide
   // Create notification for guide
$notifyGuide = $conn->prepare("
    INSERT INTO notifications (user_id, type, title, message, link) 
    VALUES (?, 'booking', 'New Booking Request', ?, 'guide_dashboard.php')
");

// Create the full message
$message = "New booking request from " . $touristName . " for KES " . number_format($totalPrice);

// Bind parameters - 'is' means integer and string
$notifyGuide->bind_param("is", $guideId, $message);

if (!$notifyGuide->execute()) {
    // Log error but don't stop the booking process
    error_log("Failed to create notification: " . $notifyGuide->error);
}
    $_SESSION['success'] = 'Booking request sent successfully! The guide will respond within 24 hours.';
    header("Location: booking_confirmation.php?id=" . $bookingId);
    exit();
    
} catch(Exception $e) {
    error_log("Booking error: " . $e->getMessage());
    $_SESSION['error'] = 'Booking failed. Please try again.';
    header("Location: book_guide.php?id=" . $guideId);
    exit();
}

$conn->close();
?>