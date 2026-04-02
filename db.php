<?php
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";

// PDO Connection (keep your existing code)
try {
  $pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
    $username,
    $password,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
  );
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}

// Add MySQLi Connection (for guide_dashboard.php)
$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("MySQLi Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>