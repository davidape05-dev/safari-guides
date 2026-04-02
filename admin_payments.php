<?php
session_start();
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch admin user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_payment') {
        $payment_id = intval($_POST['payment_id']);
        $status = $_POST['status']; // 'completed' or 'failed'
        
        // Update payment status
        $update = $conn->prepare("UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
        $update->bind_param("sii", $status, $user_id, $payment_id);
        
        if ($update->execute()) {
            if ($status === 'completed') {
                // Get payment details to update booking
                $payment_info = $conn->query("SELECT booking_id, guide_id FROM payments WHERE id = $payment_id")->fetch_assoc();
                
                // Update booking status
                $conn->query("UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = {$payment_info['booking_id']}");
                
                // Create notification for guide
                $notify = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'payment', 'Payment Verified', 'Payment has been verified for your booking')");
                $notify->bind_param("i", $payment_info['guide_id']);
                $notify->execute();
            }
            $success = "Payment status updated successfully";
        }
    } elseif ($_POST['action'] === 'process_payout') {
        $payout_id = intval($_POST['payout_id']);
        $status = $_POST['status']; // 'completed' or 'failed'
        
        $update = $conn->prepare("UPDATE payout_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $update->bind_param("sii", $status, $user_id, $payout_id);
        $update->execute();
        
        if ($status === 'completed') {
            $success = "Payout processed successfully";
        } else {
            $success = "Payout request rejected";
        }
    }
}

// Get statistics
$stats = [];

// Pending payments count
$result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
$stats['pending_payments'] = $result->fetch_assoc()['count'];

// Pending payouts count
$result = $conn->query("SELECT COUNT(*) as count FROM payout_requests WHERE status = 'pending'");
$stats['pending_payouts'] = $result->fetch_assoc()['count'];

// Total payments today
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed'");
$stats['today_payments'] = $result->fetch_assoc()['total'];

// Total platform fees
$result = $conn->query("SELECT COALESCE(SUM(platform_fee), 0) as total FROM payments WHERE status = 'completed'");
$stats['total_fees'] = $result->fetch_assoc()['total'];

