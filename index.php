<?php
session_start();
?>



<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Discover Kenya - Your Perfect Tour Guide Awaits</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="landing_pagestyle.css" />
</head>

<body>
  <!-- Navigation -->
  <nav>
    <div class="nav-container">
      <div class="logo">Kenya<span>Guides</span></div>
      <ul class="nav-links">
        <li><a href="#home">Home</a></li>
        <li><a href="#guides">Find Guides</a></li>
        <li><a href="#how">How It Works</a></li>
        <li><a href="#about">About</a></li>
      </ul>
      <div class="nav-buttons">
        <?php if (!isset($_SESSION['user_id'])): ?>
          <!-- User NOT logged in -->
          <a href="login.php" class="btn btn-outline">Login</a>
          <a href="signup.php" class="btn btn-primary">Sign Up</a>
        <?php else: ?>
          <!-- User IS logged in -->

          <a href="logout.php" class="btn btn-primary">Logout</a>
        <?php endif; ?>
      </div>

    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero" id="home">
    <div class="hero-content">
      <h1>Discover Your Perfect Kenyan Adventure</h1>
      <p>
        Connect with verified professional tour guides for authentic,
        personalized experiences across Kenya
      </p>


    </div>
  </section>


  <!-- CTA Section -->
  <section class="cta-section" id="guides">
    <h2>Ready to Start Your Adventure?</h2>
    <p>Join satisfied tourists who found their perfect guide</p>
    <div class="cta-buttons">
      <a href="find_guide.php" class="btn btn-white">Find a Guide</a>
      <a href="guide_profilecreation.php" class="btn btn-primary">Become a Guide</a>
    </div>
  </section>

  <!-- How It Works -->
  <section class="how-it-works" id="how">
    <h2 class="section-title">How It Works</h2>
    <p class="section-subtitle">
      Three simple steps to your perfect adventure
    </p>

    <div class="steps-container">
      <div class="step">
        <div class="step-number">1</div>
        <h3>Search & Discover</h3>
        <p>
          Browse verified tour guides by location, specialty, price, and
          availability. Read reviews and view portfolios to find your perfect
          match.
        </p>
      </div>

      <div class="step">
        <div class="step-number">2</div>
        <h3>Connect & Plan</h3>
        <p>
          Message guides directly to discuss your interests, customize your
          itinerary, and ask questions. Book with confidence using our secure
          platform.
        </p>
      </div>

      <div class="step">
        <div class="step-number">3</div>
        <h3>Experience & Review</h3>
        <p>
          Enjoy your personalized tour with a professional guide. Share your
          experience and help future travelers by leaving a review.
        </p>
      </div>
    </div>
  </section>


  <!-- Footer -->
  <footer>
    <div class="footer-content">
      <div class="footer-section" id="about">
        <h3>About SafariGuide</h3>
        <p>
          Connecting tourists with verified professional tour guides across
          Kenya for authentic, personalized experiences.
        </p>
      </div>
      <div class="footer-section">
        <h3>For Tourists</h3>
        <ul>
          <li><a href="find_guide.php">Find Guides</a></li>
          <li><a href="tourist_dashboard.php">Tourist Dashboard</a></li>
          <li><a href="#how">How It Works</a></li>
        </ul>
      </div>
      <div class="footer-section">
        <h3>For Guides</h3>
        <ul>
          <li><a href="guide_profilecreation.php">Register as Guide</a></li>
          <li><a href="guide_dashboard.php">Guide Dashboard</a></li>
        </ul>
      </div>
      <div class="footer-section">
        <h3>Contact</h3>
        <ul>
          <li>Email: <a href="mailto:info@kenyaguides.ke">info@kenyaguides.ke</a></li>
          <li>Phone: +254 700 123 456</li>
          <li>Address: Nairobi, Kenya</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>
        &copy; 2026 KenyaGuides Platform. All rights reserved. |
      </p>
    </div>
  </footer>
</body>

</html>