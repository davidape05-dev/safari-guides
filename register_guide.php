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
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: application/json');

// ======================================
// AUTHENTICATION CHECK
// ======================================
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to register as a guide.',
        'redirect' => 'login.php'
    ]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ======================================
// REQUEST METHOD CHECK
// ======================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // ======================================
    // COLLECT FORM DATA
    // ======================================
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $license = $_POST['license'] ?? '';
    $experience = (int) ($_POST['experience'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $location = $_POST['location'] ?? '';
    $bio = $_POST['bio'] ?? '';

    // ======================================
    // VALIDATE REQUIRED FIELDS
    // ======================================
    if (empty($license) || empty($experience) || empty($price) || empty($location) || empty($bio)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }

    // ======================================
    // CHECK IF USER ALREADY HAS GUIDE PROFILE
    // ======================================
    $check_stmt = $conn->prepare("SELECT id FROM guide_profiles WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have a guide profile']);
        exit();
    }

    // ======================================
    // CHECK LICENSE UNIQUENESS
    // ======================================
    $license_check = $conn->prepare("SELECT id FROM guide_profiles WHERE license_number = ?");
    $license_check->bind_param("s", $license);
    $license_check->execute();
    $license_result = $license_check->get_result();

    if ($license_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This license number is already registered']);
        exit();
    }

    // ======================================
    // FILE UPLOAD HANDLING
    // ======================================
    $uploadDir = __DIR__ . '/uploads/';
    $profileDir = $uploadDir . 'profiles/';
    $portfolioDir = $uploadDir . 'portfolios/';
    $documentDir = $uploadDir . 'documents/';

    // Create directories if they don't exist
    if (!file_exists($profileDir))
        mkdir($profileDir, 0777, true);
    if (!file_exists($portfolioDir))
        mkdir($portfolioDir, 0777, true);
    if (!file_exists($documentDir))
        mkdir($documentDir, 0777, true);

    $profilePhoto = null;
    $licenseDoc = null;

    // Profile Photo Upload
    if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['profilePhoto']['type'], $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid profile photo format']);
            exit();
        }

        $ext = pathinfo($_FILES['profilePhoto']['name'], PATHINFO_EXTENSION);
        $profilePhoto = uniqid('profile_') . '.' . $ext;
        $profilePath = $profileDir . $profilePhoto;

        if (!move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $profilePath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile photo']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile photo is required']);
        exit();
    }

    // License Document Upload
    if (isset($_FILES['licenseDoc']) && $_FILES['licenseDoc']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($_FILES['licenseDoc']['type'], $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid license document format']);
            exit();
        }

        $ext = pathinfo($_FILES['licenseDoc']['name'], PATHINFO_EXTENSION);
        $licenseDoc = uniqid('license_') . '.' . $ext;
        $licensePath = $documentDir . $licenseDoc;

        if (!move_uploaded_file($_FILES['licenseDoc']['tmp_name'], $licensePath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload license document']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'License document is required']);
        exit();
    }

    // ======================================
    // BEGIN TRANSACTION
    // ======================================
    $conn->begin_transaction();

    // ======================================
    // UPDATE USER ROLE TO GUIDE
    // ======================================
    $update_user = $conn->prepare("UPDATE users SET role = 'guide', status = 'pending' WHERE id = ?");
    $update_user->bind_param("i", $user_id);
    $update_user->execute();

    // ======================================
    // INSERT GUIDE PROFILE
    // ======================================
    $stmt = $conn->prepare("
        INSERT INTO guide_profiles (
            user_id, license_number, years_experience, price_per_day, 
            location, bio, profile_photo, license_document, 
            rating, total_reviews, total_tours, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, NOW())
    ");

    $stmt->bind_param(
        "isidssss",
        $user_id,
        $license,
        $experience,
        $price,
        $location,
        $bio,
        $profilePhoto,
        $licenseDoc
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to create guide profile: ' . $stmt->error);
    }

    $guideId = $conn->insert_id;

    // ======================================
    // INSERT LANGUAGES
    // ======================================
    if (isset($_POST['languages']) && is_array($_POST['languages'])) {
        $lang_stmt = $conn->prepare("INSERT INTO guide_languages (guide_id, language) VALUES (?, ?)");
        foreach ($_POST['languages'] as $language) {
            $lang_stmt->bind_param("is", $guideId, $language);
            $lang_stmt->execute();
        }
        $lang_stmt->close();
    }

    // ======================================
    // INSERT CATEGORIES
    // ======================================
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $cat_stmt = $conn->prepare("INSERT INTO guide_categories (guide_id, category) VALUES (?, ?)");
        foreach ($_POST['categories'] as $category) {
            $cat_stmt->bind_param("is", $guideId, $category);
            $cat_stmt->execute();
        }
        $cat_stmt->close();
    }

    // ======================================
    // HANDLE PORTFOLIO IMAGES
    // ======================================
    if (isset($_FILES['portfolioImages']) && is_array($_FILES['portfolioImages']['name'])) {
        $portfolio_stmt = $conn->prepare("INSERT INTO portfolio_images (guide_id, image_path, uploaded_at) VALUES (?, ?, NOW())");

        foreach ($_FILES['portfolioImages']['name'] as $key => $name) {
            if ($_FILES['portfolioImages']['error'][$key] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (in_array($_FILES['portfolioImages']['type'][$key], $allowed)) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $portfolioName = uniqid('portfolio_') . '_' . $key . '.' . $ext;
                    $portfolioPath = $portfolioDir . $portfolioName;

                    if (move_uploaded_file($_FILES['portfolioImages']['tmp_name'][$key], $portfolioPath)) {
                        $dbPath = 'uploads/portfolios/' . $portfolioName;
                        $portfolio_stmt->bind_param("is", $guideId, $dbPath);
                        $portfolio_stmt->execute();
                    }
                }
            }
        }
        $portfolio_stmt->close();
    }

    // ======================================
    // COMMIT TRANSACTION
    // ======================================
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Guide registration successful! Your profile is pending verification.',
        'guideId' => $guideId,
        'userId' => $user_id
    ]);

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }

    // Clean up uploaded files on error
    if (isset($profilePath) && file_exists($profilePath))
        unlink($profilePath);
    if (isset($licensePath) && file_exists($licensePath))
        unlink($licensePath);

    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>