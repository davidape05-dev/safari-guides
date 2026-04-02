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

// Check if user is logged in and is a tourist
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'tourist') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch tourist user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get all bookings for this tourist
$bookings_query = "
    SELECT 
        b.*,
        CONCAT(u.first_name, ' ', u.last_name) as guide_name,
        u.email as guide_email,
        u.phone as guide_phone,
        gp.profile_photo,
        gp.location as guide_location,
        (SELECT COUNT(*) FROM reviews WHERE booking_id = b.id) as has_review
    FROM bookings b
    INNER JOIN users u ON b.guide_id = u.id
    LEFT JOIN guide_profiles gp ON u.id = gp.user_id
    WHERE b.tourist_id = ?
    ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'confirmed' AND b.payment_status = 'pending' THEN 2
            WHEN b.status = 'confirmed' AND b.payment_status = 'paid' THEN 3
            WHEN b.status = 'completed' THEN 4
            ELSE 5
        END,
        b.created_at DESC
";

$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();

// Get statistics
$stats = [];

// Total bookings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE tourist_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->get_result()->fetch_assoc()['count'];

// Pending acceptance
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE tourist_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['pending_acceptance'] = $stmt->get_result()->fetch_assoc()['count'];

// Awaiting payment
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE tourist_id = ? AND status = 'confirmed' AND payment_status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['awaiting_payment'] = $stmt->get_result()->fetch_assoc()['count'];

// Upcoming tours
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE tourist_id = ? AND status = 'confirmed' AND payment_status = 'paid' AND end_date >= CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['upcoming_tours'] = $stmt->get_result()->fetch_assoc()['count'];

// Completed tours
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE tourist_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['completed_tours'] = $stmt->get_result()->fetch_assoc()['count'];

// Handle any session messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - KenyaGuides</title>
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: #2c2c2c;
            text-decoration: none;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: #228B22;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #228B22;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .logout-btn {
            padding: 0.5rem 1rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }

        .home-btn {
            padding: 0.5rem 1rem;
            background: #8B4513;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #228B22;
        }

        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }

        /* Bookings Section */
        .section-title {
            font-size: 1.5rem;
            color: #8B4513;
            margin: 2rem 0 1rem;
        }

        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid transparent;
        }

        .booking-card.pending {
            border-left-color: #ffc107;
        }

        .booking-card.confirmed {
            border-left-color: #17a2b8;
        }

        .booking-card.completed {
            border-left-color: #28a745;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .guide-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .guide-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #228B22;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
        }

        .detail-value {
            font-weight: 600;
            color: #2c2c2c;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #228B22;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #2c2c2c;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            border: 1px solid #e0e0e0;
        }

        .filter-tab.active {
            background: #228B22;
            color: white;
            border-color: #228B22;
        }
    </style>
</head>

<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="logo">Kenya<span>Guides</span></div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="find_guide.php">Find Guides</a>
            <a href="tourist_dashboard.php">My Dashboard</a>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['first_name'] ?? 'T', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1)); ?>
            </div>
            <span><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></span>
            <a href="index.php" class="home-btn">🏠 Home</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? 'Tourist'); ?>! 👋</h1>
            <p>Manage your bookings, make payments, and review your safari experiences.</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_acceptance']; ?></div>
                <div class="stat-label">Awaiting Response</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['awaiting_payment']; ?></div>
                <div class="stat-label">Awaiting Payment</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['upcoming_tours']; ?></div>
                <div class="stat-label">Upcoming Tours</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_tours']; ?></div>
                <div class="stat-label">Completed Tours</div>
            </div>
        </div>

        <!-- My Bookings -->
        <h2 class="section-title">My Bookings</h2>

        <?php if ($bookings && $bookings->num_rows > 0): ?>
            <?php while ($booking = $bookings->fetch_assoc()):
                $status_class = '';
                $status_text = '';

                if ($booking['status'] == 'pending') {
                    $status_class = 'status-pending';
                    $status_text = 'Awaiting Guide Response';
                } elseif ($booking['status'] == 'confirmed' && $booking['payment_status'] == 'pending') {
                    $status_class = 'status-confirmed';
                    $status_text = 'Confirmed - Payment Required';
                } elseif ($booking['status'] == 'confirmed' && $booking['payment_status'] == 'paid') {
                    $status_class = 'status-completed';
                    $status_text = 'Paid & Confirmed';
                } elseif ($booking['status'] == 'completed') {
                    $status_class = 'status-completed';
                    $status_text = 'Tour Completed';
                } elseif ($booking['status'] == 'cancelled') {
                    $status_class = 'status-pending';
                    $status_text = 'Cancelled';
                }
                ?>
                <div class="booking-card <?php echo $booking['status']; ?>">
                    <div class="booking-header">
                        <div class="guide-info">
                            <div class="guide-avatar">
                                <?php
                                $initial = !empty($booking['guide_name']) ? substr($booking['guide_name'], 0, 1) : 'G';
                                echo $initial;
                                ?>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($booking['guide_name']); ?></h3>
                                <p style="color: #666; font-size: 0.9rem;">📍
                                    <?php echo htmlspecialchars($booking['guide_location'] ?? 'Kenya'); ?></p>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </div>

                    <div class="booking-details">
                        <div class="detail-item">
                            <span class="detail-label">Booking ID</span>
                            <span class="detail-value">#<?php echo $booking['id']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tour Dates</span>
                            <span class="detail-value">
                                <?php echo date('M j, Y', strtotime($booking['start_date'])); ?> -
                                <?php echo date('M j, Y', strtotime($booking['end_date'])); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value"><?php echo $booking['duration_days']; ?> days</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">People</span>
                            <span class="detail-value"><?php echo $booking['num_people']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Price</span>
                            <span class="detail-value" style="color: #228B22; font-weight: 700;">
                                KES <?php echo number_format($booking['total_price']); ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($booking['special_requests'])): ?>
                        <div style="background: #f9f9f9; padding: 0.8rem; border-radius: 5px; margin: 0.5rem 0;">
                            <strong>Special Requests:</strong> <?php echo htmlspecialchars($booking['special_requests']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <!-- Payment Button - Show when confirmed and not paid -->
                        <?php if ($booking['status'] == 'confirmed' && $booking['payment_status'] == 'pending'): ?>
                            <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                                💳 Pay Now (KES <?php echo number_format($booking['total_price']); ?>)
                            </a>
                        <?php endif; ?>

                        <!-- View Details Button -->
                        <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                            View Details
                        </a>

                        <!-- Review Button - Show when completed and no review yet -->
                        <!-- Review Button - Show when completed and no review yet -->
                        <?php
                        // Check if review exists for this booking
                        $check_review = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
                        $check_review->bind_param("i", $booking['id']);
                        $check_review->execute();
                        $has_review = $check_review->get_result()->num_rows > 0;
                        ?>

                        <?php if ($booking['status'] == 'completed' && !$has_review): ?>
                            <a href="submit_review.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                                ⭐ Write a Review
                            </a>
                        <?php endif; ?>

                        <!-- Contact Guide Button -->
                        <a href="mailto:<?php echo $booking['guide_email']; ?>?subject=Question about booking #<?php echo $booking['id']; ?>"
                            class="btn btn-secondary">
                            📧 Contact Guide
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📅</div>
                <h3>No Bookings Yet</h3>
                <p>You haven't made any bookings. Start by finding a guide for your safari adventure!</p>
                <a href="find_guide.php" class="btn btn-primary" style="margin-top: 1rem;">Find a Guide</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Simple filter functionality (optional)
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Add filter logic here if needed
            });
        });
    </script>
</body>

</html>