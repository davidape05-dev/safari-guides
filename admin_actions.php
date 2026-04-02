<?php
session_start();
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';
$guide_id = $_POST['guide_id'] ?? 0;

switch ($action) {
    case 'approve':
        $stmt = $conn->prepare("UPDATE users SET status = 'verified' WHERE id = ? AND role = 'guide'");
        $stmt->bind_param("i", $guide_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Guide approved successfully']);
        break;

    case 'reject':
        $stmt = $conn->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'guide'");
        $stmt->bind_param("i", $guide_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Guide rejected']);
        break;

    case 'suspend':
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'guide'");
        $stmt->bind_param("i", $guide_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Guide suspended']);
        break;

    case 'activate':
        $stmt = $conn->prepare("UPDATE users SET status = 'verified' WHERE id = ? AND role = 'guide'");
        $stmt->bind_param("i", $guide_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Guide activated']);
        break;

    case 'verify_payment':
        $payment_id = $_POST['payment_id'] ?? 0;
        $status = $_POST['status'] ?? '';

        $stmt = $conn->prepare("UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $payment_id);
        $stmt->execute();

        if ($status === 'completed') {
            // Get booking details
            $payment_info = $conn->query("SELECT booking_id, guide_id FROM payments WHERE id = $payment_id")->fetch_assoc();
            $conn->query("UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = {$payment_info['booking_id']}");
        }

        echo json_encode(['success' => true, 'message' => 'Payment ' . $status]);
        break;
    case 'delete_user':
        $user_id = $_POST['user_id'] ?? 0;

        // Check if user exists and is not admin
        $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $user = $check->get_result()->fetch_assoc();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            break;
        }

        if ($user['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete admin user']);
            break;
        }

        // Delete user (this will cascade to related tables due to foreign keys)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $conn->error]);
        }
        break;

    case 'process_payout':
        $payout_id = $_POST['payout_id'] ?? 0;
        $status = $_POST['status'] ?? '';

        $stmt = $conn->prepare("UPDATE payout_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $status, $user_id, $payout_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Payout ' . $status]);
        break;

    case 'get_user_details':
        $user_id = $_POST['user_id'] ?? 0;

        $stmt = $conn->prepare("
        SELECT u.*,
               gp.id as guide_profile_id,
               gp.license_number,
               gp.price_per_day,
               gp.location as guide_location,
               gp.years_experience,
               gp.rating,
               gp.total_reviews,
               gp.total_tours,
               gp.bio as guide_bio
        FROM users u
        LEFT JOIN guide_profiles gp ON u.id = gp.user_id
        WHERE u.id = ?
    ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $response = ['success' => true, 'user' => $user];
            if ($user['guide_profile_id']) {
                $response['user']['guide_profile'] = [
                    'license_number' => $user['license_number'],
                    'price_per_day' => $user['price_per_day'],
                    'location' => $user['guide_location'],
                    'years_experience' => $user['years_experience'],
                    'rating' => $user['rating'],
                    'total_reviews' => $user['total_reviews'],
                    'total_tours' => $user['total_tours'],
                    'bio' => $user['guide_bio']
                ];
            }
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;

    case 'get_user_bookings':
        $user_id = $_POST['user_id'] ?? 0;

        $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(total_price) as total_spent
        FROM bookings
        WHERE tourist_id = ?
    ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();

        echo json_encode([
            'success' => true,
            'total_bookings' => $stats['total_bookings'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'pending' => $stats['pending'] ?? 0,
            'confirmed' => $stats['confirmed'] ?? 0,
            'cancelled' => $stats['cancelled'] ?? 0,
            'total_spent' => $stats['total_spent'] ?? 0
        ]);
        break;

    case 'get_details':
        $stmt = $conn->prepare("
            SELECT 
                u.*,
                gp.*,
                GROUP_CONCAT(DISTINCT gc.category) as categories,
                GROUP_CONCAT(DISTINCT gl.language) as languages
            FROM users u
            INNER JOIN guide_profiles gp ON u.id = gp.user_id
            LEFT JOIN guide_categories gc ON gp.id = gc.guide_id
            LEFT JOIN guide_languages gl ON gp.id = gl.guide_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->bind_param("i", $guide_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $guide = $result->fetch_assoc();

        echo json_encode(['success' => true, 'guide' => $guide]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>