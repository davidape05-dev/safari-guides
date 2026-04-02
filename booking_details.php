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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch booking details based on user role
if ($user_role === 'tourist') {
    // Tourists can only see their own bookings
    $query = "
        SELECT 
            b.*,
            CONCAT(u.first_name, ' ', u.last_name) as guide_name,
            u.email as guide_email,
            u.phone as guide_phone,
            gp.location as guide_location,
            gp.profile_photo,
            gp.bio as guide_bio,
            gp.rating as guide_rating,
            gp.total_reviews,
            gp.years_experience,
            gp.license_number
        FROM bookings b
        INNER JOIN users u ON b.guide_id = u.id
        LEFT JOIN guide_profiles gp ON u.id = gp.user_id
        WHERE b.id = ? AND b.tourist_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $user_id);
} elseif ($user_role === 'guide') {
    // Guides can see bookings assigned to them
    $query = "
        SELECT 
            b.*,
            CONCAT(u.first_name, ' ', u.last_name) as tourist_full_name,
            u.email as tourist_email,
            u.phone as tourist_phone
        FROM bookings b
        INNER JOIN users u ON b.tourist_id = u.id
        WHERE b.id = ? AND b.guide_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $user_id);
} else {
    // Admin can see any booking
    $query = "
        SELECT 
            b.*,
            CONCAT(t.first_name, ' ', t.last_name) as tourist_full_name,
            t.email as tourist_email,
            t.phone as tourist_phone,
            CONCAT(g.first_name, ' ', g.last_name) as guide_name,
            g.email as guide_email,
            g.phone as guide_phone
        FROM bookings b
        INNER JOIN users t ON b.tourist_id = t.id
        INNER JOIN users g ON b.guide_id = g.id
        WHERE b.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $booking_id);
}

$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: " . ($user_role === 'tourist' ? 'my_bookings.php' : ($user_role === 'guide' ? 'guide_dashboard.php' : 'admin_dashboard.php')));
    exit();
}

