<?php
session_start();
require_once 'db.php';

// Check if guide ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: find_guide.php");
    exit();
}

$guide_user_id = (int) $_GET['id'];

// Fetch guide details with user information
$query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.status,
        gp.id as guide_profile_id,
        gp.license_number,
        gp.years_experience,
        gp.price_per_day,
        gp.location,
        gp.bio,
        gp.rating,
        gp.total_reviews,
        gp.total_tours,
        gp.profile_photo,
        gp.license_document,
        gp.created_at,
        GROUP_CONCAT(DISTINCT gc.category SEPARATOR '|') as categories,
        GROUP_CONCAT(DISTINCT gl.language SEPARATOR '|') as languages
    FROM users u
    INNER JOIN guide_profiles gp ON u.id = gp.user_id
    LEFT JOIN guide_categories gc ON gp.id = gc.guide_id
    LEFT JOIN guide_languages gl ON gp.id = gl.guide_id
    WHERE u.id = ? AND u.role = 'guide'
    GROUP BY u.id, gp.id
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $guide_user_id);
$stmt->execute();
$result = $stmt->get_result();
$guide = $result->fetch_assoc();

if (!$guide) {
    header("Location: find_guide.php?error=guide_not_found");
    exit();
}

// Fetch portfolio images
$portfolio_query = "SELECT image_path FROM portfolio_images WHERE guide_id = ? ORDER BY uploaded_at DESC";
$portfolio_stmt = $conn->prepare($portfolio_query);
$portfolio_stmt->bind_param("i", $guide['guide_profile_id']);
$portfolio_stmt->execute();
$portfolio_result = $portfolio_stmt->get_result();
$portfolio_images = $portfolio_result->fetch_all(MYSQLI_ASSOC);

// Convert comma-separated strings to arrays
$categories = !empty($guide['categories']) ? explode('|', $guide['categories']) : [];
$languages = !empty($guide['languages']) ? explode('|', $guide['languages']) : [];

// Check if user is logged in for booking
$is_logged_in = isset($_SESSION['user_id']);
$logged_in_user_id = $is_logged_in ? $_SESSION['user_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?> - Tour Guide Profile
    </title>
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
            color: #333;
        }

        /* Navigation */
        nav {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8B4513;
        }

        .logo span {
            color: #228B22;
        }

        .back-link {
            color: #8B4513;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: #228B22;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: -2rem auto 3rem;
            padding: 0 2rem;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .profile-image {
            width: 100%;
            height: 300px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-info h2 {
            font-size: 2rem;
            color: #8B4513;
            margin-bottom: 0.5rem;
        }

        .verified-badge {
            display: inline-block;
            background: #228B22;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
            font-size: 1.2rem;
        }

        .stars {
            color: #FFD700;
        }

        .reviews {
            color: #666;
            font-size: 1rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #228B22;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        .price-box {
            background: #228B22;
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            margin-top: 1rem;
        }

        .price-box h3 {
            font-size: 2rem;
            margin-bottom: 0.3rem;
        }

        .price-box p {
            opacity: 0.9;
        }

        /* Details Section */
        .details-section {
            padding: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #8B4513;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #228B22;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            display: flex;
            align-items: start;
            gap: 0.8rem;
        }

        .detail-icon {
            font-size: 1.5rem;
        }

        .detail-content h4 {
            color: #8B4513;
            margin-bottom: 0.3rem;
        }

        .detail-content p {
            color: #666;
        }

        .bio {
            line-height: 1.8;
            color: #555;
            margin-bottom: 2rem;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .tag {
            background: #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #555;
        }

        /* Portfolio */
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .portfolio-item {
            height: 200px;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .portfolio-item:hover {
            transform: scale(1.05);
        }

        .portfolio-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Booking Section */
        .booking-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 1rem;
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
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-header {
                grid-template-columns: 1fr;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 1.8rem;
            }
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav>
        <div class="nav-container">
            <div class="logo">Kenya<span>Guides</span></div>
            <a href="find_guide.php" class="back-link">← Back to Search</a>
        </div>
    </nav>

    <!-- Hero -->
    <div class="hero">
        <h1><?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?></h1>
        <p>Professional Tour Guide in <?php echo htmlspecialchars($guide['location']); ?></p>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <img src="<?php echo !empty($guide['profile_photo']) ? 'uploads/profiles/' . htmlspecialchars($guide['profile_photo']) : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=400'; ?>"
                    alt="Profile Photo" class="profile-image">

                <div class="profile-info">
                    <h2>
                        <?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?>
                        <?php if ($guide['status'] === 'verified'): ?>
                            <span class="verified-badge">✓ Verified</span>
                        <?php endif; ?>
                    </h2>

                    <div class="rating">
                        <span class="stars">
                            <?php
                            $rating = $guide['rating'];
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating ? '★' : '☆';
                            }
                            ?>
                        </span>
                        <span class="reviews">(<?php echo $guide['total_reviews']; ?> reviews)</span>
                    </div>

                    <div class="stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $guide['years_experience']; ?></div>
                            <div class="stat-label">Years Experience</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $guide['total_tours']; ?></div>
                            <div class="stat-label">Tours Completed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($guide['rating'], 1); ?></div>
                            <div class="stat-label">Average Rating</div>
                        </div>
                    </div>

                    <div class="price-box">
                        <h3>KES <?php echo number_format($guide['price_per_day']); ?></h3>
                        <p>per day</p>
                    </div>
                </div>
            </div>

            <div class="details-section">
                <h3 class="section-title">About Me</h3>
                <p class="bio"><?php echo nl2br(htmlspecialchars($guide['bio'])); ?></p>

                <h3 class="section-title">Details</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-icon">📍</span>
                        <div class="detail-content">
                            <h4>Location</h4>
                            <p><?php echo htmlspecialchars($guide['location']); ?></p>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-icon">🎯</span>
                        <div class="detail-content">
                            <h4>Specializations</h4>
                            <div class="tags">
                                <?php foreach ($categories as $category): ?>
                                    <span class="tag"><?php echo htmlspecialchars($category); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-icon">🗣️</span>
                        <div class="detail-content">
                            <h4>Languages</h4>
                            <div class="tags">
                                <?php foreach ($languages as $language): ?>
                                    <span class="tag"><?php echo htmlspecialchars($language); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-item">
                        <span class="detail-icon">📜</span>
                        <div class="detail-content">
                            <h4>License</h4>
                            <p><?php echo htmlspecialchars($guide['license_number']); ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($portfolio_images)): ?>
                    <h3 class="section-title">Portfolio</h3>
                    <div class="portfolio-grid">
                        <?php foreach ($portfolio_images as $image): ?>
                            <div class="portfolio-item">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Portfolio Image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Booking Section -->
        <div class="booking-section">
            <h3 class="section-title">Ready to Book?</h3>

            <?php if (!$is_logged_in): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Please log in to book this guide</strong>
                </div>
                <div class="button-group">
                    <a href="login.php" class="btn btn-primary">Log In to Book</a>
                    <a href="signup.php" class="btn btn-secondary">Create Account</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>📅 Booking is available!</strong> Contact the guide or create a booking request.
                </div>
                <div class="button-group">
                    <a href="book_guide.php?id=<?php echo $guide['id']; ?>" class="btn btn-primary">Book This Guide</a>
                    <a href="mailto:<?php echo htmlspecialchars($guide['email']); ?>" class="btn btn-secondary">Contact
                        Guide</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>