<?php
require_once __DIR__ . '/includes/bootstrap.php';

// If coming from the index application, prefill values from session
$prefill_company_name       = $_SESSION['app_company_name'] ?? '';
$prefill_main_contact       = $_SESSION['app_main_contact'] ?? '';
$prefill_company_phone      = $_SESSION['app_company_phone'] ?? '';
$prefill_main_contact_email = $_SESSION['app_main_contact_email'] ?? '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        die("Invalid CSRF token");
    }
    
    // Sanitize and assign form values
    $company_name            = htmlspecialchars(trim($_POST['company_name']));
    $main_contact            = htmlspecialchars(trim($_POST['main_contact']));
    $main_contact_email      = htmlspecialchars(trim($_POST['main_contact_email']));
    $company_hq_address      = htmlspecialchars(trim($_POST['company_hq_address']));
    $company_hq_city         = htmlspecialchars(trim($_POST['company_hq_city']));
    $company_hq_state        = htmlspecialchars(trim($_POST['company_hq_state']));
    $company_hq_zip_code     = htmlspecialchars(trim($_POST['company_hq_zip_code']));
    $company_phone_number    = htmlspecialchars(trim($_POST['company_phone_number']));
    $ein_number              = htmlspecialchars(trim($_POST['ein_number']));
    $finance_contact_name    = htmlspecialchars(trim($_POST['finance_contact_name']));
    $finance_contact_email   = filter_var($_POST['finance_contact_email'], FILTER_SANITIZE_EMAIL);
    $finance_contact_phone_number = htmlspecialchars(trim($_POST['finance_contact_phone_number']));
    $technical_contact_name  = htmlspecialchars(trim($_POST['technical_contact_name']));
    $technical_contact_email = filter_var($_POST['technical_contact_email'], FILTER_SANITIZE_EMAIL);
    $technical_contact_phone_number = htmlspecialchars(trim($_POST['technical_contact_phone_number']));
    
    try {
        $pdo = app_pdo();
        // Insert new partner record with status set to "Pending Application"
        $stmt = $pdo->prepare(
            'INSERT INTO partners (
                company_name, main_contact, main_contact_email, company_hq_address, company_hq_city, company_hq_state,
                company_hq_zip_code, company_phone_number, ein_number, finance_contact_name, finance_contact_email,
                finance_contact_phone_number, technical_contact_name, technical_contact_email, technical_contact_phone_number,
                application_status
            ) VALUES (
                :company_name, :main_contact, :main_contact_email, :company_hq_address, :company_hq_city, :company_hq_state,
                :company_hq_zip_code, :company_phone_number, :ein_number, :finance_contact_name, :finance_contact_email,
                :finance_contact_phone_number, :technical_contact_name, :technical_contact_email, :technical_contact_phone_number,
                :application_status
            )'
        );
        
        $stmt->execute([
            ':company_name'               => $company_name,
            ':main_contact'               => $main_contact,
            ':main_contact_email'         => $main_contact_email,
            ':company_hq_address'         => $company_hq_address,
            ':company_hq_city'            => $company_hq_city,
            ':company_hq_state'           => $company_hq_state,
            ':company_hq_zip_code'        => $company_hq_zip_code,
            ':company_phone_number'       => $company_phone_number,
            ':ein_number'                 => $ein_number,
            ':finance_contact_name'       => $finance_contact_name,
            ':finance_contact_email'      => $finance_contact_email,
            ':finance_contact_phone_number'=> $finance_contact_phone_number,
            ':technical_contact_name'     => $technical_contact_name,
            ':technical_contact_email'    => $technical_contact_email,
            ':technical_contact_phone_number' => $technical_contact_phone_number,
            ':application_status'         => 'Pending Application'
        ]);
        
        $success = "Your application has been submitted successfully. Your application is now pending.";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$pageTitle = 'Partner Application';
