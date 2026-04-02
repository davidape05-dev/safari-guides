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

// Check if user is logged in and is a guide
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch guide user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Get guide profile data
$guide_profile_query = "SELECT * FROM guide_profiles WHERE user_id = ?";
$stmt = $conn->prepare($guide_profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$guide_profile = $stmt->get_result()->fetch_assoc();

// Get statistics
$stats = [];

// Total bookings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guide_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_bookings'] = $stmt->get_result()->fetch_assoc()['count'];

// Pending bookings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guide_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['pending_bookings'] = $stmt->get_result()->fetch_assoc()['count'];

// Completed tours
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guide_id = ? AND status = 'completed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['completed_tours'] = $stmt->get_result()->fetch_assoc()['count'];

// Total earnings this month
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_price), 0) as total 
    FROM bookings 
    WHERE guide_id = ? 
    AND status = 'completed' 
    AND MONTH(end_date) = MONTH(CURRENT_DATE())
    AND YEAR(end_date) = YEAR(CURRENT_DATE())
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['monthly_earnings'] = $stmt->get_result()->fetch_assoc()['total'];

// Total earnings all time
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_price), 0) as total 
    FROM bookings 
    WHERE guide_id = ? AND status = 'completed'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_earnings'] = $stmt->get_result()->fetch_assoc()['total'];

// Average rating
$stats['rating'] = $guide_profile['rating'] ?? 0;
$stats['total_reviews'] = $guide_profile['total_reviews'] ?? 0;

// Get upcoming bookings
// Get upcoming bookings - FIXED VERSION
$upcoming_query = "
    SELECT 
        b.id,
        b.tourist_name,
        b.tourist_email,
        b.tourist_phone,
        b.start_date,
        b.end_date,
        b.num_people,
        b.total_price,
        b.status,
        b.payment_status,
        b.special_requests
    FROM bookings b
    WHERE b.guide_id = ? 
    AND b.status IN ('pending', 'confirmed')
    ORDER BY 
        CASE 
            WHEN b.end_date < CURDATE() THEN 0  -- Past tours first (need completion)
            ELSE 1                                -- Future tours later
        END,
        b.start_date ASC
    LIMIT 10
";

