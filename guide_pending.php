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

// Check if user is logged in and is a pending guide
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'guide') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check user status
$query = "SELECT status FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// If already verified, redirect to dashboard
if ($user['status'] === 'verified') {
    header("Location: guide_dashboard.php");
    exit();
}

// If not pending (maybe suspended), redirect to login
if ($user['status'] !== 'pending') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - KenyaGuides</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .pending-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 3rem;
            text-align: center;
        }

        .pending-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }

        h1 {
            color: #228B22;
            margin-bottom: 1rem;
        }

        p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .status-badge {
            background: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #228B22;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 0.5rem;
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
    </style>
</head>
<body>
    <div class="pending-card">
        <div class="pending-icon">⏳</div>
        <h1>Application Pending</h1>
        <div class="status-badge">Under Review</div>
        <p>Thank you for registering as a tour guide! Your application has been submitted and is currently being reviewed by our admin team.</p>
        <p>This process usually takes <strong>24-48 hours</strong>. You will receive an email notification once your account is verified.</p>
        <p>In the meantime, you can:</p>
        <a href="index.php" class="btn btn-outline">Browse Home</a>
        <a href="logout.php" class="btn">Logout</a>
    </div>
</body>
</html>