include __DIR__ . '/includes/header.php';
?>
    <div class="container">
        <div class="card">
            <h2>Create Partner Application</h2>
            <?php if (!empty($success)): ?>
                <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
            <?php elseif (!empty($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST" class="two-column-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(app_csrf_token()); ?>">

                <!-- Column 1 Section -->
                <div class="form-section">
                    <div class="form-group">
                        <label for="company_name">Company Name:</label>
                        <input type="text" id="company_name" name="company_name"
                               value="<?php echo htmlspecialchars($prefill_company_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="main_contact">Main Contact:</label>
                        <input type="text" id="main_contact" name="main_contact"
                               value="<?php echo htmlspecialchars($prefill_main_contact); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="main_contact_email">Main Contact Email:</label>
                        <input type="email" id="main_contact_email" name="main_contact_email"
                               value="<?php echo htmlspecialchars($prefill_main_contact_email); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="company_hq_address">Company HQ Address:</label>
                        <input type="text" id="company_hq_address" name="company_hq_address" required>
                    </div>
                    <div class="form-group">
                        <label for="company_hq_city">Company HQ City:</label>
                        <input type="text" id="company_hq_city" name="company_hq_city" required>
                    </div>
                    <div class="form-group">
                        <label for="company_hq_state">Company HQ State:</label>
                        <select id="company_hq_state" name="company_hq_state" required>
                            <option value="">Select State</option>
                            <?php 
                            $states = [
                                "AL"=>"Alabama", "AK"=>"Alaska", "AZ"=>"Arizona", "AR"=>"Arkansas",
                                "CA"=>"California", "CO"=>"Colorado", "CT"=>"Connecticut", "DE"=>"Delaware",
                                "FL"=>"Florida", "GA"=>"Georgia", "HI"=>"Hawaii", "ID"=>"Idaho",
                                "IL"=>"Illinois", "IN"=>"Indiana", "IA"=>"Iowa", "KS"=>"Kansas",
                                "KY"=>"Kentucky", "LA"=>"Louisiana", "ME"=>"Maine", "MD"=>"Maryland",
                                "MA"=>"Massachusetts", "MI"=>"Michigan", "MN"=>"Minnesota", "MS"=>"Mississippi",
                                "MO"=>"Missouri", "MT"=>"Montana", "NE"=>"Nebraska", "NV"=>"Nevada",
                                "NH"=>"New Hampshire", "NJ"=>"New Jersey", "NM"=>"New Mexico", "NY"=>"New York",
                                "NC"=>"North Carolina", "ND"=>"North Dakota", "OH"=>"Ohio", "OK"=>"Oklahoma",
                                "OR"=>"Oregon", "PA"=>"Pennsylvania", "RI"=>"Rhode Island", "SC"=>"South Carolina",
                                "SD"=>"South Dakota", "TN"=>"Tennessee", "TX"=>"Texas", "UT"=>"Utah",
                                "VT"=>"Vermont", "VA"=>"Virginia", "WA"=>"Washington", "WV"=>"West Virginia",
                                "WI"=>"Wisconsin", "WY"=>"Wyoming", "PR"=>"Puerto Rico", "GU"=>"Guam",
                                "VI"=>"U.S. Virgin Islands", "AS"=>"American Samoa", "MP"=>"Northern Mariana Islands"
                            ];
                            foreach ($states as $abbr => $state) {
                                echo "<option value='$abbr'>$state</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="company_hq_zip_code">Company HQ Zip Code:</label>
                        <input type="text" id="company_hq_zip_code" name="company_hq_zip_code" required>
                    </div>
                    <div class="form-group">
                        <label for="company_phone_number">Company Phone Number:</label>
                        <input type="text" id="company_phone_number" name="company_phone_number"
                               value="<?php echo htmlspecialchars($prefill_company_phone); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ein_number">EIN Number:</label>
                        <input type="text" id="ein_number" name="ein_number" required>
                    </div>
                </div>

                <!-- Column 2 Section -->
                <div class="form-section">
                    <div class="sub-section-title">Finance Contact</div>
                    <div class="form-group">
                        <label for="finance_contact_name">Name:</label>
                        <input type="text" id="finance_contact_name" name="finance_contact_name" required>
                    </div>
                    <div class="form-group">
                        <label for="finance_contact_email">Email:</label>
                        <input type="email" id="finance_contact_email" name="finance_contact_email" required>
                    </div>
                    <div class="form-group">
                        <label for="finance_contact_phone_number">Phone Number:</label>
                        <input type="text" id="finance_contact_phone_number" name="finance_contact_phone_number" required>
                    </div>

                    <div class="sub-section-title">Technical Contact</div>
                    <div class="form-group">
                        <label for="technical_contact_name">Name:</label>
                        <input type="text" id="technical_contact_name" name="technical_contact_name" required>
                    </div>
                    <div class="form-group">
                        <label for="technical_contact_email">Email:</label>
                        <input type="email" id="technical_contact_email" name="technical_contact_email" required>
                    </div>
                    <div class="form-group">
                        <label for="technical_contact_phone_number">Phone Number:</label>
                        <input type="text" id="technical_contact_phone_number" name="technical_contact_phone_number" required>
                    </div>
                </div>

                <!-- Full-width submit button -->
                <div class="full-width">
                    <button type="submit">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
    <?php include __DIR__ . '/includes/footer.php'; ?>
