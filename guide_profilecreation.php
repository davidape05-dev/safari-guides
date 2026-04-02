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

// Check if user has temp session (just registered) OR is logged in as pending guide
if (!isset($_SESSION['temp_user']) && !isset($_SESSION['user_id'])) {
    header("Location: signup.php");
    exit();
}

// Get user data
if (isset($_SESSION['temp_user'])) {
    // New registration from signup
    $user_id = $_SESSION['temp_user']['user_id'];
    $first_name = $_SESSION['temp_user']['first_name'];
    $last_name = $_SESSION['temp_user']['last_name'];
    $email = $_SESSION['temp_user']['email'];
    $phone = $_SESSION['temp_user']['phone'];
} else {
    // Already logged in but profile incomplete
    $user_id = $_SESSION['user_id'];
    
    // Fetch user data
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        header("Location: logout.php");
        exit();
    }
    
    $first_name = $user['first_name'];
    $last_name = $user['last_name'];
    $email = $user['email'];
    $phone = $user['phone'];
    
    // Check if profile already exists
    $check_profile = $conn->prepare("SELECT id FROM guide_profiles WHERE user_id = ?");
    $check_profile->bind_param("i", $user_id);
    $check_profile->execute();
    if ($check_profile->get_result()->num_rows > 0) {
        // Profile exists, redirect to pending page
        header("Location: guide_pending.php");
        exit();
    }
}

