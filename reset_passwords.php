<?php
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define users and their new passwords
$users = [
    ['email' => 'admin@safariguide.com', 'password' => 'admin123', 'role' => 'admin'],
    ['email' => 'guide@safariguide.com', 'password' => 'guide123', 'role' => 'guide'],
    ['email' => 'tourist@safariguide.com', 'password' => 'tourist123', 'role' => 'tourist']
];

echo "<h2>Resetting Passwords</h2>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Email</th><th>New Password</th><th>Status</th></tr>";

foreach ($users as $user) {
    $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = ?");
    $stmt->bind_param("sss", $hashed_password, $user['email'], $user['role']);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo "<tr>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['password'] . "</td>";
        echo "<td style='color: green;'>✅ Updated successfully</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['password'] . "</td>";
        echo "<td style='color: red;'>❌ User not found or update failed</td>";
        echo "</tr>";
    }
}

echo "</table>";

// Show all users
$result = $conn->query("SELECT id, email, role, status FROM users");
echo "<h2>Current Users in Database</h2>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Status</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['email'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>