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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Check user in database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check user status
            if ($user['role'] === 'guide' && $user['status'] === 'pending') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = 'guide';
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Redirect to pending page
                header("Location: guide_pending.php");
                exit();
            }
            
            // Normal login for verified users
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user['role'] === 'guide') {
                header("Location: guide_dashboard.php");
            } else {
                header("Location: tourist_dashboard.php");
            }
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KenyaGuides</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .login-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c2c2c;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            padding-right: 2.5rem; /* Space for the eye icon */
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #8B4513;
        }

        /* Password toggle container */
        .password-container {
            position: relative;
        }

        .password-container input {
            width: 100%;
            padding: 0.8rem;
            padding-right: 2.5rem;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
            font-size: 1.2rem;
        }

        .password-toggle:hover {
            color: #8B4513;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #ffe7e7;
            border: 1px solid #ffb3b3;
            color: #721c24;
        }

        .alert-success {
            background: #e7ffe7;
            border: 1px solid #b3ffb3;
            color: #1c7224;
        }

        .links {
            text-align: center;
            margin-top: 1rem;
        }

        .links a {
            color: #8B4513;
            text-decoration: none;
            font-weight: 500;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .role-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: #228B22;
            color: white;
            border-radius: 15px;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        /* Optional: Add a little animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .alert-error {
            animation: shake 0.3s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>KenyaGuides</h1>
            <p>Welcome Back!</p>
        </div>

        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right: 5px;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="margin-right: 5px;"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>
                        <i class="fas fa-envelope" style="color: #8B4513; margin-right: 5px;"></i>
                        Email Address
                    </label>
                    <input type="email" name="email" required placeholder="your.email@example.com" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-lock" style="color: #8B4513; margin-right: 5px;"></i>
                        Password
                    </label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" required placeholder="Enter your password">
                        <i class="fas fa-eye password-toggle" id="togglePassword" onclick="togglePassword()"></i>
                    </div>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt" style="margin-right: 5px;"></i>
                    Login
                </button>
            </form>

            <div class="links">
                <p>
                    <i class="fas fa-user-plus" style="color: #8B4513; margin-right: 5px;"></i>
                    Don't have an account? <a href="signup.php">Sign Up</a>
                </p>
                <p style="margin-top: 0.5rem;">
                    <i class="fas fa-home" style="color: #8B4513; margin-right: 5px;"></i>
                    <a href="index.php">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Optional: Add keyboard shortcut (Ctrl+Shift+E) to toggle password visibility
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey && event.shiftKey && event.key === 'E') {
                event.preventDefault();
                togglePassword();
            }
        });

        // Optional: Auto-focus email field on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="email"]').focus();
        });
    </script>
</body>
</html>