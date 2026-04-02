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

if (!$admin) {
    header("Location: logout.php");
    exit();
}

// Get statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total guides
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'guide'");
$stats['total_guides'] = $result->fetch_assoc()['count'];

// Total tourists
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tourist'");
$stats['total_tourists'] = $result->fetch_assoc()['count'];

// Pending verification
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'guide' AND status = 'pending'");
$stats['pending_guides'] = $result->fetch_assoc()['count'];

// Verified guides
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'guide' AND status = 'verified'");
$stats['verified_guides'] = $result->fetch_assoc()['count'];

// Total bookings
$bookings_count = $conn->query("SELECT COUNT(*) as count FROM bookings");
if ($bookings_count) {
    $stats['total_bookings'] = $bookings_count->fetch_assoc()['count'];
} else {
    $stats['total_bookings'] = 0;
}

// Pending bookings
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
if ($pending_bookings) {
    $stats['pending_bookings'] = $pending_bookings->fetch_assoc()['count'];
} else {
    $stats['pending_bookings'] = 0;
}

// Confirmed bookings
$confirmed_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'");
$stats['confirmed_bookings'] = $confirmed_bookings ? $confirmed_bookings->fetch_assoc()['count'] : 0;

// Completed bookings
$completed_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'completed'");
$stats['completed_bookings'] = $completed_bookings ? $completed_bookings->fetch_assoc()['count'] : 0;

// Total reviews
$reviews_count = $conn->query("SELECT COUNT(*) as count FROM reviews");
if ($reviews_count) {
    $stats['total_reviews'] = $reviews_count->fetch_assoc()['count'];
} else {
    $stats['total_reviews'] = 0;
}

// Average rating across all guides
$avg_rating = $conn->query("SELECT AVG(rating) as avg FROM guide_profiles WHERE rating > 0");
$stats['avg_rating'] = $avg_rating ? number_format($avg_rating->fetch_assoc()['avg'] ?? 0, 1) : '0.0';

// Total revenue
$revenue_query = $conn->query("SELECT SUM(total_price) as total FROM bookings WHERE status = 'completed'");
if ($revenue_query) {
    $revenue_result = $revenue_query->fetch_assoc();
    $stats['total_revenue'] = $revenue_result['total'] ?? 0;
} else {
    $stats['total_revenue'] = 0;
}

// Platform fees (10% of all completed bookings)
$stats['platform_fees'] = $stats['total_revenue'] * 0.1;

// Get pending guide verifications
$pending_guides_query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.created_at,
        gp.license_number,
        gp.years_experience,
        gp.location,
        gp.profile_photo,
        gp.bio,
        gp.price_per_day
    FROM users u
    INNER JOIN guide_profiles gp ON u.id = gp.user_id
    WHERE u.role = 'guide' AND u.status = 'pending'
    ORDER BY u.created_at DESC
";
$pending_guides = $conn->query($pending_guides_query);

// Get all users (for Users section)
$all_users_query = "
    SELECT 
        id,
        first_name,
        last_name,
        email,
        phone,
        role,
        status,
        created_at
    FROM users
    ORDER BY 
        CASE 
            WHEN role = 'admin' THEN 1
            WHEN role = 'guide' THEN 2
            ELSE 3
        END,
        created_at DESC
";
$all_users = $conn->query($all_users_query);

// Get all bookings (for Bookings section)
$all_bookings_query = "
    SELECT 
        b.id,
        b.tourist_name,
        b.tourist_email,
        b.start_date,
        b.end_date,
        b.total_price,
        b.status,
        b.payment_status,
        b.created_at,
        CONCAT(t.first_name, ' ', t.last_name) as tourist_full_name,
        CONCAT(g.first_name, ' ', g.last_name) as guide_name
    FROM bookings b
    LEFT JOIN users t ON b.tourist_id = t.id
    LEFT JOIN users g ON b.guide_id = g.id
    ORDER BY b.created_at DESC
    LIMIT 50
