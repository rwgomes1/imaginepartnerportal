<?php
session_start();

// Implement basic security headers
header("Content-Security-Policy: default-src 'self' data: https:;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Ensure secure session cookies
if (session_status() === PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
}

// Process the partner application form submission from index.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_name'])) {
    // Map the submitted form fields to session variables
    $_SESSION['app_main_contact']       = trim($_POST['app_name']);   // Maps to Main Contact
    $_SESSION['app_company_name']       = trim($_POST['app_company']);  // Maps to Company Name
    $_SESSION['app_company_phone']      = trim($_POST['app_phone']);    // Maps to Company Phone Number
    $_SESSION['app_main_contact_email'] = trim($_POST['app_email']);    // Maps to Main Contact Email

    // Redirect to the public partner application form page
    header('Location: /apply_partner.php');
    exit;    
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner with ImagineSoftware</title>
    <link rel="stylesheet" href="assets/css/style2.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <!-- Left Column: Slider and Content -->
        <div class="left-column">
            <!-- Slider Container (remains dynamic but fixed height as per CSS) -->
            <div class="slider-container">
                <div class="slider-pointer">
                    <input type="range" id="range-slider" min="0" max="150" step="30" value="0">
                    <div class="pointer" id="pointer">
                        <span id="pointer-value">0</span>
                    </div>
                </div>
                <div class="slider-panels">
                    <!-- Month 1 -->
                    <div id="month-1" class="slider-panel">
                        <h2>Stage 1</h2>
                        <button class="bubble" data-target="bubble-1">Review referral/reseller agreements</button>
                        <button class="bubble" data-target="bubble-2">Sign NDAs and clarify expectations</button>
                        <button class="bubble" data-target="bubble-3">Finalize contracts with partners</button>
                    </div>
                    <!-- Month 2 -->
                    <div id="month-2" class="slider-panel">
                        <h2>Stage 2</h2>
                        <button class="bubble" data-target="bubble-4">Complete contracts and NDAs</button>
                        <button class="bubble" data-target="bubble-5">Obtain Partner Qualification Guideline approval</button>
                        <button class="bubble" data-target="bubble-6">Review technical integration documentation</button>
                    </div>
                    <!-- Month 3 -->
                    <div id="month-3" class="slider-panel">
                        <h2>Stage 3</h2>
                        <button class="bubble" data-target="bubble-7">IT VSAQ approval and kickoff meeting</button>
                        <button class="bubble" data-target="bubble-8">Introduction to the Partner Portal</button>
                        <button class="bubble" data-target="bubble-9">High-level review of integration documentation</button>
                    </div>
                    <!-- Month 4 -->
                    <div id="month-4" class="slider-panel">
                        <h2>Stage 4</h2>
                        <button class="bubble" data-target="bubble-10">Development build and testing phase</button>
                        <button class="bubble" data-target="bubble-11">Full integration ready for deployment</button>
                        <button class="bubble" data-target="bubble-12">Testing with pilot clients</button>
                    </div>
                    <!-- Month 5 -->
                    <div id="month-5" class="slider-panel">
                        <h2>Stage 5</h2>
                        <button class="bubble" data-target="bubble-13">Demo and sales training videos provided</button>
                        <button class="bubble" data-target="bubble-14">Obtain optional certifications (ICP, ICEE)</button>
                        <button class="bubble" data-target="bubble-15">Transition to Relationship Officer</button>
                    </div>
                    <!-- Month 6+ -->
                    <div id="month-6" class="slider-panel">
                        <h2>Stage 6</h2>
                        <button class="bubble" data-target="bubble-16">Maintain successful partnership</button>
                        <button class="bubble" data-target="bubble-17">Add additional VAPs and integrations</button>
                        <button class="bubble" data-target="bubble-18">Expand client base and enrich opportunities</button>
                    </div>
                </div>
            </div>
            <!-- Content Area (fixed height) -->
            <div id="content-area" class="content-area">
                <p>Select a stage to view details here.</p>
            </div>
        </div>

        <!-- Right Column: Partner Application Form -->
        <div class="partner-form-container">
            <div class="partner-form">
                <h3>Start The Partner Application Process</h3>
                <form method="POST" action="index.php">
                    <label for="app_name">Name:</label>
                    <input type="text" id="app_name" name="app_name" placeholder="Your Name" required>

                    <label for="app_company">Company:</label>
                    <input type="text" id="app_company" name="app_company" placeholder="Your Company" required>

                    <label for="app_phone">Phone Number:</label>
                    <input type="text" id="app_phone" name="app_phone" placeholder="Your Phone Number" required>

                    <label for="app_email">Email Address:</label>
                    <input type="email" id="app_email" name="app_email" placeholder="Your Email" required>

                    <button type="submit" class="button">Start Application</button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/script01010101.js"></script>
</body>
</html>
