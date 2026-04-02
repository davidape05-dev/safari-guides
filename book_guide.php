<?php
session_start();
$host = "localhost";
$port = "3306";
$dbname = "safariguides";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed");
}

$guide_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch guide details
$query = "SELECT u.first_name, u.last_name, u.email, gp.* 
          FROM users u 
          JOIN guide_profiles gp ON u.id = gp.user_id 
          WHERE u.id = ? AND u.role = 'guide'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $guide_id);
$stmt->execute();
$result = $stmt->get_result();
$guide = $result->fetch_assoc();

if (!$guide) {
    header("Location: find_guide.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?></title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        
        .booking-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: #228B22;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .content {
            padding: 2rem;
        }
        
        .guide-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .guide-avatar {
            width: 80px;
            height: 80px;
            background: #228B22;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .guide-details h3 {
            color: #2c2c2c;
            margin-bottom: 0.5rem;
        }
        
        .guide-details p {
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .price-tag {
            font-size: 1.5rem;
            color: #228B22;
            font-weight: 700;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c2c2c;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #228B22;
        }
        
        .form-group input.error {
            border-color: #dc3545;
        }
        
        .price-calculator {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .price-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .price-item {
            padding: 1rem;
        }
        
        .price-item .label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .price-item .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #228B22;
        }
        
        .total-price {
            text-align: center;
            padding-top: 1rem;
            border-top: 2px dashed #228B22;
        }
        
        .total-price .label {
            font-size: 1.1rem;
            color: #666;
        }
        
        .total-price .amount {
            font-size: 2rem;
            font-weight: 700;
            color: #228B22;
            margin-left: 1rem;
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: #228B22;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-submit:hover {
            background: #1a6b1a;
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: white;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .validation-message {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="booking-card">
            <div class="header">
                <h1>Book Your Safari Adventure</h1>
                <p>Complete the form below to request a booking</p>
            </div>
            
            <div class="content">
                <div class="guide-summary">
                    <div class="guide-avatar">
                        <?php echo strtoupper(substr($guide['first_name'], 0, 1) . substr($guide['last_name'], 0, 1)); ?>
                    </div>
                    <div class="guide-details">
                        <h3><?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?></h3>
                        <p>📍 <?php echo htmlspecialchars($guide['location'] ?? 'Kenya'); ?></p>
                        <p>⭐ <?php echo number_format($guide['rating'] ?? 0, 1); ?> (<?php echo $guide['total_reviews'] ?? 0; ?> reviews)</p>
                        <p class="price-tag">KES <?php echo number_format($guide['price_per_day']); ?>/day per person</p>
                    </div>
                </div>
                
                <form action="process_booking.php" method="POST" id="bookingForm">
                    <input type="hidden" name="guide_id" value="<?php echo $guide_id; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="tourist_name" id="tourist_name" required 
                                   value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>"
                                   placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" id="email" required 
                                   value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>"
                                   placeholder="Enter your email">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" id="phone" 
                               value="<?php echo isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : ''; ?>"
                               placeholder="Enter your phone number (optional)">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" name="start_date" id="start_date" required 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>End Date *</label>
                            <input type="date" name="end_date" id="end_date" required 
                                   min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>"
                                   value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Number of People *</label>
                        <input type="number" name="num_people" id="num_people" required 
                               min="1" max="20" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Special Requests</label>
                        <textarea name="special_requests" id="special_requests" rows="4" 
                                  placeholder="Any special requirements? (dietary needs, accessibility, specific activities, etc.)"></textarea>
                    </div>
                    
                    <!-- Live Price Calculator -->
                    <div class="price-calculator">
                        <h3 style="margin-bottom: 1rem;">Price Breakdown</h3>
                        <div class="price-breakdown">
                            <div class="price-item">
                                <div class="label">Duration</div>
                                <div class="value" id="displayDuration">1 day</div>
                            </div>
                            <div class="price-item">
                                <div class="label">People</div>
                                <div class="value" id="displayPeople">1</div>
                            </div>
                            <div class="price-item">
                                <div class="label">Price/Day</div>
                                <div class="value">KES <?php echo number_format($guide['price_per_day']); ?></div>
                            </div>
                        </div>
                        
                        <div class="total-price">
                            <span class="label">Total Estimated Price:</span>
                            <span class="amount" id="totalPrice">KES <?php echo number_format($guide['price_per_day']); ?></span>
                        </div>
                        <p style="color: #666; font-size: 0.85rem; margin-top: 0.5rem; text-align: center;">
                            *Final price may vary based on actual tour duration and services
                        </p>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">Send Booking Request</button>
                </form>
            </div>
        </div>
        
        <a href="guide_profile.php?id=<?php echo $guide_id; ?>" class="back-link">← Back to Guide Profile</a>
    </div>
    
    <script>
        const pricePerDay = <?php echo $guide['price_per_day']; ?>;
        
        function calculatePrice() {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            const numPeople = parseInt(document.getElementById('num_people').value) || 1;
            
            if (startDate && endDate && endDate > startDate) {
                const diffTime = Math.abs(endDate - startDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                
                document.getElementById('displayDuration').textContent = diffDays + ' days';
                document.getElementById('displayPeople').textContent = numPeople;
                
                const total = diffDays * numPeople * pricePerDay;
                document.getElementById('totalPrice').textContent = 'KES ' + total.toLocaleString();
            }
        }
        
        function validateForm(event) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            const email = document.getElementById('email').value;
            const name = document.getElementById('tourist_name').value;
            
            // Clear previous errors
            document.querySelectorAll('.validation-message').forEach(el => el.remove());
            
            let isValid = true;
            
            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('email', 'Please enter a valid email address');
                isValid = false;
            }
            
            // Validate name
            if (name.trim().length < 2) {
                showError('tourist_name', 'Please enter your full name');
                isValid = false;
            }
            
            // Validate dates
            if (endDate <= startDate) {
                showError('end_date', 'End date must be after start date');
                isValid = false;
            }
            
            if (startDate < new Date().setHours(0,0,0,0)) {
                showError('start_date', 'Start date cannot be in the past');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
            
            return isValid;
        }
        
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            field.classList.add('error');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'validation-message';
            errorDiv.textContent = message;
            
            field.parentNode.appendChild(errorDiv);
            
            setTimeout(() => {
                field.classList.remove('error');
                errorDiv.remove();
            }, 3000);
        }
        
        // Add event listeners
        document.getElementById('start_date').addEventListener('change', calculatePrice);
        document.getElementById('end_date').addEventListener('change', calculatePrice);
        document.getElementById('num_people').addEventListener('input', calculatePrice);
        document.getElementById('bookingForm').addEventListener('submit', validateForm);
        
        // Initial calculation
        calculatePrice();
    </script>
</body>
</html>