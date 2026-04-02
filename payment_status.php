<?php
session_start();
require_once 'db.php';

$checkout_id = isset($_GET['checkout_id']) ? $_GET['checkout_id'] : '';

if (!$checkout_id) {
    header("Location: my_bookings.php");
    exit();
}

// Check payment status
$stmt = $conn->prepare("
    SELECT p.status, p.mpesa_receipt, b.id as booking_id, b.total_price
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE p.transaction_code = ?
");
$stmt->bind_param("s", $checkout_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - SafariGuide</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .status-card {
            max-width: 500px;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .pending-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
        .failed-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.8rem 1.5rem;
            background: #228B22;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="status-card">
        <?php if ($payment && $payment['status'] == 'completed'): ?>
            <div class="success-icon">✅</div>
            <h2>Payment Successful!</h2>
            <p>Your payment of KES <?php echo number_format($payment['total_price']); ?> has been received.</p>
            <p>M-Pesa Receipt: <?php echo $payment['mpesa_receipt']; ?></p>
            <p>Booking ID: #<?php echo $payment['booking_id']; ?></p>
            <a href="my_bookings.php" class="btn">View My Bookings</a>
        <?php elseif ($payment && $payment['status'] == 'pending'): ?>
            <div class="pending-icon">⏳</div>
            <h2>Payment Pending</h2>
            <p>Please check your phone to complete the payment.</p>
            <p>You'll receive a confirmation once payment is complete.</p>
            <meta http-equiv="refresh" content="5">
        <?php else: ?>
            <div class="failed-icon">❌</div>
            <h2>Payment Failed</h2>
            <p>There was an issue processing your payment.</p>
            <p>Please try again or contact support.</p>
            <a href="mpesa_payment.php?booking_id=<?php echo $payment['booking_id']; ?>&amount=<?php echo $payment['total_price']; ?>" class="btn">Try Again</a>
        <?php endif; ?>
    </div>
</body>
</html>