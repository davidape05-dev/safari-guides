<?php
session_start();
require_once 'mpesa_config.php';
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'tourist') {
    header("Location: login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

if (!$booking_id || !$amount) {
    header("Location: my_bookings.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'];
    
    if (empty($phone)) {
        $error = 'Please enter your M-Pesa phone number';
    } else {
        $mpesa = new MpesaAPI();
        $result = $mpesa->stkPush($phone, $amount, 'Booking#' . $booking_id, 'Payment for safari booking');
        
        if ($result['success']) {
            // Store checkout_request_id in database
            $stmt = $conn->prepare("
                UPDATE payments 
                SET transaction_code = ?, 
                    payment_method = 'mpesa',
                    status = 'pending'
                WHERE booking_id = ?
            ");
            $stmt->bind_param("si", $result['checkout_request_id'], $booking_id);
            $stmt->execute();
            
            $_SESSION['success'] = 'Payment request sent to your phone! Please complete the payment.';
            header("Location: payment_status.php?checkout_id=" . $result['checkout_request_id']);
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Payment - SafariGuide</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .payment-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #8B4513, #228B22);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .content {
            padding: 2rem;
        }

        .amount-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .amount-box h3 {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: #228B22;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c2c2c;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #228B22;
        }

        .btn-pay {
            width: 100%;
            padding: 1rem;
            background: #228B22;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-pay:hover {
            background: #1a6b1a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 139, 34, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .mpesa-instructions {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .mpesa-instructions h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }

        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #228B22;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="header">
            <h1>💰 M-Pesa Payment</h1>
            <p>Pay for your safari booking</p>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="amount-box">
                <h3>Booking #<?php echo $booking_id; ?></h3>
                <div class="amount">KES <?php echo number_format($amount); ?></div>
            </div>

            <div class="mpesa-instructions">
                <h4>📱 How to pay with M-Pesa:</h4>
                <p>1. Enter your M-Pesa phone number below</p>
                <p>2. Click "Pay Now"</p>
                <p>3. You'll receive a prompt on your phone</p>
                <p>4. Enter your PIN to complete payment</p>
                <p>5. Payment will be confirmed instantly</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>M-Pesa Phone Number</label>
                    <input type="tel" name="phone" placeholder="07xxxxxxxx" required>
                </div>

                <button type="submit" class="btn-pay">
                    <i class="fas fa-mobile-alt"></i> Pay KES <?php echo number_format($amount); ?>
                </button>
            </form>

            <a href="my_bookings.php" class="back-link">← Back to My Bookings</a>
        </div>
    </div>
</body>
</html>