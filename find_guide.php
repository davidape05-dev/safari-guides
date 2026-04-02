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

// Fetch the logged-in user's data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If user does not exist, logout
if (!$user) {
    header("Location: logout.php");
    exit();
}

// ============================================
// FETCH ALL GUIDES WITH FILTERS
// ============================================

// Get filter parameters
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (int) $_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (int) $_GET['max_price'] : 999999;
$experience = isset($_GET['experience']) ? $_GET['experience'] : '';
$language = isset($_GET['language']) ? $_GET['language'] : '';
$minRating = isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? (float) $_GET['min_rating'] : 0;
$verifiedOnly = isset($_GET['verified']) ? true : false;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'rating';

// Build the query
$sql = "
    SELECT DISTINCT
        u.id,
        u.first_name,
        u.last_name,
        u.status,
        gp.id as guide_profile_id,
        gp.price_per_day,
        gp.location,
        gp.years_experience,
        gp.rating,
        gp.total_reviews,
        gp.profile_photo,
        gp.total_tours,
        GROUP_CONCAT(DISTINCT gc.category SEPARATOR ', ') as categories,
        GROUP_CONCAT(DISTINCT gl.language SEPARATOR ', ') as languages
    FROM users u
    INNER JOIN guide_profiles gp ON u.id = gp.user_id
    LEFT JOIN guide_categories gc ON gp.id = gc.guide_id
    LEFT JOIN guide_languages gl ON gp.id = gl.guide_id
    WHERE u.role = 'guide'
";

$params = [];
$types = '';

// Apply filters
if ($verifiedOnly) {
    $sql .= " AND u.status = 'verified'";
} else {
    $sql .= " AND u.status IN ('verified', 'active')";
}

if (!empty($searchTerm)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR gp.location LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($category)) {
    $sql .= " AND gc.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($location)) {
    $sql .= " AND gp.location = ?";
    $params[] = $location;
    $types .= 's';
}

if ($minPrice > 0 || $maxPrice < 999999) {
    $sql .= " AND gp.price_per_day BETWEEN ? AND ?";
    $params[] = $minPrice;
    $params[] = $maxPrice;
    $types .= 'ii';
}

if (!empty($language)) {
    $sql .= " AND gl.language = ?";
    $params[] = $language;
    $types .= 's';
}

if ($minRating > 0) {
    $sql .= " AND gp.rating >= ?";
    $params[] = $minRating;
    $types .= 'd';
}

if (!empty($experience)) {
    switch ($experience) {
        case '1-3':
            $sql .= " AND gp.years_experience BETWEEN 1 AND 3";
            break;
        case '3-5':
            $sql .= " AND gp.years_experience BETWEEN 3 AND 5";
            break;
        case '5-10':
            $sql .= " AND gp.years_experience BETWEEN 5 AND 10";
            break;
        case '10+':
            $sql .= " AND gp.years_experience >= 10";
            break;
    }
}

$sql .= " GROUP BY u.id, gp.id";

// Add sorting
switch ($sortBy) {
    case 'price-low':
        $sql .= " ORDER BY gp.price_per_day ASC";
        break;
    case 'price-high':
        $sql .= " ORDER BY gp.price_per_day DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY gp.rating DESC, gp.total_reviews DESC";
        break;
    case 'experience':
        $sql .= " ORDER BY gp.years_experience DESC";
        break;
    default:
        $sql .= " ORDER BY gp.rating DESC, gp.total_reviews DESC";
}