$stmt = $conn->prepare($upcoming_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_bookings = $stmt->get_result();

// Get all bookings
$all_bookings_query = "
    SELECT 
        b.id,
        b.tourist_name,
        b.tourist_email,
        b.tourist_phone,
        b.start_date,
        b.end_date,
        b.num_people,
        b.duration_days,
        b.total_price,
        b.status,
        b.special_requests,
        b.created_at
    FROM bookings b
    WHERE b.guide_id = ?
    ORDER BY b.created_at DESC
";
$stmt = $conn->prepare($all_bookings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_bookings = $stmt->get_result();

// Get recent reviews
$reviews_query = "
    SELECT 
        r.rating,
        r.comment,
        r.created_at,
        COALESCE(u.first_name, b.tourist_name, 'Guest') as reviewer_name
    FROM reviews r
    INNER JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN users u ON r.tourist_id = u.id
    WHERE b.guide_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_reviews = $stmt->get_result();

// Get payment history
$payments_query = "
    SELECT 
        b.id,
        b.tourist_name,
        b.end_date,
        b.total_price,
        b.status
    FROM bookings b
    WHERE b.guide_id = ?
    AND b.status IN ('completed', 'confirmed')
    ORDER BY b.end_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide Dashboard - SafariGuide</title>
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
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card h2 {
            color: #2c2c2c;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }

        /* Table */
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

        table tr:hover {
            background: #f9f9f9;
        }

        /* Status Badges */
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

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #228B22;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.85rem;
            margin-right: 0.3rem;
        }

        /* Profile Section */
        .profile-header {
            display: flex;
            gap: 2rem;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #228B22;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
        }

        .profile-info h2 {
            margin-bottom: 0.5rem;
        }

        .profile-info p {
            color: #666;
        }

        .verified-badge {
            display: inline-block;
            background: #228B22;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }

        /* Reviews */
        .review-item {
            border-bottom: 1px solid #e0e0e0;
            padding: 1rem 0;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .reviewer-name {
            font-weight: 600;
        }

        .review-date {
            color: #999;
            font-size: 0.85rem;
        }

        .stars {
            color: #DAA520;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>

<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="logo">Kenya<span>Guides</span></div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
            <span><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></span>
            <a href="index.php" class="home-btn">🏠 Home</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="active" onclick="showSection('overview')">📊 Dashboard</li>
                <li onclick="showSection('bookings')">📅 My Bookings (<?php echo $stats['pending_bookings']; ?>)</li>
                <li onclick="showSection('profile')">👤 My Profile</li>
                <li onclick="showSection('reviews')">⭐ Reviews (<?php echo $stats['total_reviews']; ?>)</li>
                <li onclick="showSection('earnings')">💰 Earnings</li>
                <li onclick="showSection('settings')">⚙️ Settings</li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">

            <!-- Overview Section -->
            <div id="overview" class="content-section active">
                <div class="page-header">
                    <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                    <p>Here's what's happening with your tours today</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <h3>Total Bookings</h3>
                        <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <h3>Pending Requests</h3>
                        <div class="stat-number"><?php echo $stats['pending_bookings']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <h3>Average Rating</h3>
                        <div class="stat-number"><?php echo number_format($stats['rating'], 1); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <h3>This Month Earnings</h3>
                        <div class="stat-number">KES <?php echo number_format($stats['monthly_earnings']); ?></div>
                    </div>
                </div>

                <!-- Upcoming Tours -->
                <div class="card">
                    <h2>Upcoming Tours</h2>
                    <?php if ($upcoming_bookings && $upcoming_bookings->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Tourist Name</th>
                                    <th>Date</th>
                                    <th>People</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $upcoming_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['tourist_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($booking['start_date'])); ?></td>
                                        <td><?php echo $booking['num_people']; ?></td>
                                        <td>KES <?php echo number_format($booking['total_price']); ?></td>
                                        <td>
                                            <!-- Booking Status Badge -->
                                            <span class="status status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>

                                            <!-- Payment Status Badge (if confirmed) -->
                                            <?php if ($booking['status'] === 'confirmed'): ?>
                                                <br>
                                                <span
                                                    class="status status-<?php echo isset($booking['payment_status']) && $booking['payment_status'] == 'paid' ? 'completed' : 'pending'; ?>"
                                                    style="margin-top: 5px; display: inline-block;">
                                                    <?php echo isset($booking['payment_status']) && $booking['payment_status'] == 'paid' ? '💰 Paid' : '⏳ Payment Pending'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button class="btn btn-primary btn-small"
                                                    onclick="handleBooking(<?php echo $booking['id']; ?>, 'accept')">Accept</button>
                                                <button class="btn btn-danger btn-small"
                                                    onclick="handleBooking(<?php echo $booking['id']; ?>, 'decline')">Decline</button>
                                            <?php elseif ($booking['status'] === 'confirmed' && (!isset($booking['payment_status']) || $booking['payment_status'] !== 'paid')): ?>
                                                <span class="status status-pending">Awaiting Payment</span>
                                            <?php elseif ($booking['status'] === 'confirmed' && isset($booking['payment_status']) && $booking['payment_status'] === 'paid'): ?>
                                                <span class="status status-completed">✓ Payment Received</span>
                                                <?php if (strtotime($booking['end_date']) < time()): ?>
                                                    <button class="btn btn-success btn-small"
                                                        onclick="completeTour(<?php echo $booking['id']; ?>)">Complete Tour</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-small"
                                                    onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">View</button>
                                            <?php endif; ?>
                                        </td>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📅</div>
                            <p>No upcoming tours scheduled</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bookings Section -->
            <div id="bookings" class="content-section">
                <div class="page-header">
                    <h1>My Bookings</h1>
                    <p>Manage all your tour bookings</p>
                </div>

                <div class="card">
                    <h2>All Bookings</h2>
                    <?php if ($all_bookings && $all_bookings->num_rows > 0): ?>
                        <?php $all_bookings->data_seek(0); // Reset pointer ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tourist</th>
                                    <th>Contact</th>
                                    <th>Date</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $all_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['tourist_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['tourist_email']); ?><br>
                                            <small><?php echo htmlspecialchars($booking['tourist_phone']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($booking['start_date'])); ?></td>
                                        <td><?php echo $booking['duration_days']; ?> days</td>
                                        <td>KES <?php echo number_format($booking['total_price']); ?></td>
                                        <td><span
                                                class="status status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button class="btn btn-primary btn-small"
                                                    onclick="handleBooking(<?php echo $booking['id']; ?>, 'accept')">Accept</button>
                                                <button class="btn btn-danger btn-small"
                                                    onclick="handleBooking(<?php echo $booking['id']; ?>, 'decline')">Decline</button>
                                            <?php else: ?>
                                                <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-small">View Details</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📅</div>
                            <p>No bookings yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Section -->
            <div id="profile" class="content-section">
                <div class="page-header">
                    <h1>My Profile</h1>
                    <p>Manage your public profile information</p>
                </div>

                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                <?php if ($user['status'] === 'verified'): ?>
                                    <span class="verified-badge">✓ Verified</span>
                                <?php endif; ?>
                            </h2>
                            <p>⭐ <?php echo number_format($stats['rating'], 1); ?>
                                (<?php echo $stats['total_reviews']; ?> reviews)</p>
                            <p>📍 <?php echo htmlspecialchars($guide_profile['location'] ?? 'Not specified'); ?></p>
                        </div>
                    </div>

                    <form>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text"
                                value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                readonly>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea rows="5"><?php echo htmlspecialchars($guide_profile['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Price Per Day (KES)</label>
                            <input type="number" value="<?php echo $guide_profile['price_per_day'] ?? 0; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Reviews Section -->
            <div id="reviews" class="content-section">
                <div class="page-header">
                    <h1>My Reviews</h1>
                    <p>See what tourists are saying about you</p>
                </div>

                <div class="card">
                    <h2>Recent Reviews (<?php echo $stats['total_reviews']; ?> total)</h2>

                    <?php if ($recent_reviews && $recent_reviews->num_rows > 0): ?>
                        <?php while ($review = $recent_reviews->fetch_assoc()): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                                    <span
                                        class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                                </div>
                                <div class="stars">
                                    <?php for ($i = 0; $i < $review['rating']; $i++)
                                        echo '★'; ?>
                                    <?php for ($i = $review['rating']; $i < 5; $i++)
                                        echo '☆'; ?>
                                </div>
                                <p><?php echo htmlspecialchars($review['comment']); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">⭐</div>
                            <p>No reviews yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Earnings Section -->
            <!-- Earnings Section - Update the payment history query -->
            <?php
            // Get payment history with actual payment status
            $payments_query = "
    SELECT 
        b.id,
        b.tourist_name,
        b.end_date,
        b.total_price,
        b.status as booking_status,
        p.status as payment_status,
        p.payment_method,
        p.payment_date,
        (b.total_price * 0.9) as guide_earnings,
        (b.total_price * 0.1) as platform_fee
    FROM bookings b
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.guide_id = ?
    AND b.status IN ('completed', 'confirmed')
    ORDER BY b.end_date DESC
    LIMIT 10
";
            $stmt = $conn->prepare($payments_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $payment_history = $stmt->get_result();
            ?>

            <!-- In the earnings section HTML -->
            <div id="earnings" class="content-section">
                <div class="page-header">
                    <h1>My Earnings</h1>
                    <p>Track your income and request payouts</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Earnings</h3>
                        <div class="stat-number">KES <?php echo number_format($stats['total_earnings']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>This Month</h3>
                        <div class="stat-number">KES <?php echo number_format($stats['monthly_earnings']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Available for Payout</h3>
                        <div class="stat-number">KES <?php echo number_format($stats['total_earnings'] * 0.9); ?></div>
                    </div>
                </div>

                <!-- Payout Request Button -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3>Request Payout</h3>
                            <p>Withdraw your earnings to M-Pesa or bank account</p>
                        </div>
                        <button class="btn btn-primary"
                            onclick="document.getElementById('payoutModal').style.display='block'">Request
                            Payout</button>
                    </div>
                </div>

                <div class="card">
                    <h2>Payment History</h2>
                    <?php if ($payment_history && $payment_history->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Booking ID</th>
                                    <th>Tourist</th>
                                    <th>Total</th>
                                    <th>Platform Fee</th>
                                    <th>Your Earnings</th>
                                    <th>Payment Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $payment_history->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['end_date'])); ?></td>
                                        <td>#<?php echo $payment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['tourist_name']); ?></td>
                                        <td>KES <?php echo number_format($payment['total_price']); ?></td>
                                        <td>KES <?php echo number_format($payment['platform_fee']); ?></td>
                                        <td><strong>KES <?php echo number_format($payment['guide_earnings']); ?></strong></td>
                                        <td>
                                            <?php if ($payment['payment_status'] === 'completed'): ?>
                                                <span class="status status-completed">Paid</span>
                                            <?php elseif ($payment['payment_status'] === 'pending'): ?>
                                                <span class="status status-pending">Pending</span>
                                            <?php else: ?>
                                                <span class="status status-pending">Awaiting Payment</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💰</div>
                            <p>No payment history yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payout Modal -->
            <div id="payoutModal"
                style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div
                    style="background: white; max-width: 500px; margin: 100px auto; padding: 2rem; border-radius: 10px;">
                    <h2 style="margin-bottom: 1.5rem;">Request Payout</h2>

                    <form action="request_payout.php" method="POST">
                        <div class="form-group">
                            <label>Amount (KES)</label>
                            <input type="number" name="amount" min="100"
                                max="<?php echo $stats['total_earnings'] * 0.9; ?>" required
                                value="<?php echo min(1000, $stats['total_earnings'] * 0.9); ?>">
                            <small style="color: #666;">Available: KES
                                <?php echo number_format($stats['total_earnings'] * 0.9); ?></small>
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" required onchange="togglePayoutFields(this.value)">
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>

                        <div class="form-group" id="mpesa-field">
                            <label>M-Pesa Phone Number</label>
                            <input type="text" name="phone" placeholder="0712345678"
                                value="<?php echo $user['phone']; ?>">
                        </div>

                        <div class="form-group" id="bank-field" style="display: none;">
                            <label>Bank Details</label>
                            <textarea name="bank_details" rows="3"
                                placeholder="Bank Name, Account Name, Account Number"></textarea>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                            <button type="button" class="btn btn-danger"
                                onclick="document.getElementById('payoutModal').style.display='none'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function togglePayoutFields(method) {
                    if (method === 'mpesa') {
                        document.getElementById('mpesa-field').style.display = 'block';
                        document.getElementById('bank-field').style.display = 'none';
                    } else {
                        document.getElementById('mpesa-field').style.display = 'none';
                        document.getElementById('bank-field').style.display = 'block';
                    }
                }
            </script>
            <!-- Settings Section -->
            <div id="settings" class="content-section">
                <div class="page-header">
                    <h1>Settings</h1>
                    <p>Manage your account settings</p>
                </div>

                <div class="card">
                    <h2>Account Settings</h2>
                    <form>
                        <div class="form-group">
                            <label>Change Password</label>
                            <input type="password" placeholder="New password">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" placeholder="Confirm new password">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Show section
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });

            document.querySelectorAll('.sidebar-menu li').forEach(item => {
                item.classList.remove('active');
            });

            document.getElementById(sectionId).classList.add('active');
            event.target.classList.add('active');
        }

        // Handle booking (accept/decline)
        function handleBooking(bookingId, action) {
            if (!confirm(`Are you sure you want to ${action} this booking?`)) {
                return;
            }

            fetch('handle_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}&action=${action}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Action failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }

        // View booking details
        function viewBookingDetails(bookingId) {
            alert('View booking details for ID: ' + bookingId);
            // In production, open a modal with full booking details
        }
        // Complete tour function
        function completeTour(bookingId) {
            if (!confirm('Mark this tour as completed? The tourist will be able to leave a review.')) {
                return;
            }

            fetch('complete_tour.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Action failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }
    </script>
</body>

</html>