// Handle form submission (this will be called by AJAX from your JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Get form data
        $license = $_POST['license'] ?? '';
        $experience = intval($_POST['experience'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $location = $_POST['location'] ?? '';
        $bio = $_POST['bio'] ?? '';
        
        // Handle languages (array)
        $languages = isset($_POST['languages']) ? $_POST['languages'] : [];
        
        // Handle categories (array)
        $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
        
        // Validate required fields
        if (empty($license) || empty($experience) || empty($price) || empty($location) || empty($bio)) {
            throw new Exception("All required fields must be filled");
        }
        
        if (empty($languages)) {
            throw new Exception("Please select at least one language");
        }
        
        if (empty($categories)) {
            throw new Exception("Please select at least one specialization category");
        }
        
        // Handle file uploads
        $upload_dir = "uploads/";
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Create subdirectories
        $profile_dir = $upload_dir . "profiles/";
        $portfolio_dir = $upload_dir . "portfolios/";
        $docs_dir = $upload_dir . "documents/";
        
        if (!file_exists($profile_dir)) mkdir($profile_dir, 0777, true);
        if (!file_exists($portfolio_dir)) mkdir($portfolio_dir, 0777, true);
        if (!file_exists($docs_dir)) mkdir($docs_dir, 0777, true);
        
        // Handle profile photo
        $profile_photo = '';
        if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profilePhoto']['tmp_name'];
            $file_ext = pathinfo($_FILES['profilePhoto']['name'], PATHINFO_EXTENSION);
            $file_name = "profile_" . uniqid() . "." . $file_ext;
            $file_path = $profile_dir . $file_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $profile_photo = $file_name;
            } else {
                throw new Exception("Failed to upload profile photo");
            }
        } else {
            throw new Exception("Profile photo is required");
        }
        
        // Handle license document
        $license_doc = '';
        if (isset($_FILES['licenseDoc']) && $_FILES['licenseDoc']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['licenseDoc']['tmp_name'];
            $file_ext = pathinfo($_FILES['licenseDoc']['name'], PATHINFO_EXTENSION);
            $file_name = "license_" . uniqid() . "." . $file_ext;
            $file_path = $docs_dir . $file_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $license_doc = $file_name;
            } else {
                throw new Exception("Failed to upload license document");
            }
        } else {
            throw new Exception("License document is required");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Insert guide profile
        $stmt = $conn->prepare("
            INSERT INTO guide_profiles (
                user_id, license_number, years_experience, price_per_day, 
                location, bio, profile_photo, license_document, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param("isidssss", 
            $user_id, $license, $experience, $price, 
            $location, $bio, $profile_photo, $license_doc
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create guide profile: " . $stmt->error);
        }
        
        $guide_profile_id = $conn->insert_id;
        
        // Insert languages
        if (!empty($languages)) {
            $lang_stmt = $conn->prepare("INSERT INTO guide_languages (guide_id, language) VALUES (?, ?)");
            foreach ($languages as $language) {
                $lang_stmt->bind_param("is", $guide_profile_id, $language);
                if (!$lang_stmt->execute()) {
                    throw new Exception("Failed to add language: " . $language);
                }
            }
        }
        
        // Insert categories
        if (!empty($categories)) {
            $cat_stmt = $conn->prepare("INSERT INTO guide_categories (guide_id, category) VALUES (?, ?)");
            foreach ($categories as $category) {
                $cat_stmt->bind_param("is", $guide_profile_id, $category);
                if (!$cat_stmt->execute()) {
                    throw new Exception("Failed to add category: " . $category);
                }
            }
        }
        
        // Handle portfolio images
        if (isset($_FILES['portfolioImages'])) {
            $portfolio_files = $_FILES['portfolioImages'];
            $file_count = count($portfolio_files['name']);
            
            if ($file_count > 0 && $portfolio_files['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $port_stmt = $conn->prepare("INSERT INTO portfolio_images (guide_id, image_path) VALUES (?, ?)");
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($portfolio_files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp = $portfolio_files['tmp_name'][$i];
                        $file_ext = pathinfo($portfolio_files['name'][$i], PATHINFO_EXTENSION);
                        $file_name = "portfolio_" . uniqid() . "_" . $i . "." . $file_ext;
                        $file_path = $portfolio_dir . $file_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $port_stmt->bind_param("is", $guide_profile_id, $file_path);
                            $port_stmt->execute();
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set session for logged in user
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_role'] = 'guide';
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        
        // Clear temp session if exists
        if (isset($_SESSION['temp_user'])) {
            unset($_SESSION['temp_user']);
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Profile created successfully!'
        ]);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit();
    }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Become a Tour Guide - SafariGuide</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <style>
    /* Copy all your existing CSS styles here */
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
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }

    .header {
      background: linear-gradient(135deg, #8B4513 0%, #228B22 100%);
      color: white;
      padding: 2rem;
      text-align: center;
    }

    .header h1 {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }

    /* Progress Bar */
    .progress-container {
      padding: 2rem 2rem 1rem;
    }

    .progress-steps {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin-bottom: 2rem;
    }

    .progress-line {
      position: absolute;
      top: 25px;
      left: 0;
      height: 4px;
      background: #e0e0e0;
      width: 100%;
      z-index: 1;
    }

    #progressLine {
      background: #228B22;
      width: 0%;
      transition: width 0.3s;
    }

    .step {
      text-align: center;
      position: relative;
      z-index: 2;
      flex: 1;
    }

    .step-circle {
      width: 50px;
      height: 50px;
      background: #e0e0e0;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 0.5rem;
      font-weight: 600;
      color: #666;
      transition: all 0.3s;
    }

    .step.active .step-circle {
      background: #228B22;
      color: white;
    }

    .step.completed .step-circle {
      background: #228B22;
      color: white;
    }

    .step-label {
      font-size: 0.85rem;
      color: #666;
    }

    .step.active .step-label {
      color: #228B22;
      font-weight: 600;
    }

    /* Form */
    .form-container {
      padding: 0 2rem 2rem;
    }

    .form-step {
      display: none;
    }

    .form-step.active {
      display: block;
    }

    .form-title {
      color: #8B4513;
      margin-bottom: 0.5rem;
    }

    .form-description {
      color: #666;
      margin-bottom: 2rem;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
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

    .required {
      color: #dc3545;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.8rem;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #228B22;
    }

    .form-group textarea {
      min-height: 120px;
      resize: vertical;
    }

    /* Checkbox Group */
    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 0.5rem;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .checkbox-item input[type="checkbox"] {
      width: auto;
    }

    /* File Upload */
    .file-upload {
      border: 3px dashed #e0e0e0;
      padding: 2rem;
      text-align: center;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .file-upload:hover {
      border-color: #228B22;
      background: #f5f5f5;
    }

    .file-upload input {
      display: none;
    }

    .upload-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
    }

    .preview-area {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-top: 1rem;
    }

    .preview-item {
      position: relative;
      width: 100px;
      height: 100px;
      border-radius: 8px;
      overflow: hidden;
    }

    .preview-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .remove-btn {
      position: absolute;
      top: 5px;
      right: 5px;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 50%;
      width: 25px;
      height: 25px;
      cursor: pointer;
    }

    /* Alerts */
    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    .alert-info {
      background: #d1ecf1;
      color: #0c5460;
      border-left: 4px solid #17a2b8;
    }

    /* Navigation Buttons */
    .form-navigation {
      display: flex;
      justify-content: space-between;
      margin-top: 2rem;
    }

    .btn {
      padding: 0.8rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-back {
      background: #6c757d;
      color: white;
    }

    .btn-next {
      background: #228B22;
      color: white;
      margin-left: auto;
    }

    .btn-submit {
      background: #28a745;
      color: white;
      width: 100%;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    /* Success Message */
    .success-message {
      display: none;
      text-align: center;
      padding: 3rem;
    }

    .success-message.active {
      display: block;
    }

    .success-icon {
      width: 80px;
      height: 80px;
      background: #28a745;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.5rem;
      margin: 0 auto 1.5rem;
    }

    /* Responsive */
    @media (max-width: 600px) {
      .form-row {
        grid-template-columns: 1fr;
      }

      .checkbox-group {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <h1>Become a Tour Guide</h1>
      <p>Complete your profile to join our community of verified professional tour guides</p>
    </div>

    <!-- Progress Bar -->
    <div class="progress-container">
      <div class="progress-steps">
        <div class="progress-line" id="progressLine"></div>
        <div class="step active" data-step="1">
          <div class="step-circle">1</div>
          <div class="step-label">Basic Info</div>
        </div>
        <div class="step" data-step="2">
          <div class="step-circle">2</div>
          <div class="step-label">Professional Details</div>
        </div>
        <div class="step" data-step="3">
          <div class="step-circle">3</div>
          <div class="step-label">Profile & Portfolio</div>
        </div>
        <div class="step" data-step="4">
          <div class="step-circle">4</div>
          <div class="step-label">Review & Submit</div>
        </div>
      </div>
    </div>

    <!-- Form -->
    <form id="registrationForm" class="form-container" enctype="multipart/form-data">
      <!-- Step 1: Basic Information (Pre-filled from session) -->
      <div class="form-step active" data-step="1">
        <h2 class="form-title">Basic Information</h2>
        <p class="form-description">Review your personal details</p>

        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" name="firstName" value="<?php echo htmlspecialchars($first_name); ?>" readonly />
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="lastName" value="<?php echo htmlspecialchars($last_name); ?>" readonly />
          </div>
        </div>

        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly />
        </div>

        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" readonly />
        </div>

        <div class="alert alert-info">
          <strong>📧 Your account has been created.</strong> Now let's complete your guide profile.
        </div>
      </div>

      <!-- Step 2: Professional Details -->
      <div class="form-step" data-step="2">
        <h2 class="form-title">Professional Details</h2>
        <p class="form-description">
          Tell us about your professional background
        </p>

        <div class="form-group">
          <label>KPSGA License Number <span class="required">*</span></label>
          <input type="text" name="license" required placeholder="e.g., KPSGA/2024/001234" />
          <small style="color: #666">You must be licensed by the Kenya Professional Safari Guides
            Association</small>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Years of Experience <span class="required">*</span></label>
            <input type="number" name="experience" required min="0" placeholder="e.g., 5" />
          </div>
          <div class="form-group">
            <label>Price Per Day (KES) <span class="required">*</span></label>
            <input type="number" name="price" required min="0" placeholder="e.g., 8000" />
          </div>
        </div>

        <div class="form-group">
          <label>Languages Spoken <span class="required">*</span></label>
          <div class="checkbox-group">
            <div class="checkbox-item">
              <input type="checkbox" name="languages[]" value="English" id="lang1" />
              <label for="lang1">English</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="languages[]" value="Swahili" id="lang2" />
              <label for="lang2">Swahili</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="languages[]" value="French" id="lang3" />
              <label for="lang3">French</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="languages[]" value="German" id="lang4" />
              <label for="lang4">German</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="languages[]" value="Spanish" id="lang5" />
              <label for="lang5">Spanish</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="languages[]" value="Italian" id="lang6" />
              <label for="lang6">Italian</label>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Specialization Categories <span class="required">*</span></label>
          <div class="checkbox-group">
            <div class="checkbox-item">
              <input type="checkbox" name="categories[]" value="Wildlife Safari" id="cat1" />
              <label for="cat1">🦁 Wildlife Safari</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="categories[]" value="Cultural Tours" id="cat2" />
              <label for="cat2">🎭 Cultural Tours</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="categories[]" value="Adventure" id="cat3" />
              <label for="cat3">🏔️ Adventure & Hiking</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="categories[]" value="Culinary" id="cat4" />
              <label for="cat4">🍽️ Culinary Tours</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="categories[]" value="Historical" id="cat5" />
              <label for="cat5">🏛️ Historical Tours</label>
            </div>
            <div class="checkbox-item">
              <input type="checkbox" name="categories[]" value="Beach & Coastal" id="cat6" />
              <label for="cat6">🏖️ Beach & Coastal</label>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Primary Location/Region <span class="required">*</span></label>
          <select name="location" required>
            <option value="">Select your primary location</option>
            <option value="Nairobi">Nairobi</option>
            <option value="Mombasa">Mombasa</option>
            <option value="Maasai Mara">Maasai Mara</option>
            <option value="Amboseli">Amboseli</option>
            <option value="Nakuru">Nakuru</option>
            <option value="Samburu">Samburu</option>
            <option value="Tsavo">Tsavo</option>
            <option value="Mount Kenya">Mount Kenya</option>
            <option value="Lamu">Lamu</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>

      <!-- Step 3: Profile & Portfolio -->
      <div class="form-step" data-step="3">
        <h2 class="form-title">Profile & Portfolio</h2>
        <p class="form-description">Showcase your expertise and experience</p>

        <div class="form-group">
          <label>Profile Photo <span class="required">*</span></label>
          <div class="file-upload" onclick="document.getElementById('profilePhoto').click()">
            <input type="file" id="profilePhoto" name="profilePhoto" accept="image/*" required
              onchange="previewProfilePhoto(event)" />
            <div class="upload-icon">📸</div>
            <p><strong>Click to upload your profile photo</strong></p>
            <small>JPG, PNG or WEBP (Max 5MB)</small>
          </div>
          <div id="profilePreview" class="preview-area"></div>
        </div>

        <div class="form-group">
          <label>About Me / Bio <span class="required">*</span></label>
          <textarea name="bio" required
            placeholder="Tell tourists about yourself, your passion for guiding, your expertise, and what makes your tours special..."></textarea>
          <small style="color: #666">Write a compelling description (200-500 words recommended)</small>
        </div>

        <div class="form-group">
          <label>Portfolio Images (Optional)</label>
          <div class="file-upload" onclick="document.getElementById('portfolioImages').click()">
            <input type="file" id="portfolioImages" name="portfolioImages[]" accept="image/*" multiple
              onchange="previewPortfolioImages(event)" />
            <div class="upload-icon">🖼️</div>
            <p><strong>Upload photos from your previous tours</strong></p>
            <small>Multiple images allowed (Max 5MB each, up to 10 images)</small>
          </div>
          <div id="portfolioPreview" class="preview-area"></div>
        </div>

        <div class="form-group">
          <label>License/ID Document Upload <span class="required">*</span></label>
          <div class="file-upload" onclick="document.getElementById('licenseDoc').click()">
            <input type="file" id="licenseDoc" name="licenseDoc" accept="image/*,.pdf" required
              onchange="previewDocument(event)" />
            <div class="upload-icon">📄</div>
            <p><strong>Upload your KPSGA license and National ID</strong></p>
            <small>PDF or Image format (Max 10MB)</small>
          </div>
          <div id="documentPreview" class="preview-area"></div>
        </div>

        <div class="alert alert-info">
          <strong>🔍 Verification Process:</strong> Your documents will be
          reviewed by our team within 2-3 business days. You'll receive an
          email once verification is complete.
        </div>
      </div>

      <!-- Step 4: Review & Submit -->
      <div class="form-step" data-step="4">
        <h2 class="form-title">Review Your Information</h2>
        <p class="form-description">
          Please review all details before submitting
        </p>

        <div id="reviewContent">
          <!-- Will be populated by JavaScript -->
        </div>

        <div class="form-group">
          <div class="checkbox-item">
            <input type="checkbox" name="terms" id="terms" required />
            <label for="terms">I agree to the
              <a href="#" style="color: #228b22">Terms and Conditions</a> and
              <a href="#" style="color: #228b22">Privacy Policy</a>
              <span class="required">*</span></label>
          </div>
        </div>

        <div class="form-group">
          <div class="checkbox-item">
            <input type="checkbox" name="verification" id="verification" required />
            <label for="verification">I confirm that all information provided is accurate and I
              understand my profile will undergo verification
              <span class="required">*</span></label>
          </div>
        </div>

        <div class="alert alert-info">
          <strong>📧 Next Steps:</strong><br />
          1. Click submit to send your application<br />
          2. Wait for admin approval (2-3 business days)<br />
          3. Start receiving booking requests once verified!
        </div>
      </div>

      <!-- Navigation Buttons -->
      <div class="form-navigation">
        <button type="button" class="btn btn-back" id="prevBtn" onclick="changeStep(-1)" style="display: none">
          ← Previous
        </button>
        <button type="button" class="btn btn-next" id="nextBtn" onclick="changeStep(1)">
          Next →
        </button>
        <button type="submit" class="btn btn-submit" id="submitBtn" style="display: none">
          Submit Application
        </button>
      </div>
    </form>

    <!-- Success Message -->
    <div class="success-message" id="successMessage">
      <div class="success-icon">✓</div>
      <h2>Application Submitted Successfully!</h2>
      <p>
        Thank you for completing your guide profile. Your application is now
        under review.
      </p>
      <p>
        We'll notify you via email within 2-3 business days once your account
        is verified.
      </p>
      <a href="guide_pending.php" class="btn btn-primary">Check Status</a>
    </div>
  </div>

  <script>
    // Copy all your existing JavaScript here
    let currentStep = 1;
    const totalSteps = 4;
    let portfolioFiles = [];

    // Change step
    function changeStep(direction) {
      // Validate current step before moving
      if (direction === 1 && !validateStep(currentStep)) {
        return;
      }

      // Update current step
      currentStep += direction;

      // Update UI
      updateProgressBar();
      showStep(currentStep);
      updateButtons();

      // Scroll to top
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    // Show specific step
    function showStep(step) {
      document
        .querySelectorAll(".form-step")
        .forEach((s) => s.classList.remove("active"));
      document
        .querySelector(`.form-step[data-step="${step}"]`)
        .classList.add("active");

      // If review step, populate review content
      if (step === 4) {
        populateReview();
      }
    }

    // Update progress bar
    function updateProgressBar() {
      // Update circles
      document.querySelectorAll(".step").forEach((step, index) => {
        if (index + 1 < currentStep) {
          step.classList.add("completed");
          step.classList.remove("active");
        } else if (index + 1 === currentStep) {
          step.classList.add("active");
          step.classList.remove("completed");
        } else {
          step.classList.remove("active", "completed");
        }
      });

      // Update progress line
      const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
      document.getElementById("progressLine").style.width = progress + "%";
    }

    // Update buttons
    function updateButtons() {
      const prevBtn = document.getElementById("prevBtn");
      const nextBtn = document.getElementById("nextBtn");
      const submitBtn = document.getElementById("submitBtn");

      prevBtn.style.display = currentStep === 1 ? "none" : "block";
      nextBtn.style.display = currentStep === totalSteps ? "none" : "block";
      submitBtn.style.display = currentStep === totalSteps ? "block" : "none";
    }

    // Validate step
    function validateStep(step) {
      const currentStepElement = document.querySelector(
        `.form-step[data-step="${step}"]`,
      );
      const inputs = currentStepElement.querySelectorAll(
        "input[required], textarea[required], select[required]",
      );

      for (let input of inputs) {
        if (!input.value && input.type !== "checkbox") {
          alert("Please fill in all required fields");
          input.focus();
          return false;
        }

        if (input.type === "checkbox" && input.hasAttribute("required")) {
          const checkboxGroup = input.name;
          const checked = currentStepElement.querySelectorAll(
            `input[name="${checkboxGroup}"]:checked`,
          );
          if (checked.length === 0) {
            alert("Please select at least one option");
            return false;
          }
        }
      }

      return true;
    }

    // Preview profile photo
    function previewProfilePhoto(event) {
      const file = event.target.files[0];
      const preview = document.getElementById("profilePreview");
      preview.innerHTML = "";

      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          preview.innerHTML = `
                        <div class="preview-item">
                            <img src="${e.target.result}" alt="Profile Photo">
                        </div>
                    `;
        };
        reader.readAsDataURL(file);
      }
    }

    // Preview portfolio images
    function previewPortfolioImages(event) {
      const files = Array.from(event.target.files);
      const preview = document.getElementById("portfolioPreview");
      preview.innerHTML = "";

      files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function (e) {
          const div = document.createElement("div");
          div.className = "preview-item";
          div.innerHTML = `
                        <img src="${e.target.result}" alt="Portfolio ${index + 1}">
                        <button type="button" class="remove-btn" onclick="removePortfolioImage(${index})">×</button>
                    `;
          preview.appendChild(div);
        };
        reader.readAsDataURL(file);
      });

      portfolioFiles = files;
    }

    // Remove portfolio image
    function removePortfolioImage(index) {
      const dt = new DataTransfer();
      const input = document.getElementById("portfolioImages");
      const files = Array.from(input.files);

      files.forEach((file, i) => {
        if (i !== index) dt.items.add(file);
      });

      input.files = dt.files;
      previewPortfolioImages({ target: input });
    }

    // Preview document
    function previewDocument(event) {
      const file = event.target.files[0];
      const preview = document.getElementById("documentPreview");
      preview.innerHTML = "";

      if (file) {
        if (file.type.includes("pdf")) {
          preview.innerHTML = `
                        <div class="preview-item" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <p style="margin: 0;">📄 ${file.name}</p>
                        </div>
                    `;
        } else {
          const reader = new FileReader();
          reader.onload = function (e) {
            preview.innerHTML = `
                            <div class="preview-item">
                                <img src="${e.target.result}" alt="License Document">
                            </div>
                        `;
          };
          reader.readAsDataURL(file);
        }
      }
    }

    // Populate review
    function populateReview() {
      const form = document.getElementById("registrationForm");
      const formData = new FormData(form);

      let languages = [];
      formData.getAll("languages[]").forEach((lang) => languages.push(lang));

      let categories = [];
      formData.getAll("categories[]").forEach((cat) => categories.push(cat));

      const reviewHTML = `
                <div style="background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: #8B4513; margin-bottom: 1rem;">Personal Information</h3>
                    <p><strong>Name:</strong> ${document.querySelector('input[name="firstName"]').value} ${document.querySelector('input[name="lastName"]').value}</p>
                    <p><strong>Email:</strong> ${document.querySelector('input[name="email"]').value}</p>
                    <p><strong>Phone:</strong> ${document.querySelector('input[name="phone"]').value}</p>
                </div>

                <div style="background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: #8B4513; margin-bottom: 1rem;">Professional Details</h3>
                    <p><strong>KPSGA License:</strong> ${formData.get("license")}</p>
                    <p><strong>Experience:</strong> ${formData.get("experience")} years</p>
                    <p><strong>Price Per Day:</strong> KES ${formData.get("price")}</p>
                    <p><strong>Languages:</strong> ${languages.join(", ")}</p>
                    <p><strong>Specializations:</strong> ${categories.join(", ")}</p>
                    <p><strong>Location:</strong> ${formData.get("location")}</p>
                </div>

                <div style="background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                    <h3 style="color: #8B4513; margin-bottom: 1rem;">Profile & Portfolio</h3>
                    <p><strong>Bio:</strong> ${formData.get("bio").substring(0, 200)}...</p>
                    <p><strong>Profile Photo:</strong> ${document.getElementById("profilePhoto").files.length > 0 ? "✓ Uploaded" : "✗ Not uploaded"}</p>
                    <p><strong>Portfolio Images:</strong> ${document.getElementById("portfolioImages").files.length} images uploaded</p>
                    <p><strong>License Document:</strong> ${document.getElementById("licenseDoc").files.length > 0 ? "✓ Uploaded" : "✗ Not uploaded"}</p>
                </div>
            `;

      document.getElementById("reviewContent").innerHTML = reviewHTML;
    }

    // Form submission
    document
      .getElementById("registrationForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();

        if (
          !document.getElementById("terms").checked ||
          !document.getElementById("verification").checked
        ) {
          alert("Please accept all terms and conditions");
          return;
        }

        const formData = new FormData(this);

        // Show loading state
        const submitBtn = document.getElementById("submitBtn");
        submitBtn.innerText = "Processing...";
        submitBtn.disabled = true;

        fetch(window.location.href, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Hide form, show success
              document.querySelector(".form-container").style.display = "none";
              document.querySelector(".progress-container").style.display = "none";
              document.getElementById("successMessage").classList.add("active");
            } else {
              alert("Registration failed: " + data.message);
              submitBtn.innerText = "Submit Application";
              submitBtn.disabled = false;
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            alert("An error occurred during submission.");
            submitBtn.innerText = "Submit Application";
            submitBtn.disabled = false;
          });
      });

    // Initialize
    updateProgressBar();
    updateButtons();
  </script>
</body>

</html>