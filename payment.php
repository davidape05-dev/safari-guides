<?php
session_start();
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id = $_SESSION['user_id'];

// Fetch booking details - only if it belongs to this tourist and is confirmed
$query = "
    SELECT 
        b.*,
        CONCAT(u.first_name, ' ', u.last_name) as guide_name,
        u.email as guide_email,
        u.phone as guide_phone,
        gp.price_per_day,
        gp.profile_photo
    FROM bookings b
    INNER JOIN users u ON b.guide_id = u.id
    INNER JOIN guide_profiles gp ON u.id = gp.user_id
    WHERE b.id = ? AND b.tourist_id = ? AND b.status = 'confirmed'
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    // Check if already paid
    $check_paid = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND tourist_id = ? AND payment_status = 'paid'");
    $check_paid->bind_param("ii", $booking_id, $user_id);
    $check_paid->execute();
    if ($check_paid->get_result()->num_rows > 0) {
        header("Location: payment_success.php?booking_id=" . $booking_id);
        exit();
    }
    
    $_SESSION['error'] = "Invalid booking or payment not yet available";
    header("Location: my_bookings.php");
    exit();
}

// Calculate fees
$platform_fee = $booking['total_price'] * 0.10;
$guide_earnings = $booking['total_price'] - $platform_fee;

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    $phone = $_POST['phone'] ?? '';
    $transaction_code = $_POST['transaction_code'] ?? '';
    
    // Validate based on payment method
    $error = '';
    if ($payment_method === 'mpesa' && empty($phone)) {
        $error = "Phone number is required for M-Pesa";
    } elseif ($payment_method === 'mpesa' && empty($transaction_code)) {
        $error = "Transaction code is required";
    }
    
    if (empty($error)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert payment record
            $insert_payment = $conn->prepare("
                INSERT INTO payments (
                    booking_id, tourist_id, guide_id, amount, platform_fee, 
                    guide_earnings, payment_method, transaction_code, phone_number, 
                    status, payment_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $insert_payment->bind_param(
                "iiidddsss",
                $booking_id,
                $user_id,
                $booking['guide_id'],
                $booking['total_price'],
                $platform_fee,
                $guide_earnings,
                $payment_method,
                $transaction_code,
                $phone
            );
            $insert_payment->execute();
            $payment_id = $conn->insert_id;
            
            // Update booking with payment info
            $update_booking = $conn->prepare("
                UPDATE bookings 
                SET payment_status = 'pending',
                    payment_id = ?,
                    platform_fee = ?,
                    guide_payout = ?
                WHERE id = ?
            ");
            $update_booking->bind_param("iddi", $payment_id, $platform_fee, $guide_earnings, $booking_id);
            $update_booking->execute();
            
            // Create notification for admin
            // Create notification for admin
$admin_notify = $conn->prepare("
    INSERT INTO notifications (user_id, type, title, message, link) 
    SELECT id, 'payment', 'New Payment Pending', ?, 'admin_payments.php'
    FROM users WHERE role = 'admin'
");

// Create the message separately
$admin_message = "Payment of KES " . number_format($booking['total_price']) . " from " . $booking['tourist_name'] . " needs verification";

// Bind the message parameter
$admin_notify->bind_param("s", $admin_message);
$admin_notify->execute();
            
            // Create notification for guide
           // Create notification for guide
$guide_notify = $conn->prepare("
    INSERT INTO notifications (user_id, type, title, message, link) 
    VALUES (?, 'payment', 'Payment Initiated', ?, 'guide_dashboard.php?section=earnings')
");

$guide_message = "Tourist has initiated payment for booking #" . $booking_id;
$guide_notify->bind_param("is", $booking['guide_id'], $guide_message);
$guide_notify->execute();
            
            $conn->commit();
            
            $_SESSION['success'] = "Payment submitted successfully! Admin will verify within 24 hours.";
            header("Location: payment_success.php?booking_id=" . $booking_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Payment failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - KenyaGuides</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #8b4513 0%, #d2691e 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background:  #228B22;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .booking-summary {
            padding: 2rem;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .guide-info {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .guide-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #228B22;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .guide-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
            color: #2c2c2c;
        }

        .total-amount {
            font-size: 1.5rem;
            color: #228B22;
            font-weight: 700;
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px dashed #228B22;
        }

        .fee-breakdown {
            background: #e8f5e9;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
        }

        .payment-methods {
            padding: 2rem;
        }

        .method-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .method-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .method-tab.active {
            border-color: #228B22;
            background: #e8f5e9;
        }

        .method-tab img {
            width: 40px;
            height: 40px;
            margin-bottom: 0.5rem;
        }

        .payment-form {
            display: none;
        }

        .payment-form.active {
            display: block;
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

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
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
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-pay:hover {
            background: #1a6b1a;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 2rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .mpesa-instructions {
            background: #f0f8ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .mpesa-instructions p {
            margin: 0.5rem 0;
        }

        .mpesa-instructions strong {
            color: #228B22;
        }

        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: white;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="payment-card">
            <div class="header">
                <h1>Complete Your Payment</h1>
                <p>Secure payment for your safari adventure</p>
            </div>

            <div class="booking-summary">
                <div class="guide-info">
                    <div class="guide-avatar">
                        <?php if (!empty($booking['profile_photo'])): ?>
                            <img src="uploads/profiles/<?php echo $booking['profile_photo']; ?>" alt="Guide">
                        <?php else: ?>
                            <?php echo strtoupper(substr($booking['guide_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($booking['guide_name']); ?></h3>
                        <p>Your Tour Guide</p>
                    </div>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Booking ID:</span>
                    <span class="detail-value">#<?php echo $booking['id']; ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Tour Dates:</span>
                    <span class="detail-value">
                        <?php echo date('M j, Y', strtotime($booking['start_date'])); ?> - 
                        <?php echo date('M j, Y', strtotime($booking['end_date'])); ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value"><?php echo $booking['duration_days']; ?> days</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Number of People:</span>
                    <span class="detail-value"><?php echo $booking['num_people']; ?></span>
                </div>

                <div class="fee-breakdown">
                    <h4 style="margin-bottom: 1rem;">Payment Breakdown</h4>
                    
                    <div class="detail-row">
                        <span class="detail-label">Tour Price:</span>
                        <span class="detail-value">KES <?php echo number_format($booking['total_price']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Platform Fee (10%):</span>
                        <span class="detail-value">KES <?php echo number_format($platform_fee); ?></span>
                    </div>

                    <div class="detail-row" style="border-top: 2px dashed #228B22; margin-top: 0.5rem; padding-top: 1rem;">
                        <span class="detail-label" style="font-weight: 700;">Total to Pay:</span>
                        <span class="detail-value" style="font-size: 1.3rem; color: #228B22;">
                            KES <?php echo number_format($booking['total_price']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" id="paymentForm" class="payment-methods">
                <h3 style="margin-bottom: 1rem;">Select Payment Method</h3>

                <div class="method-tabs">
                    <div class="method-tab active" onclick="selectMethod('mpesa')">
                        <div>📱 M-Pesa</div>
                        <small>Pay via mobile money</small>
                    </div>
                    <div class="method-tab" onclick="selectMethod('card')">
                        <div>💳 Card</div>
                        <small>Credit/Debit card</small>
                    </div>
                </div>

                <!-- M-Pesa Form -->
                <div id="mpesa-form" class="payment-form active">
                    <div class="mpesa-instructions">
                        <h4>📱 M-Pesa Payment Instructions:</h4>
                        <p>1. Go to your M-Pesa menu</p>
                        <p>2. Select <strong>Lipa Na M-Pesa</strong></p>
                        <p>3. Select <strong>Pay Bill</strong></p>
                        <p>4. Enter Business No: <strong>123456</strong></p>
                        <p>5. Enter Account No: <strong><?php echo $booking['id']; ?></strong></p>
                        <p>6. Enter Amount: <strong>KES <?php echo number_format($booking['total_price']); ?></strong></p>
                        <p>7. Enter your M-Pesa PIN and confirm</p>
                        <p>8. You'll receive an SMS with transaction code</p>
                    </div>

                    <div class="form-group">
                        <label>M-Pesa Phone Number</label>
                        <input type="text" name="phone" placeholder="e.g., 0712345678" required>
                    </div>

                    <div class="form-group">
                        <label>M-Pesa Transaction Code</label>
                        <input type="text" name="transaction_code" placeholder="e.g., OI12R34T5" required>
                        <small style="color: #666;">Enter the code from your M-Pesa confirmation SMS</small>
                    </div>
                </div>

                <!-- Card Form -->
                <div id="card-form" class="payment-form">
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" placeholder="1234 5678 9012 3456">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="text" placeholder="MM/YY">
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" placeholder="123">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Cardholder Name</label>
                        <input type="text" placeholder="Name on card">
                    </div>

                    <p style="color: #666; font-size: 0.9rem; margin: 1rem 0;">
                        🔒 Your card details are secure and encrypted
                    </p>
                </div>

                <input type="hidden" name="payment_method" id="payment_method" value="mpesa">

                <button type="submit" class="btn-pay" id="submitBtn">
                    Pay KES <?php echo number_format($booking['total_price']); ?>
                </button>
            </form>
        </div>

        <a href="my_bookings.php" class="back-link">← Back to My Bookings</a>
    </div>

    <script>
        function selectMethod(method) {
            // Update tabs
            document.querySelectorAll('.method-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Update forms
            document.querySelectorAll('.payment-form').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(method + '-form').classList.add('active');
            
            // Update hidden input
            document.getElementById('payment_method').value = method;
            
            // Update button text
            const totalAmount = '<?php echo number_format($booking['total_price']); ?>';
            document.getElementById('submitBtn').innerHTML = 
                method === 'mpesa' ? 'Pay with M-Pesa' : 'Pay with Card';
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const method = document.getElementById('payment_method').value;
            
            if (method === 'mpesa') {
                const phone = document.querySelector('input[name="phone"]').value;
                const code = document.querySelector('input[name="transaction_code"]').value;
                
                if (!phone || !code) {
                    e.preventDefault();
                    alert('Please fill in both phone number and transaction code');
                }
            }
        });
    </script>
</body>
</html>