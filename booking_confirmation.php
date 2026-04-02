<?php
session_start();
$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed</title>
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
        .confirmation-card {
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
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #228B22;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .booking-id {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-size: 1.2rem;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="confirmation-card">
        <div class="success-icon">✅</div>
        <h1>Booking Request Sent!</h1>
        <p>Your booking request has been submitted successfully. The guide will review your request and respond within 24 hours.</p>
        <?php if ($bookingId): ?>
            <div class="booking-id">Booking ID: #<?php echo $bookingId; ?></div>
        <?php endif; ?>
        <p>You will receive a confirmation email once the guide accepts your request.</p>
        <a href="find_guide.php" class="btn">Browse More Guides</a>
    </div>
</body>
</html>