// Prepare and execute
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$guidesResult = $stmt->get_result();
$guides = $guidesResult->fetch_all(MYSQLI_ASSOC);
$totalGuides = count($guides);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Your Perfect Tour Guide - SafariGuide</title>
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
            max-width: 1400px;
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

        /* Search Header */
        .search-header {
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .search-header h1 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
        }

        .search-box {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            gap: 1rem;
        }

        .search-box input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .search-box button {
            padding: 1rem 2rem;
            background: #8B4513;
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-box button:hover {
            background: #6b3410;
            transform: translateY(-2px);
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }

        /* Filters Sidebar */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .filters h3 {
            color: #8B4513;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #228B22;
        }

        .filter-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c2c2c;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"],
        .checkbox-item input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-item span {
            color: #666;
            cursor: pointer;
        }

        .checkbox-item:hover span {
            color: #228B22;
        }

        .filter-group select {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }

        .price-range {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 0.5rem;
            align-items: center;
        }

        .price-range input {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
        }

        .clear-filters {
            display: block;
            text-align: center;
            padding: 0.5rem;
            background: #f5f5f5;
            color: #666;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .clear-filters:hover {
            background: #e0e0e0;
            color: #dc3545;
        }

        /* Results Section */
        .results-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .results-count {
            font-size: 1.1rem;
            color: #666;
        }

        .sort-by {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sort-by select {
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }

        /* Guides Grid */
        .guides-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .guide-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .guide-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .guide-image {
            height: 200px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .guide-image.has-image {
            background-color: #333;
        }

        .guide-image.no-image {
            background: linear-gradient(135deg, #8B4513, #228B22);
        }

        .guide-image .initials {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .verified-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #228B22;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .guide-info {
            padding: 1.5rem;
        }

        .guide-name {
            font-size: 1.2rem;
            color: #2c2c2c;
            margin-bottom: 0.3rem;
        }

        .guide-specialty {
            color: #8B4513;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .guide-details {
            margin: 1rem 0;
            font-size: 0.9rem;
            color: #666;
        }

        .guide-details div {
            margin-bottom: 0.3rem;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .stars {
            color: #FFD700;
        }

        .reviews {
            color: #666;
            font-size: 0.9rem;
        }

        .guide-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #228B22;
            margin: 1rem 0;
        }

        .view-profile-btn {
            width: 100%;
            padding: 0.8rem;
            background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 139, 34, 0.3);
        }

        .filter-actions {
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
        }

        .btn-primary {
            background: #228B22;
            color: white;
        }

        .btn-secondary {
            background: #8B4513;
            color: white;
        }

        .btn-small {
            padding: 0.3rem 0.8rem;
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }

            .filters {
                position: static;
            }

            .search-header h1 {
                font-size: 1.8rem;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav>
        <div class="nav-container">
            <div class="logo">Kenya<span>Guides</span></div>
            <a href="index.php" style="text-decoration: none; color: #8B4513; font-weight: 600;">← Back to Home</a>
        </div>
    </nav>

    <!-- Search Header -->
    <div class="search-header">
        <h1>Find Your Perfect Tour Guide</h1>
        <form method="GET" action="" class="search-box" id="searchForm">
            <input type="text" name="search" id="searchInput"
                placeholder="Search by guide name, location, or specialty..."
                value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Filters Sidebar -->
        <aside class="filters">
            <h3>Filter Guides</h3>
            <form method="GET" action="" id="filterForm">
                <!-- Preserve search term -->
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">

                <!-- Category Filter -->
                <div class="filter-group">
                    <label>Category</label>
                    <?php 
                    $categories = ['Wildlife Safari', 'Cultural Tours', 'Adventure', 'Culinary', 'Historical', 'Beach & Coastal'];
                    foreach ($categories as $cat): 
                    ?>
                    <div class="checkbox-item">
                        <input type="radio" name="category" value="<?php echo $cat; ?>" 
                               <?php echo $category === $cat ? 'checked' : ''; ?> 
                               onchange="document.getElementById('filterForm').submit()">
                        <span><?php echo $cat; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="checkbox-item">
                        <input type="radio" name="category" value="" 
                               <?php echo empty($category) ? 'checked' : ''; ?> 
                               onchange="document.getElementById('filterForm').submit()">
                        <span>All Categories</span>
                    </div>
                </div>

                <!-- Location Filter -->
                <div class="filter-group">
                    <label>Location</label>
                    <select name="location" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Locations</option>
                        <option value="Nairobi" <?php echo $location === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                        <option value="Mombasa" <?php echo $location === 'Mombasa' ? 'selected' : ''; ?>>Mombasa</option>
                        <option value="Maasai Mara" <?php echo $location === 'Maasai Mara' ? 'selected' : ''; ?>>Maasai Mara</option>
                        <option value="Amboseli" <?php echo $location === 'Amboseli' ? 'selected' : ''; ?>>Amboseli</option>
                        <option value="Nakuru" <?php echo $location === 'Nakuru' ? 'selected' : ''; ?>>Nakuru</option>
                        <option value="Samburu" <?php echo $location === 'Samburu' ? 'selected' : ''; ?>>Samburu</option>
                        <option value="Tsavo" <?php echo $location === 'Tsavo' ? 'selected' : ''; ?>>Tsavo</option>
                        <option value="Mount Kenya" <?php echo $location === 'Mount Kenya' ? 'selected' : ''; ?>>Mount Kenya</option>
                    </select>
                </div>

                <!-- Price Range -->
                <div class="filter-group">
                    <label>Price Range (KES/Day)</label>
                    <div class="price-range">
                        <input type="number" name="min_price" placeholder="Min" min="0"
                            value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>">
                        <span>-</span>
                        <input type="number" name="max_price" placeholder="Max" min="0"
                            value="<?php echo $maxPrice < 999999 ? $maxPrice : ''; ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-small">Apply Price</button>
                    </div>
                </div>

                <!-- Experience -->
                <div class="filter-group">
                    <label>Experience</label>
                    <select name="experience" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Any Experience</option>
                        <option value="1-3" <?php echo $experience === '1-3' ? 'selected' : ''; ?>>1-3 years</option>
                        <option value="3-5" <?php echo $experience === '3-5' ? 'selected' : ''; ?>>3-5 years</option>
                        <option value="5-10" <?php echo $experience === '5-10' ? 'selected' : ''; ?>>5-10 years</option>
                        <option value="10+" <?php echo $experience === '10+' ? 'selected' : ''; ?>>10+ years</option>
                    </select>
                </div>

                <!-- Language -->
                <div class="filter-group">
                    <label>Language</label>
                    <select name="language" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Any Language</option>
                        <option value="English" <?php echo $language === 'English' ? 'selected' : ''; ?>>English</option>
                        <option value="Swahili" <?php echo $language === 'Swahili' ? 'selected' : ''; ?>>Swahili</option>
                        <option value="French" <?php echo $language === 'French' ? 'selected' : ''; ?>>French</option>
                        <option value="German" <?php echo $language === 'German' ? 'selected' : ''; ?>>German</option>
                        <option value="Spanish" <?php echo $language === 'Spanish' ? 'selected' : ''; ?>>Spanish</option>
                    </select>
                </div>

                <!-- Rating -->
                <div class="filter-group">
                    <label>Minimum Rating</label>
                    <select name="min_rating" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Any Rating</option>
                        <option value="4.5" <?php echo $minRating == 4.5 ? 'selected' : ''; ?>>4.5+ Stars</option>
                        <option value="4" <?php echo $minRating == 4 ? 'selected' : ''; ?>>4+ Stars</option>
                        <option value="3" <?php echo $minRating == 3 ? 'selected' : ''; ?>>3+ Stars</option>
                    </select>
                </div>

                <!-- Verified Only -->
                <div class="filter-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="verified" value="1" 
                               <?php echo $verifiedOnly ? 'checked' : ''; ?>
                               onchange="document.getElementById('filterForm').submit()">
                        <span>✓ Verified Guides Only</span>
                    </div>
                </div>
            </form>

            <a href="?" class="clear-filters">Clear All Filters</a>
        </aside>

        <!-- Results Section -->
        <div class="results-section">
            <div class="results-header">
                <div class="results-count">Showing <?php echo $totalGuides; ?> guide<?php echo $totalGuides !== 1 ? 's' : ''; ?></div>
                <div class="sort-by">
                    <label>Sort by:</label>
                    <form method="GET" action="" style="display: inline;" id="sortForm">
                        <!-- Preserve all current filters -->
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                        <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
                        <input type="hidden" name="min_price" value="<?php echo $minPrice; ?>">
                        <input type="hidden" name="max_price" value="<?php echo $maxPrice; ?>">
                        <input type="hidden" name="experience" value="<?php echo htmlspecialchars($experience); ?>">
                        <input type="hidden" name="language" value="<?php echo htmlspecialchars($language); ?>">
                        <input type="hidden" name="min_rating" value="<?php echo $minRating; ?>">
                        <?php if ($verifiedOnly): ?>
                            <input type="hidden" name="verified" value="1">
                        <?php endif; ?>

                        <select name="sort" onchange="document.getElementById('sortForm').submit()">
                            <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            <option value="price-low" <?php echo $sortBy === 'price-low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price-high" <?php echo $sortBy === 'price-high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="experience" <?php echo $sortBy === 'experience' ? 'selected' : ''; ?>>Most Experienced</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Guides Grid -->
            <div class="guides-grid">
                <?php if (empty($guides)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #666;">
                        <h3>No guides found</h3>
                        <p>Try adjusting your filters or search criteria</p>
                        <a href="?" class="btn btn-primary" style="display: inline-block; margin-top: 1rem; text-decoration: none;">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($guides as $guide): 
                        $hasImage = !empty($guide['profile_photo']);
                        $imagePath = $hasImage ? 'uploads/profiles/' . htmlspecialchars($guide['profile_photo']) : '';
                    ?>
                        <div class="guide-card" onclick="window.location.href='guide_profile.php?id=<?php echo $guide['id']; ?>'">
                            <div class="guide-image <?php echo $hasImage ? 'has-image' : 'no-image'; ?>" 
                                 <?php if ($hasImage): ?>style="background-image: url('<?php echo $imagePath; ?>');"<?php endif; ?>>
                                <?php if (!$hasImage): ?>
                                    <span class="initials"><?php echo strtoupper(substr($guide['first_name'], 0, 1) . substr($guide['last_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                                <?php if ($guide['status'] === 'verified'): ?>
                                    <span class="verified-badge">✓ Verified</span>
                                <?php endif; ?>
                            </div>
                            <div class="guide-info">
                                <h3 class="guide-name"><?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?></h3>
                                <p class="guide-specialty"><?php echo htmlspecialchars($guide['categories'] ?: 'Professional Tour Guide'); ?></p>
                                <div class="guide-details">
                                    <div>📍 <?php echo htmlspecialchars($guide['location'] ?: 'Kenya'); ?></div>
                                    <div>🗣️ <?php echo htmlspecialchars($guide['languages'] ?: 'English, Swahili'); ?></div>
                                    <div>🎯 <?php echo $guide['years_experience']; ?> years experience</div>
                                </div>
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
                                <div class="guide-price">KES <?php echo number_format($guide['price_per_day']); ?>/day</div>
                                <button class="view-profile-btn">View Profile</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Enter key search
        document.getElementById('searchInput')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });
    </script>
  <!-- Font Awesome Icons (if not already included) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- AI Assistant Container -->
<div id="ai-assistant">
    <div id="assistant-icon" onclick="toggleAssistant()">
        <i class="fas fa-robot"></i>
        <span class="notification-badge" id="notificationBadge">1</span>
    </div>
    
    <div id="assistant-window">
        <div id="assistant-header">
            <div class="header-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div class="header-text">
                <h4>Safari Guide Assistant</h4>
                <p>Online • Ready to help</p>
            </div>
            <div class="header-actions">
                <i class="fas fa-times" onclick="closeAssistant()"></i>
            </div>
        </div>
        
        <div id="assistant-messages">
            <div class="message bot">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-bubble">
                    <div class="message-text">
                        Hello! 👋 I'm your Safari Guide Assistant.<br><br>
                        I can help you with:
                        • Finding the perfect guide 🦁
                        • Booking tours 📅
                        • Making payments 💰
                        • Leaving reviews ⭐<br><br>
                        What would you like to know?
                    </div>
                    <div class="message-time">Just now</div>
                </div>
            </div>
        </div>
        
        <div id="assistant-input">
            <div class="suggestions" id="suggestions">
                <button onclick="sendQuickReply('find guide')">Find a guide 🔍</button>
                <button onclick="sendQuickReply('book tour')">Book a tour 📅</button>
                <button onclick="sendQuickReply('payment')">Payment help 💰</button>
                <button onclick="sendQuickReply('review')">Leave review ⭐</button>
            </div>
            <div class="input-area">
                <input type="text" id="assistant-input-field" placeholder="Type your question..." onkeypress="handleKeyPress(event)">
                <button id="send-btn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* AI Assistant Styles */
#ai-assistant {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 10000;
    font-family: 'Poppins', sans-serif;
}

/* Icon Button */
#assistant-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #228B22, #1a6b1a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    position: relative;
    animation: pulse 2s infinite;
}

#assistant-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(34, 139, 34, 0.4);
}

#assistant-icon i {
    font-size: 32px;
    color: white;
}

/* Notification Badge */
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    animation: bounce 1s infinite;
}

/* Pulse Animation */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(34, 139, 34, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(34, 139, 34, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(34, 139, 34, 0);
    }
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

/* Assistant Window */
#assistant-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 380px;
    height: 550px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

#assistant-window.open {
    display: flex;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Header */
#assistant-header {
    background: linear-gradient(135deg, #228B22, #1a6b1a);
    color: white;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-icon i {
    font-size: 28px;
}

.header-text {
    flex: 1;
}

.header-text h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.header-text p {
    margin: 0;
    font-size: 12px;
    opacity: 0.8;
}

.header-actions i {
    font-size: 18px;
    cursor: pointer;
    padding: 5px;
    transition: all 0.3s;
}

.header-actions i:hover {
    opacity: 0.7;
    transform: scale(1.1);
}

/* Messages Area */
#assistant-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
}

/* Message Bubbles */
.message {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.user {
    flex-direction: row-reverse;
}

.message-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.message.bot .message-avatar {
    background: linear-gradient(135deg, #228B22, #1a6b1a);
    color: white;
}

.message.user .message-avatar {
    background: #8B4513;
    color: white;
}

.message-bubble {
    max-width: 70%;
    background: white;
    border-radius: 15px;
    padding: 10px 15px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.message.user .message-bubble {
    background: #228B22;
    color: white;
}

.message-text {
    font-size: 14px;
    line-height: 1.5;
}

.message-time {
    font-size: 10px;
    color: #999;
    margin-top: 5px;
}

.message.user .message-time {
    color: rgba(255,255,255,0.7);
}

/* Input Area */
#assistant-input {
    border-top: 1px solid #e0e0e0;
    background: white;
    padding: 15px;
}

.suggestions {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.suggestions button {
    background: #f0f0f0;
    border: none;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Poppins', sans-serif;
}

.suggestions button:hover {
    background: #228B22;
    color: white;
}

.input-area {
    display: flex;
    gap: 10px;
}

#assistant-input-field {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 25px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    outline: none;
    transition: all 0.3s;
}

#assistant-input-field:focus {
    border-color: #228B22;
    box-shadow: 0 0 0 2px rgba(34, 139, 34, 0.1);
}

#send-btn {
    width: 40px;
    height: 40px;
    background: #228B22;
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

#send-btn:hover {
    background: #1a6b1a;
    transform: scale(1.05);
}

/* Responsive */
@media (max-width: 480px) {
    #assistant-window {
        width: 320px;
        height: 500px;
        right: -10px;
    }
    
    .message-bubble {
        max-width: 85%;
    }
}
</style>

<script>
// AI Assistant JavaScript
let isOpen = false;

function toggleAssistant() {
    const window = document.getElementById('assistant-window');
    const badge = document.getElementById('notificationBadge');
    
    if (isOpen) {
        window.classList.remove('open');
        isOpen = false;
    } else {
        window.classList.add('open');
        isOpen = true;
        badge.style.display = 'none';
    }
}

function closeAssistant() {
    document.getElementById('assistant-window').classList.remove('open');
    isOpen = false;
}

function sendMessage() {
    const input = document.getElementById('assistant-input-field');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message
    addMessage(message, 'user');
    input.value = '';
    
    // Simulate typing
    setTimeout(() => {
        const response = getAIResponse(message);
        addMessage(response, 'bot');
    }, 800);
}

function sendQuickReply(reply) {
    document.getElementById('assistant-input-field').value = reply;
    sendMessage();
}

function addMessage(text, sender) {
    const messagesDiv = document.getElementById('assistant-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    if (sender === 'bot') {
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-bubble">
                <div class="message-text">${text}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="message-bubble">
                <div class="message-text">${text}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
    }
    
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function getAIResponse(question) {
    const q = question.toLowerCase();
    
    // Booking related
    if (q.includes('book') || q.includes('booking')) {
        return "📅 To book a guide:<br><br>1. Search for a guide using our search bar<br>2. Click 'Book Now' on their profile<br>3. Select your tour dates and number of people<br>4. Submit your booking request<br>5. Wait for the guide to accept!";
    }
    
    // Price related
    else if (q.includes('price') || q.includes('cost') || q.includes('how much')) {
        return "💰 Guide prices vary from KES 3,000 to KES 15,000 per day. Each guide sets their own price based on experience, location, and specialties. You can filter by price range in our search page!";
    }
    
    // Safari/Wildlife related
    else if (q.includes('safari') || q.includes('wildlife')) {
        return "🦁 We have many wildlife safari guides! Use our search filters and select 'Wildlife Safari' category. You'll find experienced guides who specialize in game drives, bird watching, and wildlife photography!";
    }
    
    // Cultural tours
    else if (q.includes('culture') || q.includes('cultural')) {
        return "🎭 Cultural tours are amazing! Select 'Cultural Tours' in categories to find guides specializing in Maasai culture, local traditions, historical sites, and authentic Kenyan experiences!";
    }
    
    // Payment related
    else if (q.includes('payment') || q.includes('pay') || q.includes('mpesa')) {
        return "💳 After your booking is accepted, you'll see a 'Pay Now' button. Payments can be made via M-Pesa or card. The admin will verify your payment and confirm your booking!";
    }
    
    // Review related
    else if (q.includes('review') || q.includes('rating')) {
        return "⭐ After your tour is completed, go to 'My Bookings' and click 'Write a Review' next to the completed tour. Share your experience to help other travelers!";
    }
    
    // Find guide
    else if (q.includes('find') || q.includes('search')) {
        return "🔍 To find a guide:<br><br>• Use the search bar at the top<br>• Filter by location, price, or category<br>• Browse guide profiles<br>• Check reviews and ratings<br>• Click 'Book Now' when you find your perfect guide!";
    }
    
    // Help/Support
    else if (q.includes('help') || q.includes('support')) {
        return "🆘 I'm here to help! You can ask me about:<br>• Finding guides<br>• Making bookings<br>• Payment options<br>• Leaving reviews<br>• Tour categories<br><br>What would you like to know?";
    }
    
    // Greeting
    else if (q.includes('hello') || q.includes('hi') || q.includes('hey')) {
        return "Hello! 👋 Welcome to SafariGuides. I'm your AI assistant. How can I help you today? You can ask me about booking tours, finding guides, payments, or anything else!";
    }
    
    // Default response
    else {
        return "I'm here to help! You can ask me about:<br><br>• Finding and booking guides 🦁<br>• Tour prices and payments 💰<br>• Leaving reviews ⭐<br>• Tour categories and locations 📍<br><br>What would you like to know?";
    }
}

// Handle Enter key
function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Auto-scroll to bottom when messages update
const observer = new MutationObserver(() => {
    const messagesDiv = document.getElementById('assistant-messages');
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
});
observer.observe(document.getElementById('assistant-messages'), { childList: true, subtree: true });
</script>
</body>

</html>