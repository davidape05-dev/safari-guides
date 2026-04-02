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

// Get report type (from URL, not from _blank target)
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - SafariGuide</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
        }
        .top-nav {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8B4513;
        }
        .logo span { color: #228B22; }
        .nav-links { display: flex; gap: 2rem; }
        .nav-links a {
            color: #2c2c2c;
            text-decoration: none;
            font-weight: 500;
        }
        .nav-links a.active {
            color: #228B22;
            font-weight: 600;
        }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .admin-badge {
            background: #8B4513;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
        }
        .logout-btn {
            padding: 0.5rem 1rem;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .page-header h1 { color: #2c2c2c; }
        
        /* Report Tabs */
        .report-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .report-tab {
            padding: 0.8rem 1.5rem;
            background: white;
            border-radius: 5px;
            text-decoration: none;
            color: #2c2c2c;
            font-weight: 500;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .report-tab:hover {
            background: #e8f5e9;
            border-color: #228B22;
        }
        .report-tab.active {
            background: #228B22;
            color: white;
        }
        
        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: #228B22;
            color: white;
        }
        .btn-outline {
            background: white;
            border: 2px solid #228B22;
            color: #228B22;
        }
        .btn-secondary {
            background: #8B4513;
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
        }
        
        /* Cards */
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
            text-align: center;
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
        }
        table td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        /* Print styles */
        @media print {
            .top-nav, .report-tabs, .action-buttons, .btn, .no-print {
                display: none !important;
            }
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="logo">Kenya<span>Guides</span> Admin</div>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_reports.php" class="active">Reports</a>
        </div>
        <div class="user-info">
            <span class="admin-badge">Admin</span>
            <span><?php echo htmlspecialchars($admin['first_name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>System Reports</h1>
            <div class="action-buttons no-print">
                <!-- Print button - uses window.print() NOT target="_blank" -->
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="fas fa-print"></i> Print This Page
                </button>
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Report Tabs - Generate Report Buttons (NO target="_blank") -->
        <div class="report-tabs no-print">
            <a href="?report_type=overview" class="report-tab <?php echo $report_type == 'overview' ? 'active' : ''; ?>">
                📊 Overview
            </a>
            <a href="?report_type=bookings" class="report-tab <?php echo $report_type == 'bookings' ? 'active' : ''; ?>">
                📅 Bookings
            </a>
            <a href="?report_type=revenue" class="report-tab <?php echo $report_type == 'revenue' ? 'active' : ''; ?>">
                💰 Revenue
            </a>
            <a href="?report_type=guides" class="report-tab <?php echo $report_type == 'guides' ? 'active' : ''; ?>">
                🦁 Guides
            </a>
            <a href="?report_type=users" class="report-tab <?php echo $report_type == 'users' ? 'active' : ''; ?>">
                👥 Users
            </a>
        </div>

        <!-- Report Content -->
        <div class="card">
            <?php if ($report_type == 'overview'): ?>
                <h2>System Overview Report</h2>
                <?php
                $total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
                $total_guides = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='guide'")->fetch_assoc()['c'];
                $total_tourists = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='tourist'")->fetch_assoc()['c'];
                $total_bookings = $conn->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'];
                $total_revenue = $conn->query("SELECT SUM(total_price) as t FROM bookings WHERE status='completed'")->fetch_assoc()['t'] ?? 0;
                $total_reviews = $conn->query("SELECT COUNT(*) as c FROM reviews")->fetch_assoc()['c'];
                ?>
                <div class="stats-grid">
                    <div class="stat-card"><h3>Total Users</h3><div class="stat-number"><?php echo $total_users; ?></div></div>
                    <div class="stat-card"><h3>Tourists</h3><div class="stat-number"><?php echo $total_tourists; ?></div></div>
                    <div class="stat-card"><h3>Guides</h3><div class="stat-number"><?php echo $total_guides; ?></div></div>
                    <div class="stat-card"><h3>Bookings</h3><div class="stat-number"><?php echo $total_bookings; ?></div></div>
                    <div class="stat-card"><h3>Revenue</h3><div class="stat-number">KES <?php echo number_format($total_revenue); ?></div></div>
                    <div class="stat-card"><h3>Reviews</h3><div class="stat-number"><?php echo $total_reviews; ?></div></div>
                </div>
                
            <?php elseif ($report_type == 'bookings'): ?>
                <h2>Bookings Report</h2>
                <?php
                $bookings = $conn->query("
                    SELECT b.*, 
                           CONCAT(t.first_name, ' ', t.last_name) as tourist,
                           CONCAT(g.first_name, ' ', g.last_name) as guide
                    FROM bookings b
                    LEFT JOIN users t ON b.tourist_id = t.id
                    LEFT JOIN users g ON b.guide_id = g.id
                    ORDER BY b.created_at DESC
                    LIMIT 50
                ");
                ?>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Tourist</th><th>Guide</th><th>Date</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($b = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['tourist']); ?></td>
                            <td><?php echo htmlspecialchars($b['guide']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($b['created_at'])); ?></td>
                            <td>KES <?php echo number_format($b['total_price']); ?></td>
                            <td><span style="background:<?php echo $b['status']=='completed'?'#d4edda':'#fff3cd'; ?>;padding:0.3rem 0.8rem;border-radius:20px;"><?php echo ucfirst($b['status']); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'revenue'): ?>
                <h2>Revenue Report</h2>
                <?php
                $revenue = $conn->query("
                    SELECT DATE(created_at) as date, 
                           COUNT(*) as bookings,
                           SUM(total_price) as revenue
                    FROM bookings 
                    WHERE status='completed'
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                    LIMIT 30
                ");
                ?>
                <table>
                    <thead><tr><th>Date</th><th>Bookings</th><th>Revenue</th><th>Platform Fee</th></tr></thead>
                    <tbody>
                        <?php while ($r = $revenue->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($r['date'])); ?></td>
                            <td><?php echo $r['bookings']; ?></td>
                            <td>KES <?php echo number_format($r['revenue']); ?></td>
                            <td>KES <?php echo number_format($r['revenue'] * 0.1); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'guides'): ?>
                <h2>Guide Performance Report</h2>
                <?php
                $guides = $conn->query("
                    SELECT 
                        CONCAT(u.first_name, ' ', u.last_name) as name,
                        gp.location,
                        COUNT(b.id) as total_bookings,
                        SUM(CASE WHEN b.status='completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN b.status='completed' THEN b.total_price ELSE 0 END) as revenue,
                        AVG(r.rating) as rating
                    FROM users u
                    JOIN guide_profiles gp ON u.id = gp.user_id
                    LEFT JOIN bookings b ON u.id = b.guide_id
                    LEFT JOIN reviews r ON u.id = r.guide_id
                    WHERE u.role='guide'
                    GROUP BY u.id
                    ORDER BY revenue DESC
                ");
                ?>
                <table>
                    <thead><tr><th>Guide</th><th>Location</th><th>Bookings</th><th>Completed</th><th>Revenue</th><th>Rating</th></tr></thead>
                    <tbody>
                        <?php while ($g = $guides->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($g['name']); ?></td>
                            <td><?php echo htmlspecialchars($g['location']); ?></td>
                            <td><?php echo $g['total_bookings']; ?></td>
                            <td><?php echo $g['completed']; ?></td>
                            <td>KES <?php echo number_format($g['revenue'] ?? 0); ?></td>
                            <td><?php echo number_format($g['rating'] ?? 0, 1); ?> ⭐</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
            <?php elseif ($report_type == 'users'): ?>
                <h2>User Registrations Report</h2>
                <?php
                $users = $conn->query("
                    SELECT DATE(created_at) as date,
                           COUNT(*) as total,
                           SUM(CASE WHEN role='tourist' THEN 1 ELSE 0 END) as tourists,
                           SUM(CASE WHEN role='guide' THEN 1 ELSE 0 END) as guides
                    FROM users
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                    LIMIT 30
                ");
                ?>
                <table>
                    <thead><tr><th>Date</th><th>Total</th><th>Tourists</th><th>Guides</th></tr></thead>
                    <tbody>
                        <?php while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($u['date'])); ?></td>
                            <td><?php echo $u['total']; ?></td>
                            <td><?php echo $u['tourists']; ?></td>
                            <td><?php echo $u['guides']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; color: #666;">
                Generated: <?php echo date('F j, Y \a\t g:i A'); ?>
            </div>
        </div>
    </div>
</body>
</html>