";
$all_bookings = $conn->query($all_bookings_query);

// Get all reviews (for Reviews section)
$all_reviews_query = "
    SELECT 
        r.id,
        r.rating,
        r.comment,
        r.created_at,
        CONCAT(t.first_name, ' ', t.last_name) as tourist_name,
        CONCAT(g.first_name, ' ', g.last_name) as guide_name,
        b.id as booking_id
    FROM reviews r
    LEFT JOIN users t ON r.tourist_id = t.id
    LEFT JOIN users g ON r.guide_id = g.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    ORDER BY r.created_at DESC
    LIMIT 50
";
$all_reviews = $conn->query($all_reviews_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SafariGuide</title>
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

        .stat-small {
            font-size: 1rem;
            color: #666;
            margin-top: 0.3rem;
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

        /* Tables */
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
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-verified {
            background: #d4edda;
            color: #155724;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.85rem;
            margin-right: 0.3rem;
        }

        .btn-primary {
            background: #228B22;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #2c2c2c;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Filters */
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            min-width: 200px;
        }

        .filter-select {
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal.active {
            display: block;
        }

        .modal-content {
            background: white;
            max-width: 600px;
            margin: 100px auto;
            padding: 2rem;
            border-radius: 10px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.active {
            display: block;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 550px;
            position: relative;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
        }

        .close-modal:hover {
            color: #dc3545;
        }
    </style>
</head>

<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="logo">Kenya<span>Guides</span> Admin</div>
        <div class="user-info">
            <span class="admin-badge">👤 Admin</span>
            <span><strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="active" onclick="showSection('overview')">📊 Dashboard</li>
                <li onclick="showSection('pending')">⏳ Pending Verification (<?php echo $stats['pending_guides']; ?>)
                </li>
                <li onclick="showSection('users')">👥 All Users (<?php echo $stats['total_users']; ?>)</li>
                <li onclick="showSection('guides')">🦁 All Guides (<?php echo $stats['total_guides']; ?>)</li>
                <li onclick="showSection('bookings')">📅 Bookings (<?php echo $stats['total_bookings']; ?>)</li>
                <li onclick="showSection('reviews')">⭐ Reviews (<?php echo $stats['total_reviews']; ?>)</li>
                <li onclick="showSection('payments')">💰 Payments</li>
                <li><a href="admin_reports.php" style="text-decoration: none; color: inherit; display: block;">📊
                        Reports</a></li>
                <li onclick="showSection('settings')">⚙️ Settings</li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Overview Section -->
            <div id="overview" class="content-section active">
                <div class="page-header">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($admin['first_name']); ?>!</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-small"><?php echo $stats['total_tourists']; ?> tourists ·
                            <?php echo $stats['total_guides']; ?> guides
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <h3>Pending Guides</h3>
                        <div class="stat-number"><?php echo $stats['pending_guides']; ?></div>
                        <div class="stat-small">awaiting verification</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <h3>Total Bookings</h3>
                        <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                        <div class="stat-small"><?php echo $stats['pending_bookings']; ?> pending ·
                            <?php echo $stats['completed_bookings']; ?> completed
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <h3>Average Rating</h3>
                        <div class="stat-number"><?php echo $stats['avg_rating']; ?></div>
                        <div class="stat-small">from <?php echo $stats['total_reviews']; ?> reviews</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <h3>Total Revenue</h3>
                        <div class="stat-number">KES <?php echo number_format($stats['total_revenue']); ?></div>
                        <div class="stat-small">KES <?php echo number_format($stats['platform_fees']); ?> platform fees
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <h2>Quick Actions</h2>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="showSection('pending')">Verify Pending Guides</button>
                        <button class="btn btn-primary" onclick="showSection('bookings')">View All Bookings</button>
                        <button class="btn btn-primary" onclick="showSection('payments')">Process Payments</button>
                        <a href="admin_payments.php" class="btn btn-primary">Payment Management</a>
                    </div>
                </div>
            </div>

            <!-- Pending Verification Section -->
            <div id="pending" class="content-section">
                <div class="page-header">
                    <h1>Pending Guide Verification</h1>
                    <p>Review and approve guide applications</p>
                </div>

                <div class="card">
                    <?php if ($pending_guides && $pending_guides->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>License</th>
                                    <th>Experience</th>
                                    <th>Location</th>
                                    <th>Price/Day</th>
                                    <th>Applied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($guide = $pending_guides->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo !empty($guide['profile_photo']) ? 'uploads/profiles/' . $guide['profile_photo'] : 'https://via.placeholder.com/50'; ?>"
                                                alt="Profile"
                                                style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                        </td>
                                        <td><?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($guide['email']); ?></td>
                                        <td><?php echo htmlspecialchars($guide['license_number']); ?></td>
                                        <td><?php echo $guide['years_experience']; ?> years</td>
                                        <td><?php echo htmlspecialchars($guide['location']); ?></td>
                                        <td>KES <?php echo number_format($guide['price_per_day']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($guide['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-small btn-primary"
                                                onclick="viewGuideDetails(<?php echo $guide['id']; ?>)">View</button>
                                            <button class="btn btn-small btn-success"
                                                onclick="verifyGuide(<?php echo $guide['id']; ?>, 'approve')">Approve</button>
                                            <button class="btn btn-small btn-danger"
                                                onclick="verifyGuide(<?php echo $guide['id']; ?>, 'reject')">Reject</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <h3>No Pending Verifications</h3>
                            <p>All guide applications have been processed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Users Section -->
            <div id="users" class="content-section">
                <div class="page-header">
                    <h1>All Users</h1>
                    <p>Manage platform users</p>
                </div>

                <div class="filter-bar">
                    <input type="text" id="userSearch" class="filter-input" placeholder="Search by name or email..."
                        onkeyup="filterUsers()">
                    <select id="roleFilter" class="filter-select" onchange="filterUsers()">
                        <option value="all">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="guide">Guide</option>
                        <option value="tourist">Tourist</option>
                    </select>
                    <select id="statusFilter" class="filter-select" onchange="filterUsers()">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="verified">Verified</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="card">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($all_users && $all_users->num_rows > 0): ?>
                                <?php while ($user = $all_users->fetch_assoc()): ?>
                                    <tr data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['status']; ?>">
                                        <td>#<?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status" style="background: <?php
                                            echo $user['role'] == 'admin' ? '#8B4513' :
                                                ($user['role'] == 'guide' ? '#228B22' : '#17a2b8');
                                            ?>; color: white;">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status status-<?php echo $user['status']; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-small btn-primary"
                                                onclick="viewUser(<?php echo $user['id']; ?>)">View</button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <button class="btn btn-small btn-warning"
                                                    onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                    <?php echo $user['status'] == 'suspended' ? 'Activate' : 'Suspend'; ?>
                                                </button>
                                                <button class="btn btn-small btn-danger"
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo $user['email']; ?>')">
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 2rem;">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- All Guides Section -->
            <div id="guides" class="content-section">
                <div class="page-header">
                    <h1>All Guides</h1>
                    <p>Manage all registered guides</p>
                </div>

                <div class="card">
                    <?php
                    // Reset and re-run guides query
                    $all_guides_query = "
                        SELECT 
                            u.id,
                            u.first_name,
                            u.last_name,
                            u.email,
                            u.phone,
                            u.status,
                            gp.location,
                            gp.price_per_day,
                            gp.rating,
                            gp.total_reviews,
                            gp.total_tours,
                            gp.years_experience,
                            gp.license_number
                        FROM users u
                        INNER JOIN guide_profiles gp ON u.id = gp.user_id
                        WHERE u.role = 'guide'
                        ORDER BY u.created_at DESC
                    ";
                    $all_guides = $conn->query($all_guides_query);
                    ?>

                    <?php if ($all_guides && $all_guides->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>License</th>
                                    <th>Experience</th>
                                    <th>Location</th>
                                    <th>Price/Day</th>
                                    <th>Rating</th>
                                    <th>Reviews</th>
                                    <th>Tours</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($guide = $all_guides->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $guide['id']; ?></td>
                                        <td><?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($guide['email']); ?></td>
                                        <td><?php echo htmlspecialchars($guide['license_number']); ?></td>
                                        <td><?php echo $guide['years_experience']; ?> years</td>
                                        <td><?php echo htmlspecialchars($guide['location']); ?></td>
                                        <td>KES <?php echo number_format($guide['price_per_day']); ?></td>
                                        <td><?php echo number_format($guide['rating'], 1); ?> ⭐</td>
                                        <td><?php echo $guide['total_reviews']; ?></td>
                                        <td><?php echo $guide['total_tours']; ?></td>
                                        <td>
                                            <span class="status status-<?php echo $guide['status']; ?>">
                                                <?php echo ucfirst($guide['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-small btn-primary"
                                                onclick="window.location.href='guide_profile.php?id=<?php echo $guide['id']; ?>'">View</button>
                                            <?php if ($guide['status'] === 'verified'): ?>
                                                <button class="btn btn-small btn-danger"
                                                    onclick="verifyGuide(<?php echo $guide['id']; ?>, 'suspend')">Suspend</button>
                                            <?php elseif ($guide['status'] === 'suspended'): ?>
                                                <button class="btn btn-small btn-success"
                                                    onclick="verifyGuide(<?php echo $guide['id']; ?>, 'activate')">Activate</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🦁</div>
                            <h3>No Guides Found</h3>
                            <p>There are no registered guides yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bookings Section -->
            <div id="bookings" class="content-section">
                <div class="page-header">
                    <h1>All Bookings</h1>
                    <p>Monitor and manage bookings</p>
                </div>

                <div class="filter-bar">
                    <input type="text" id="bookingSearch" class="filter-input"
                        placeholder="Search by tourist or guide..." onkeyup="filterBookings()">
                    <select id="bookingStatusFilter" class="filter-select" onchange="filterBookings()">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="card">
                    <?php if ($all_bookings && $all_bookings->num_rows > 0): ?>
                        <table id="bookingsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tourist</th>
                                    <th>Guide</th>
                                    <th>Dates</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Booked</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $all_bookings->fetch_assoc()): ?>
                                    <tr data-status="<?php echo $booking['status']; ?>">
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['tourist_name'] ?: $booking['tourist_full_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['guide_name']); ?></td>
                                        <td><?php echo date('M j', strtotime($booking['start_date'])); ?> -
                                            <?php echo date('M j, Y', strtotime($booking['end_date'])); ?>
                                        </td>
                                        <td>KES <?php echo number_format($booking['total_price']); ?></td>
                                        <td>
                                            <span class="status status-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($booking['payment_status'] == 'paid'): ?>
                                                <span class="status status-paid">Paid</span>
                                            <?php else: ?>
                                                <span class="status status-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-small btn-primary"
                                                onclick="viewBooking(<?php echo $booking['id']; ?>)">View</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📅</div>
                            <h3>No Bookings Yet</h3>
                            <p>There are no bookings in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Section -->
            <div id="reviews" class="content-section">
                <div class="page-header">
                    <h1>Reviews Management</h1>
                    <p>Monitor and moderate reviews</p>
                </div>

                <div class="card">
                    <?php if ($all_reviews && $all_reviews->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tourist</th>
                                    <th>Guide</th>
                                    <th>Rating</th>
                                    <th>Review</th>
                                    <th>Booking</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($review = $all_reviews->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $review['id']; ?></td>
                                        <td><?php echo htmlspecialchars($review['tourist_name'] ?? 'Anonymous'); ?></td>
                                        <td><?php echo htmlspecialchars($review['guide_name']); ?></td>
                                        <td>
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $review['rating'] ? '★' : '☆';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div
                                                style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($review['comment']); ?>
                                            </div>
                                        </td>
                                        <td>#<?php echo $review['booking_id']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($review['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-small btn-primary"
                                                onclick="viewReview(<?php echo $review['id']; ?>)">View</button>
                                            <?php if ($review['status'] != 'hidden'): ?>
                                                <button class="btn btn-small btn-warning"
                                                    onclick="hideReview(<?php echo $review['id']; ?>)">Hide</button>
                                            <?php else: ?>
                                                <button class="btn btn-small btn-success"
                                                    onclick="showReview(<?php echo $review['id']; ?>)">Show</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">⭐</div>
                            <h3>No Reviews Yet</h3>
                            <p>There are no reviews in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payments Section -->
            <div id="payments" class="content-section">
                <div class="page-header">
                    <h1>Payment Management</h1>
                    <p>View and manage payments</p>
                </div>

                <div class="card">
                    <p style="text-align: center; padding: 2rem;">
                        <a href="admin_payments.php" class="btn btn-primary">Go to Payment Management Page</a>
                    </p>
                </div>
            </div>

            <!-- Settings Section -->
            <div id="settings" class="content-section">
                <div class="page-header">
                    <h1>Settings</h1>
                    <p>System configuration</p>
                </div>

                <div class="card">
                    <h2>Admin Account</h2>
                    <form>
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text"
                                value="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>"
                                readonly>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" readonly>
                        </div>
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

                    <h2 style="margin-top: 2rem;">Platform Settings</h2>
                    <form>
                        <div class="form-group">
                            <label>Commission Rate (%)</label>
                            <input type="number" value="10" min="0" max="100" step="0.1">
                        </div>
                        <div class="form-group">
                            <label>Minimum Payout Amount (KES)</label>
                            <input type="number" value="100" min="0">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- User Details Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('userModal')">&times;</span>
            <div id="userModalContent"></div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('bookingModal')">&times;</span>
            <div id="bookingModalContent"></div>
        </div>
    </div>

    <!-- Review Details Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('reviewModal')">&times;</span>
            <div id="reviewModalContent"></div>
        </div>
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

        // Verify guide
        function verifyGuide(guideId, action) {
            if (!confirm(`Are you sure you want to ${action} this guide?`)) {
                return;
            }

            fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&guide_id=${guideId}`
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

        // View guide details
        function viewGuideDetails(guideId) {
            fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_details&guide_id=${guideId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const guide = data.guide;
                        const modalContent = `
                        <h2>${guide.first_name} ${guide.last_name}</h2>
                        <img src="${guide.profile_photo ? 'uploads/profiles/' + guide.profile_photo : 'https://via.placeholder.com/200'}" 
                             style="width: 200px; height: 200px; border-radius: 50%; object-fit: cover; display: block; margin: 1rem auto;">
                        <p><strong>Email:</strong> ${guide.email}</p>
                        <p><strong>Phone:</strong> ${guide.phone}</p>
                        <p><strong>License:</strong> ${guide.license_number}</p>
                        <p><strong>Experience:</strong> ${guide.years_experience} years</p>
                        <p><strong>Location:</strong> ${guide.location}</p>
                        <p><strong>Price:</strong> KES ${guide.price_per_day}/day</p>
                        <p><strong>Categories:</strong> ${guide.categories}</p>
                        <p><strong>Languages:</strong> ${guide.languages}</p>
                        <p><strong>Bio:</strong> ${guide.bio}</p>
                        <div style="margin-top: 1.5rem;">
                            <button class="btn btn-primary" onclick="verifyGuide(${guideId}, 'approve'); closeModal('userModal');">Approve</button>
                            <button class="btn btn-danger" onclick="verifyGuide(${guideId}, 'reject'); closeModal('userModal');">Reject</button>
                        </div>
                    `;
                        document.getElementById('userModalContent').innerHTML = modalContent;
                        document.getElementById('userModal').classList.add('active');
                    }
                });
        }

        // View user
        // View user details - opens modal with user information
        function viewUser(userId) {
            console.log("Viewing user: " + userId);

            fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_user_details&user_id=${userId}`
            })
                .then(response => response.json())
                .then(data => {
                    console.log("Response:", data);

                    if (data.success) {
                        const user = data.user;

                        // Format status color
                        let statusColor = '';
                        if (user.status === 'verified') statusColor = '#28a745';
                        else if (user.status === 'active') statusColor = '#17a2b8';
                        else if (user.status === 'pending') statusColor = '#ffc107';
                        else if (user.status === 'suspended') statusColor = '#dc3545';

                        let modalContent = `
                <h2 style="color: #8B4513; margin-bottom: 15px;">User Details</h2>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <p><strong><i class="fas fa-user"></i> Name:</strong></p><p>${user.first_name} ${user.last_name}</p>
                        <p><strong><i class="fas fa-envelope"></i> Email:</strong></p><p>${user.email}</p>
                        <p><strong><i class="fas fa-phone"></i> Phone:</strong></p><p>${user.phone || 'Not provided'}</p>
                        <p><strong><i class="fas fa-tag"></i> Role:</strong></p><p><span style="background: ${user.role === 'admin' ? '#8B4513' : (user.role === 'guide' ? '#228B22' : '#17a2b8')}; color: white; padding: 3px 10px; border-radius: 15px;">${user.role.toUpperCase()}</span></p>
                        <p><strong><i class="fas fa-flag"></i> Status:</strong></p><p><span style="background: ${statusColor}; color: white; padding: 3px 10px; border-radius: 15px;">${user.status.toUpperCase()}</span></p>
                        <p><strong><i class="fas fa-calendar"></i> Joined:</strong></p><p>${new Date(user.created_at).toLocaleDateString()}</p>
                        <p><strong><i class="fas fa-clock"></i> Last Updated:</strong></p><p>${new Date(user.updated_at).toLocaleDateString()}</p>
                    </div>
                </div>
            `;

                        // Add guide-specific information if user is a guide
                        if (user.role === 'guide' && user.guide_profile) {
                            modalContent += `
                    <h3 style="color: #8B4513; margin: 15px 0 10px;">Guide Information</h3>
                    <div style="background: #e8f5e9; padding: 15px; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <p><strong>📜 License:</strong></p><p>${user.guide_profile.license_number || 'Not provided'}</p>
                            <p><strong>💰 Price/Day:</strong></p><p>KES ${user.guide_profile.price_per_day || 0}</p>
                            <p><strong>📍 Location:</strong></p><p>${user.guide_profile.location || 'Not specified'}</p>
                            <p><strong>⭐ Experience:</strong></p><p>${user.guide_profile.years_experience || 0} years</p>
                            <p><strong>⭐ Rating:</strong></p><p>${user.guide_profile.rating || 0} (${user.guide_profile.total_reviews || 0} reviews)</p>
                            <p><strong>🏆 Tours:</strong></p><p>${user.guide_profile.total_tours || 0}</p>
                        </div>
                        <p><strong>📝 Bio:</strong> ${user.guide_profile.bio || 'No bio provided'}</p>
                    </div>
                `;
                        }

                        // Add booking summary for tourists
                        if (user.role === 'tourist') {
                            modalContent += `
                    <div id="bookingInfo${user.id}" style="margin-top: 15px;">
                        <p style="text-align: center;">Loading booking information...</p>
                    </div>
                `;
                        }

                        modalContent += `
                <div style="margin-top: 20px; text-align: right;">
                    <button class="btn btn-secondary" onclick="closeModal('userModal')">Close</button>
                </div>
            `;

                        document.getElementById('userModalContent').innerHTML = modalContent;
                        document.getElementById('userModal').classList.add('active');

                        // Load booking stats for tourists
                        if (user.role === 'tourist') {
                            fetch('admin_actions.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=get_user_bookings&user_id=${userId}`
                            })
                                .then(res => res.json())
                                .then(bookingData => {
                                    if (bookingData.success) {
                                        const bookingHtml = `
                            <h3 style="color: #8B4513; margin: 15px 0 10px;">Booking Summary</h3>
                            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                    <p><strong>📅 Total Bookings:</strong></p><p>${bookingData.total_bookings}</p>
                                    <p><strong>✅ Completed Tours:</strong></p><p>${bookingData.completed}</p>
                                    <p><strong>⏳ Pending:</strong></p><p>${bookingData.pending}</p>
                                    <p><strong>📋 Confirmed:</strong></p><p>${bookingData.confirmed}</p>
                                    <p><strong>❌ Cancelled:</strong></p><p>${bookingData.cancelled}</p>
                                    <p><strong>💰 Total Spent:</strong></p><p>KES ${bookingData.total_spent ? Number(bookingData.total_spent).toLocaleString() : '0'}</p>
                                </div>
                            </div>
                        `;
                                        document.querySelector(`#bookingInfo${user.id}`).innerHTML = bookingHtml;
                                    }
                                });
                        }

                    } else {
                        alert('Failed to load user details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }

        // Toggle user status
        function toggleUserStatus(userId, currentStatus) {
            const action = currentStatus === 'suspended' ? 'activate' : 'suspend';
            verifyGuide(userId, action);
        }

        // View booking
        function viewBooking(bookingId) {
            window.location.href = 'booking_details.php?id=' + bookingId;
        }

        // View review
        function viewReview(reviewId) {
            alert('View review details for ID: ' + reviewId);
        }

        // Hide review
        function hideReview(reviewId) {
            if (confirm('Hide this review?')) {
                alert('Review hidden (demo)');
            }
        }

        // Show review
        function showReview(reviewId) {
            if (confirm('Show this review?')) {
                alert('Review shown (demo)');
            }
        }

        // Filter users
        function filterUsers() {
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#usersTable tbody tr');

            rows.forEach(row => {
                const name = row.cells[1]?.textContent.toLowerCase() || '';
                const email = row.cells[2]?.textContent.toLowerCase() || '';
                const role = row.getAttribute('data-role');
                const status = row.getAttribute('data-status');

                const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
                const matchesRole = roleFilter === 'all' || role === roleFilter;
                const matchesStatus = statusFilter === 'all' || status === statusFilter;

                row.style.display = matchesSearch && matchesRole && matchesStatus ? '' : 'none';
            });
        }

        // Filter bookings
        function filterBookings() {
            const searchTerm = document.getElementById('bookingSearch').value.toLowerCase();
            const statusFilter = document.getElementById('bookingStatusFilter').value;
            const rows = document.querySelectorAll('#bookingsTable tbody tr');

            rows.forEach(row => {
                const tourist = row.cells[1]?.textContent.toLowerCase() || '';
                const guide = row.cells[2]?.textContent.toLowerCase() || '';
                const status = row.getAttribute('data-status');

                const matchesSearch = tourist.includes(searchTerm) || guide.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Delete user
        function deleteUser(userId, userEmail) {
            if (!confirm(`Are you sure you want to permanently delete user "${userEmail}"? This action cannot be undone!`)) {
                return;
            }

            fetch('admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_user&user_id=${userId}`
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
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
    <!-- User Details Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('userModal')">&times;</span>
            <div id="userModalContent"></div>
        </div>
    </div>

    <!-- Guide Details Modal (if you have one) -->
    <div id="guideModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('guideModal')">&times;</span>
            <div id="guideModalContent"></div>
        </div>
    </div>
</body>

</html>