// Determine which dashboard to go back to
$back_link = '#';
if ($user_role === 'tourist') {
    $back_link = 'tourist_dashboard.php';
} elseif ($user_role === 'guide') {
    $back_link = 'guide_dashboard.php';
} elseif ($user_role === 'admin') {
    $back_link = 'admin_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?php echo $booking_id; ?> Details - KenyaGuides</title>
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

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Back Link */
        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transition: all 0.3s;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }

        /* Main Card */
        .booking-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        /* Header */
        .booking-header {
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-title h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .booking-title p {
            opacity: 0.9;
        }

        .status-badge {
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
        }

        /* Content */
        .booking-content {
            padding: 2rem;
        }

        /* Sections */
        .section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            color: #8B4513;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #228B22;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            background: white;
            padding: 1.2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c2c2c;
        }

        .info-value.large {
            font-size: 1.5rem;
            color: #228B22;
        }

        /* Profile Section */
        .profile-section {
            display: flex;
            gap: 2rem;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #228B22;
        }

        .profile-image-placeholder {
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
            border: 4px solid #8B4513;
        }

        .profile-details h2 {
            font-size: 2rem;
            color: #2c2c2c;
            margin-bottom: 0.5rem;
        }

        .profile-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .meta-tag {
            background: #e8f5e9;
            color: #228B22;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Price Breakdown */
        .price-breakdown {
            background: #e8f5e9;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #c8e6c9;
        }

        .price-row:last-child {
            border-bottom: none;
        }

        .price-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            color: #228B22;
            padding-top: 1rem;
            margin-top: 0.5rem;
            border-top: 2px dashed #228B22;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #228B22;
            color: white;
        }

        .btn-primary:hover {
            background: #1a6b1a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 139, 34, 0.3);
        }

        .btn-secondary {
            background: #8B4513;
            color: white;
        }

        .btn-secondary:hover {
            background: #6b3410;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #228B22;
            color: #228B22;
        }

        .btn-outline:hover {
            background: #228B22;
            color: white;
        }

        /* Timeline */
        .timeline {
            margin-top: 2rem;
        }

        .timeline-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-left: 3px solid #228B22;
            padding-left: 1.5rem;
            position: relative;
        }

        .timeline-item::before {
            content: '';
            width: 15px;
            height: 15px;
            background: #228B22;
            border-radius: 50%;
            position: absolute;
            left: -9px;
            top: 1.3rem;
        }

        .timeline-date {
            min-width: 120px;
            color: #666;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: #2c2c2c;
        }

        /* Tags */
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .tag {
            background: #e9ecef;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #555;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .booking-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .profile-section {
                flex-direction: column;
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .timeline-item {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="<?php echo $back_link; ?>" class="back-link">← Back to Dashboard</a>

        <div class="booking-card">
            <!-- Header -->
            <div class="booking-header">
                <div class="booking-title">
                    <h1>Booking #<?php echo $booking['id']; ?></h1>
                    <p>View detailed information about this safari booking</p>
                </div>
                <div class="status-badge">
                    <?php
                    $status_text = ucfirst($booking['status']);
                    if ($booking['status'] === 'confirmed' && isset($booking['payment_status']) && $booking['payment_status'] === 'paid') {
                        $status_text = 'Paid & Confirmed';
                    } elseif ($booking['status'] === 'confirmed') {
                        $status_text = 'Awaiting Payment';
                    }
                    echo $status_text;
                    ?>
                </div>
            </div>

            <!-- Content -->
            <div class="booking-content">
                <?php if ($user_role === 'tourist'): ?>
                    <!-- Tourist View - Show Guide Info -->
                    <div class="profile-section">
                        <?php if (!empty($booking['profile_photo'])): ?>
                            <img src="<?php echo $booking['profile_photo']; ?>" alt="Guide" class="profile-image">
                        <?php else: ?>
                            <div class="profile-image-placeholder">
                                <?php echo strtoupper(substr($booking['guide_name'] ?? 'G', 0, 1)); ?>
                            </div>
                        <?php endif; ?>

                        <div class="profile-details">
                            <h2><?php echo htmlspecialchars($booking['guide_name'] ?? 'Your Guide'); ?></h2>
                            <p>📍 <?php echo htmlspecialchars($booking['guide_location'] ?? 'Kenya'); ?></p>

                            <div class="profile-meta">
                                <?php if (isset($booking['guide_rating'])): ?>
                                    <span class="meta-tag">⭐ <?php echo number_format($booking['guide_rating'], 1); ?>
                                        (<?php echo $booking['total_reviews'] ?? 0; ?> reviews)</span>
                                <?php endif; ?>
                                <?php if (isset($booking['years_experience'])): ?>
                                    <span class="meta-tag">📅 <?php echo $booking['years_experience']; ?> years
                                        experience</span>
                                <?php endif; ?>
                                <?php if (isset($booking['license_number'])): ?>
                                    <span class="meta-tag">✓ Licensed</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($booking['guide_bio'])): ?>
                                <p style="margin-top: 1rem; color: #666;"><?php echo htmlspecialchars($booking['guide_bio']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Guide/Admin View - Show Tourist Info -->
                    <div class="profile-section">
                        <div class="profile-image-placeholder">
                            <?php echo strtoupper(substr($booking['tourist_name'] ?? 'T', 0, 1)); ?>
                        </div>

                        <div class="profile-details">
                            <h2><?php echo htmlspecialchars($booking['tourist_name']); ?></h2>
                            <p>📧 <?php echo htmlspecialchars($booking['tourist_email']); ?></p>
                            <?php if (!empty($booking['tourist_phone'])): ?>
                                <p>📞 <?php echo htmlspecialchars($booking['tourist_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Booking Details Grid -->
                <div class="section">
                    <div class="section-title">
                        <span>📋</span> Booking Information
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">📅 Start Date</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($booking['start_date'])); ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">📅 End Date</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($booking['end_date'])); ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">⏱️ Duration</div>
                            <div class="info-value"><?php echo $booking['duration_days']; ?>
                                day<?php echo $booking['duration_days'] > 1 ? 's' : ''; ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">👥 Number of People</div>
                            <div class="info-value"><?php echo $booking['num_people']; ?>
                                person<?php echo $booking['num_people'] > 1 ? 's' : ''; ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">💰 Total Price</div>
                            <div class="info-value large">KES <?php echo number_format($booking['total_price']); ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">💳 Payment Status</div>
                            <div class="info-value">
                                <?php if (isset($booking['payment_status']) && $booking['payment_status'] === 'paid'): ?>
                                    <span style="color: #28a745;">✓ Paid</span>
                                <?php elseif (isset($booking['payment_status']) && $booking['payment_status'] === 'pending'): ?>
                                    <span style="color: #ffc107;">⏳ Pending</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">❌ Not Paid</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <?php if (isset($booking['platform_fee']) && $booking['platform_fee'] > 0): ?>
                    <div class="price-breakdown">
                        <h3 style="margin-bottom: 1rem; color: #2c2c2c;">Payment Breakdown</h3>

                        <div class="price-row">
                            <span>Tour Price:</span>
                            <span>KES <?php echo number_format($booking['total_price']); ?></span>
                        </div>

                        <div class="price-row">
                            <span>Platform Fee (<?php echo $booking['commission_rate'] ?? 10; ?>%):</span>
                            <span>KES <?php echo number_format($booking['platform_fee']); ?></span>
                        </div>

                        <div class="price-row total">
                            <span>Guide Payout:</span>
                            <span>KES <?php echo number_format($booking['guide_payout']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Special Requests -->
                <?php if (!empty($booking['special_requests'])): ?>
                    <div class="section">
                        <div class="section-title">
                            <span>📝</span> Special Requests
                        </div>
                        <div class="info-item" style="background: #f8f9fa;">
                            <p style="line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Timeline -->
                <div class="section">
                    <div class="section-title">
                        <span>⏰</span> Booking Timeline
                    </div>

                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Booking Created</div>
                                <p style="color: #666; font-size: 0.9rem;">Tourist submitted booking request</p>
                            </div>
                        </div>

                        <?php if (strtotime($booking['updated_at']) > strtotime($booking['created_at'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo date('M j, Y', strtotime($booking['updated_at'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Status Updated</div>
                                    <p style="color: #666; font-size: 0.9rem;">Booking status changed to
                                        <?php echo ucfirst($booking['status']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($booking['payment_status']) && $booking['payment_status'] === 'paid'): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo date('M j, Y', strtotime($booking['updated_at'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Payment Completed</div>
                                    <p style="color: #666; font-size: 0.9rem;">Payment of KES
                                        <?php echo number_format($booking['total_price']); ?> received</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($user_role === 'tourist'): ?>
                        <?php if ($booking['status'] === 'confirmed' && (!isset($booking['payment_status']) || $booking['payment_status'] !== 'paid')): ?>
                            <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                                💳 Pay Now (KES <?php echo number_format($booking['total_price']); ?>)
                            </a>
                        <?php endif; ?>

                        <a href="mailto:<?php echo $booking['guide_email']; ?>?subject=Question about booking #<?php echo $booking['id']; ?>"
                            class="btn btn-outline">
                            📧 Contact Guide
                        </a>
                    <?php elseif ($user_role === 'guide'): ?>
                        <a href="mailto:<?php echo $booking['tourist_email']; ?>?subject=Your booking #<?php echo $booking['id']; ?>"
                            class="btn btn-primary">
                            📧 Contact Tourist
                        </a>
                    <?php endif; ?>

                    <?php if ($user_role === 'admin'): ?>
                        <a href="admin_payments.php" class="btn btn-primary">
                            💰 View Payment Details
                        </a>
                    <?php endif; ?>

                    <button onclick="window.print()" class="btn btn-outline">
                        🖨️ Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>