// Get pending payments
$pending_payments = $conn->query("
    SELECT 
        p.*,
        b.tourist_name,
        CONCAT(t.first_name, ' ', t.last_name) as tourist_full_name,
        CONCAT(g.first_name, ' ', g.last_name) as guide_name,
        b.start_date,
        b.end_date
    FROM payments p
    INNER JOIN bookings b ON p.booking_id = b.id
    INNER JOIN users t ON p.tourist_id = t.id
    INNER JOIN users g ON p.guide_id = g.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");

// Get payout requests
$payouts = $conn->query("
    SELECT 
        pr.*,
        CONCAT(u.first_name, ' ', u.last_name) as guide_name,
        u.email,
        u.phone
    FROM payout_requests pr
    INNER JOIN users u ON pr.guide_id = u.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at DESC
");

// Get recent completed payments
$recent_payments = $conn->query("
    SELECT 
        p.*,
        b.tourist_name,
        CONCAT(g.first_name, ' ', g.last_name) as guide_name
    FROM payments p
    INNER JOIN bookings b ON p.booking_id = b.id
    INNER JOIN users g ON p.guide_id = g.id
    WHERE p.status = 'completed'
    ORDER BY p.payment_date DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8B4513;
        }

        .logo span {
            color: #228B22;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-badge {
            background: #8B4513;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .logout-btn {
            padding: 0.5rem 1rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        /* Container */
        .container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .sidebar {
            background: white;
            padding: 2rem 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            padding: 1rem 2rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .sidebar-menu li:hover {
            background: #f5f5f5;
            border-left: 4px solid #228B22;
        }

        .sidebar-menu li.active {
            background: #e8f5e9;
            border-left: 4px solid #228B22;
            color: #228B22;
            font-weight: 600;
        }

        .sidebar-menu a {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #2c2c2c;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #228B22;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .card h2 {
            color: #2c2c2c;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f5f5f5;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #2c2c2c;
        }

        table td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.85rem;
            margin-right: 0.3rem;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-primary {
            background: #228B22;
            color: white;
        }

        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.5rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            border-bottom-color: #228B22;
            color: #228B22;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="top-nav">
        <div class="logo">Kenya<span>Guides</span> Admin</div>
        <div class="user-info">
            <span class="admin-badge">👤 Admin</span>
            <span><strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="admin_dashboard.php" class="btn btn-primary" style="background: #228B22;">Dashboard</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
                <li><a href="admin_dashboard.php#pending">⏳ Pending Verification</a></li>
                <li><a href="admin_dashboard.php#guides">👥 All Guides</a></li>
                <li class="active"><a href="admin_payments.php">💰 Payments</a></li>
                <li><a href="admin_dashboard.php#bookings">📅 Bookings</a></li>
                <li><a href="admin_dashboard.php#users">🙋 Users</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Payment Management</h1>
                <p>Verify payments and process guide payouts</p>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <div class="stat-number"><?php echo $stats['pending_payments']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Payouts</h3>
                    <div class="stat-number"><?php echo $stats['pending_payouts']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Today's Payments</h3>
                    <div class="stat-number">KES <?php echo number_format($stats['today_payments']); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Platform Fees</h3>
                    <div class="stat-number">KES <?php echo number_format($stats['total_fees']); ?></div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('pending-payments')">Pending Payments (<?php echo $stats['pending_payments']; ?>)</div>
                <div class="tab" onclick="showTab('payouts')">Payout Requests (<?php echo $stats['pending_payouts']; ?>)</div>
                <div class="tab" onclick="showTab('history')">Payment History</div>
            </div>

            <!-- Pending Payments Tab -->
            <div id="pending-payments" class="tab-content active">
                <div class="card">
                    <h2>Pending Payment Verification</h2>
                    <?php if ($pending_payments && $pending_payments->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Tourist</th>
                                    <th>Guide</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Transaction Code</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $pending_payments->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $payment['booking_id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['tourist_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['guide_name']); ?></td>
                                        <td><strong>KES <?php echo number_format($payment['amount']); ?></strong></td>
                                        <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                        <td><?php echo $payment['transaction_code'] ?: 'N/A'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="verify_payment">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                <button type="submit" name="status" value="completed" class="btn btn-small btn-success">✓ Verify</button>
                                                <button type="submit" name="status" value="failed" class="btn btn-small btn-danger">✗ Fail</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: #666;">No pending payments</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payout Requests Tab -->
            <div id="payouts" class="tab-content">
                <div class="card">
                    <h2>Guide Payout Requests</h2>
                    <?php if ($payouts && $payouts->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Guide</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Phone</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payout = $payouts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payout['guide_name']); ?></td>
                                        <td><strong>KES <?php echo number_format($payout['amount']); ?></strong></td>
                                        <td><?php echo strtoupper($payout['payment_method']); ?></td>
                                        <td><?php echo $payout['phone_number']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($payout['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="process_payout">
                                                <input type="hidden" name="payout_id" value="<?php echo $payout['id']; ?>">
                                                <button type="submit" name="status" value="completed" class="btn btn-small btn-success">Process</button>
                                                <button type="submit" name="status" value="failed" class="btn btn-small btn-danger">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: #666;">No pending payout requests</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment History Tab -->
            <div id="history" class="tab-content">
                <div class="card">
                    <h2>Recent Payments</h2>
                    <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Booking</th>
                                    <th>Tourist</th>
                                    <th>Guide</th>
                                    <th>Amount</th>
                                    <th>Platform Fee</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>#<?php echo $payment['booking_id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['tourist_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['guide_name']); ?></td>
                                        <td>KES <?php echo number_format($payment['amount']); ?></td>
                                        <td>KES <?php echo number_format($payment['platform_fee']); ?></td>
                                        <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                        <td><span class="status status-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: #666;">No payment history</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>