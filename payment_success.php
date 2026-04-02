<?php
session_start();
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - KenyaGuides</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #8b4513 0%, #d2691e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .success-icon {
            font-size: 5rem;
            color: #228B22;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #228B22;
            margin-bottom: 1rem;
        }
        
        p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .booking-id {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 1.2rem;
            color: #333;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #228B22;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 0.5rem;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #1a6b1a;
        }
        
        .btn-secondary {
            background: #8B4513;
        }
        
        .btn-secondary:hover {
            background: #6b3410;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">✅</div>
        <h1>Payment Initiated!</h1>
        <p>Your payment has been submitted successfully. The admin will verify your payment within 24 hours, and you'll receive a confirmation email once verified.</p>
        
        <?php if ($booking_id): ?>
            <div class="booking-id">Booking ID: #<?php echo $booking_id; ?></div>
        <?php endif; ?>
        
        <p>In the meantime, you can view your booking details or browse more guides.</p>
        
        <a href="tourist_dashboard.php" class="btn">View My Bookings</a>
        <a href="find_guide.php" class="btn btn-secondary">Browse More Guides</a>
    </div>
</body>
</html>