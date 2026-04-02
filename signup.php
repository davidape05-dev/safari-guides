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
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'tourist';

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email already registered';
        } else {
            // Hash password and insert user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

           if ($role === 'guide') {
    // Insert guide with 'pending' status
    $stmt = $conn->prepare("
        INSERT INTO users (
            first_name, 
            last_name, 
            email, 
            phone, 
            password, 
            role, 
            status, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $role_value = 'guide';
    $stmt->bind_param("ssssss", 
        $firstName, 
        $lastName, 
        $email, 
        $phone, 
        $hashedPassword, 
        $role_value
    );
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        $_SESSION['temp_user'] = [
            'user_id' => $user_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone
        ];
        
        header("Location: guide_profilecreation.php");
        exit();
    }
}

                // Register as tourist
                $stmt = $conn->prepare("
        INSERT INTO users (
            first_name, 
            last_name, 
            email, 
            phone, 
            password, 
            role, 
            status, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

                $role_value = 'tourist';
                $status_value = 'active';
                $stmt->bind_param(
                    "sssssss",
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $hashedPassword,
                    $role_value,
                    $status_value
                );

                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Registration successful! Please login.';
                    header("Location: login.php");
                    exit();
                } else {
                    $error = 'Registration failed: ' . $conn->error;
                }
            }
        }
    }

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SafariGuide</title>
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
            background: #8B4513;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 2rem auto;
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
            max-height: 70vh;
            overflow-y: auto;
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

        .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8B4513;
        }

        /* Password container for toggle icon */
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .role-option {
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-option:hover {
            border-color: #8B4513;
            background: #f9f9f9;
        }

        .role-option input[type="radio"]:checked+label {
            color: #8B4513;
            font-weight: 600;
        }

        .role-option input[type="radio"]:checked~.role-icon {
            transform: scale(1.2);
        }

        input[type="radio"]:checked+label+.role-option {
            border-color: #8B4513;
            background: #fff5e6;
        }

        .role-card {
            padding: 1.5rem;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }

        .role-card:hover {
            border-color: #8B4513;
            background: #fff5e6;
            transform: translateY(-5px);
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .role-card input[type="radio"]:checked~.role-content {
            color: #8B4513;
        }

        .role-card input[type="radio"]:checked~* {
            border-color: #8B4513;
        }

        input[type="radio"]:checked+.role-card {
            border-color: #8B4513;
            background: #fff5e6;
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.2);
        }

        .role-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            transition: transform 0.3s;
        }

        .role-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #2c2c2c;
            margin-bottom: 0.3rem;
        }

        .role-desc {
            font-size: 0.85rem;
            color: #666;
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
            transition: transform 0.3s;
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

        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }

        .strength-weak {
            background: #dc3545;
            width: 33%;
        }

        .strength-medium {
            background: #ffc107;
            width: 66%;
        }

        .strength-strong {
            background: #28a745;
            width: 100%;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Animation for error message */
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
            <h1>🦁 SafariGuide</h1>
            <p>Create Your Account</p>
        </div>

        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right: 5px;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="margin-right: 5px;"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- Role Selection -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-user-tag" style="color: #8B4513; margin-right: 5px;"></i>
                        I want to <span class="required">*</span>
                    </label>
                    <div class="role-selection">
                        <label>
                            <input type="radio" name="role" value="tourist" checked
                                onchange="updateRoleSelection(this)">
                            <div class="role-card">
                                <div class="role-icon">🧳</div>
                                <div class="role-content">
                                    <div class="role-title">Find a Guide</div>
                                    <div class="role-desc">I'm a tourist</div>
                                </div>
                            </div>
                        </label>

                        <label>
                            <input type="radio" name="role" value="guide" onchange="updateRoleSelection(this)">
                            <div class="role-card">
                                <div class="role-icon">🦁</div>
                                <div class="role-content">
                                    <div class="role-title">Become a Guide</div>
                                    <div class="role-desc">I'm a tour guide</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Name Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-user" style="color: #8B4513; margin-right: 5px;"></i>
                            First Name <span class="required">*</span>
                        </label>
                        <input type="text" name="first_name" required placeholder="John"
                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>
                            <i class="fas fa-user" style="color: #8B4513; margin-right: 5px;"></i>
                            Last Name <span class="required">*</span>
                        </label>
                        <input type="text" name="last_name" required placeholder="Doe"
                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-envelope" style="color: #8B4513; margin-right: 5px;"></i>
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" name="email" required placeholder="your.email@example.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-phone" style="color: #8B4513; margin-right: 5px;"></i>
                        Phone Number
                    </label>
                    <input type="tel" name="phone" placeholder="+254 700 123 456"
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-lock" style="color: #8B4513; margin-right: 5px;"></i>
                        Password <span class="required">*</span>
                    </label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" required placeholder="Minimum 8 characters"
                            onkeyup="checkPasswordStrength()">
                        <i class="fas fa-eye password-toggle" id="togglePassword" onclick="togglePassword('password', 'togglePassword')"></i>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-lock" style="color: #8B4513; margin-right: 5px;"></i>
                        Confirm Password <span class="required">*</span>
                    </label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Re-enter password">
                        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword" onclick="togglePassword('confirm_password', 'toggleConfirmPassword')"></i>
                    </div>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-user-plus" style="margin-right: 5px;"></i>
                    Create Account
                </button>
            </form>

            <div class="links">
                <p>
                    <i class="fas fa-sign-in-alt" style="color: #8B4513; margin-right: 5px;"></i>
                    Already have an account? <a href="login.php">Login</a>
                </p>
                <p style="margin-top: 0.5rem;">
                    <i class="fas fa-home" style="color: #8B4513; margin-right: 5px;"></i>
                    <a href="index.php">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId, toggleId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(toggleId);
            
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

        // Update role card selection visual
        function updateRoleSelection(radio) {
            // Remove selected class from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.style.borderColor = '#e0e0e0';
                card.style.background = 'white';
                card.style.boxShadow = 'none';
            });

            // Add selected class to chosen card
            const selectedCard = radio.nextElementSibling;
            selectedCard.style.borderColor = '#8B4513';
            selectedCard.style.background = '#fff5e6';
            selectedCard.style.boxShadow = '0 5px 15px rgba(139, 69, 19, 0.2)';
        }

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');

            strengthBar.className = 'password-strength-bar';

            if (password.length === 0) {
                strengthBar.style.width = '0';
            } else if (password.length < 6) {
                strengthBar.classList.add('strength-weak');
            } else if (password.length < 10) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        // Initialize selected role on page load
        document.addEventListener('DOMContentLoaded', function () {
            const checkedRadio = document.querySelector('input[name="role"]:checked');
            if (checkedRadio) {
                updateRoleSelection(checkedRadio);
            }
            
            // Auto-focus first name field
            document.querySelector('input[name="first_name"]').focus();
        });

        // Optional: Add keyboard shortcut (Ctrl+Shift+P) to toggle both passwords
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey && event.shiftKey && event.key === 'P') {
                event.preventDefault();
                togglePassword('password', 'togglePassword');
                togglePassword('confirm_password', 'toggleConfirmPassword');
            }
        });
    </script>
</body>

</html>