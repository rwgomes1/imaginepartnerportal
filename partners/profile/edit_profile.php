<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and is a partner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'partner') {
    header('Location: ../../login.php');
    exit;
}

// Connect to database
$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Retrieve partner_id from session or from the users table
$partner_id = isset($_SESSION['partner_id']) ? $_SESSION['partner_id'] : null;
if (!$partner_id) {
    $stmt = $pdo->prepare("SELECT partner_id FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['partner_id'])) {
        $partner_id = $row['partner_id'];
        $_SESSION['partner_id'] = $partner_id;
    } else {
        die("Partner information not found.");
    }
}

// Function to normalize phone numbers
function normalizePhoneNumber($phone) {
    // Remove all non-digit characters
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) == 10) {
        return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
    }
    // Return original if not 10 digits
    return $phone;
}

// Initialize messages
$success = '';
$error = '';

// Handle form submission to update partner info
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs for Company Information
    $company_name        = htmlspecialchars(trim($_POST['company_name']));
    $company_legal_name  = htmlspecialchars(trim($_POST['company_legal_name']));
    $dba_fka             = htmlspecialchars(trim($_POST['dba_fka']));
    $company_hq_address  = htmlspecialchars(trim($_POST['company_hq_address']));
    $company_hq_city     = htmlspecialchars(trim($_POST['company_hq_city']));
    $company_hq_state    = htmlspecialchars(trim($_POST['company_hq_state']));
    $company_hq_zip_code = htmlspecialchars(trim($_POST['company_hq_zip_code']));
    $raw_company_phone   = htmlspecialchars(trim($_POST['company_phone_number']));
    $company_phone_number= normalizePhoneNumber($raw_company_phone);
    $ein_number          = htmlspecialchars(trim($_POST['ein_number']));

    // Main Contact
    $main_contact_name   = htmlspecialchars(trim($_POST['main_contact_name']));
    $main_contact_email  = filter_var($_POST['main_contact_email'], FILTER_SANITIZE_EMAIL);
    $raw_main_contact_phone = htmlspecialchars(trim($_POST['main_contact_phone']));
    $main_contact_phone  = normalizePhoneNumber($raw_main_contact_phone);

    // Finance Contact
    $finance_contact_name  = htmlspecialchars(trim($_POST['finance_contact_name']));
    $finance_contact_email = filter_var($_POST['finance_contact_email'], FILTER_SANITIZE_EMAIL);
    $raw_finance_phone     = htmlspecialchars(trim($_POST['finance_contact_phone_number']));
    $finance_contact_phone = normalizePhoneNumber($raw_finance_phone);

    // Technical Contact
    $technical_contact_name  = htmlspecialchars(trim($_POST['technical_contact_name']));
    $technical_contact_email = filter_var($_POST['technical_contact_email'], FILTER_SANITIZE_EMAIL);
    $raw_technical_phone     = htmlspecialchars(trim($_POST['technical_contact_phone_number']));
    $technical_contact_phone = normalizePhoneNumber($raw_technical_phone);

    try {
        $stmt = $pdo->prepare(
            "UPDATE partners 
             SET 
                company_name                 = :company_name,
                company_legal_name          = :company_legal_name,
                dba_fka                     = :dba_fka,
                company_hq_address          = :company_hq_address,
                company_hq_city             = :company_hq_city,
                company_hq_state            = :company_hq_state,
                company_hq_zip_code         = :company_hq_zip_code,
                company_phone_number        = :company_phone_number,
                ein_number                  = :ein_number,
                main_contact_name           = :main_contact_name,
                main_contact_email          = :main_contact_email,
                main_contact_phone          = :main_contact_phone,
                finance_contact_name        = :finance_contact_name,
                finance_contact_email       = :finance_contact_email,
                finance_contact_phone_number= :finance_contact_phone,
                technical_contact_name      = :technical_contact_name,
                technical_contact_email     = :technical_contact_email,
                technical_contact_phone_number = :technical_contact_phone
             WHERE id = :partner_id"
        );
        $stmt->execute([
            ':company_name'            => $company_name,
            ':company_legal_name'      => $company_legal_name,
            ':dba_fka'                 => $dba_fka,
            ':company_hq_address'      => $company_hq_address,
            ':company_hq_city'         => $company_hq_city,
            ':company_hq_state'        => $company_hq_state,
            ':company_hq_zip_code'     => $company_hq_zip_code,
            ':company_phone_number'    => $company_phone_number,
            ':ein_number'              => $ein_number,
            ':main_contact_name'       => $main_contact_name,
            ':main_contact_email'      => $main_contact_email,
            ':main_contact_phone'      => $main_contact_phone,
            ':finance_contact_name'    => $finance_contact_name,
            ':finance_contact_email'   => $finance_contact_email,
            ':finance_contact_phone'   => $finance_contact_phone,
            ':technical_contact_name'  => $technical_contact_name,
            ':technical_contact_email' => $technical_contact_email,
            ':technical_contact_phone' => $technical_contact_phone,
            ':partner_id'              => $partner_id,
        ]);
        $success = "Profile updated successfully.";
    } catch (PDOException $e) {
        $error = "Error updating information: " . $e->getMessage();
    }
}

// Retrieve current partner information
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = :id");
$stmt->execute([':id' => $partner_id]);
$partnerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        <style>
            /* General form styling for a sleek look */
            .two-column-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .form-group {
                display: flex;
                flex-direction: column;
                margin-bottom: 10px;
            }
            .form-group label {
                font-weight: 600;
                margin-bottom: 5px;
                color: #444;
            }
            .form-group input,
            .form-group select {
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 0.95rem;
            }
            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: #007bff;
                box-shadow: 0 0 4px rgba(0, 123, 255, 0.2);
            }
            .card h2 {
                margin-top: 0;
                text-align: center;
            }
            .submit-btn {
                display: block;
                margin: 0 auto;
                padding: 12px 30px;
                background: #0056b3;
                color: #fff;
                border: none;
                border-radius: 4px;
                font-size: 1rem;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            .submit-btn:hover {
                background: #003f8c;
            }
        </style>

        <div class="card" style="padding:20px; margin-bottom:20px;">
            <h2>Edit Partner Profile: <?php echo htmlspecialchars($partnerInfo['company_name'] ?? ''); ?></h2>
            <?php if ($success): ?>
                <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
            <?php elseif ($error): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </div>

        <!-- Form (All sections in one form) -->
        <form method="POST">
            <!-- Card 1: Company Information -->
            <div class="card" style="margin-bottom:20px; padding:20px;">
                <h2>Company Information</h2>
                <div class="two-column-grid">
                    <div class="form-group">
                        <label for="company_name">Company Name:</label>
                        <input type="text" id="company_name" name="company_name" required
                               value="<?php echo htmlspecialchars($partnerInfo['company_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_legal_name">Company Legal Name:</label>
                        <input type="text" id="company_legal_name" name="company_legal_name" required
                               value="<?php echo htmlspecialchars($partnerInfo['company_legal_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="dba_fka">DBA / FKA:</label>
                        <input type="text" id="dba_fka" name="dba_fka" required
                               value="<?php echo htmlspecialchars($partnerInfo['dba_fka'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_hq_address">HQ Address:</label>
                        <input type="text" id="company_hq_address" name="company_hq_address" required
                               value="<?php echo htmlspecialchars($partnerInfo['company_hq_address'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_hq_city">City:</label>
                        <input type="text" id="company_hq_city" name="company_hq_city" required
                               value="<?php echo htmlspecialchars($partnerInfo['company_hq_city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_hq_state">State:</label>
                        <select id="company_hq_state" name="company_hq_state" required>
                            <option value="">Select State</option>
                            <?php 
                            $states = [
                                "AL"=>"Alabama","AK"=>"Alaska","AZ"=>"Arizona","AR"=>"Arkansas","CA"=>"California",
                                "CO"=>"Colorado","CT"=>"Connecticut","DE"=>"Delaware","FL"=>"Florida","GA"=>"Georgia",
                                "HI"=>"Hawaii","ID"=>"Idaho","IL"=>"Illinois","IN"=>"Indiana","IA"=>"Iowa","KS"=>"Kansas",
                                "KY"=>"Kentucky","LA"=>"Louisiana","ME"=>"Maine","MD"=>"Maryland","MA"=>"Massachusetts",
                                "MI"=>"Michigan","MN"=>"Minnesota","MS"=>"Mississippi","MO"=>"Missouri","MT"=>"Montana",
                                "NE"=>"Nebraska","NV"=>"Nevada","NH"=>"New Hampshire","NJ"=>"New Jersey","NM"=>"New Mexico",
                                "NY"=>"New York","NC"=>"North Carolina","ND"=>"North Dakota","OH"=>"Ohio","OK"=>"Oklahoma",
                                "OR"=>"Oregon","PA"=>"Pennsylvania","RI"=>"Rhode Island","SC"=>"South Carolina",
                                "SD"=>"South Dakota","TN"=>"Tennessee","TX"=>"Texas","UT"=>"Utah","VT"=>"Vermont",
                                "VA"=>"Virginia","WA"=>"Washington","WV"=>"West Virginia","WI"=>"Wisconsin","WY"=>"Wyoming",
                                "PR"=>"Puerto Rico","GU"=>"Guam","VI"=>"U.S. Virgin Islands","AS"=>"American Samoa",
                                "MP"=>"Northern Mariana Islands"
                            ];
                            foreach ($states as $abbr => $state) {
                                $selected = (isset($partnerInfo['company_hq_state']) && $partnerInfo['company_hq_state'] === $abbr) ? 'selected' : '';
                                echo "<option value='{$abbr}' {$selected}>{$state}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="company_hq_zip_code">Zip Code:</label>
                        <input type="text" id="company_hq_zip_code" name="company_hq_zip_code" required
                               value="<?php echo htmlspecialchars($partnerInfo['company_hq_zip_code'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_phone_number">Company Phone Number:</label>
                        <input type="text" id="company_phone_number" name="company_phone_number" required
                               value="<?php echo htmlspecialchars($partnerInfo['company_phone_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="ein_number">EIN Number:</label>
                        <input type="text" id="ein_number" name="ein_number" required
                               value="<?php echo htmlspecialchars($partnerInfo['ein_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Card 2: Main Contact -->
            <div class="card" style="margin-bottom:20px; padding:20px;">
                <h2>Main Contact</h2>
                <div class="two-column-grid">
                    <div class="form-group">
                        <label for="main_contact_name">Name:</label>
                        <input type="text" id="main_contact_name" name="main_contact_name" required
                               value="<?php echo htmlspecialchars($partnerInfo['main_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="main_contact_email">Email:</label>
                        <input type="email" id="main_contact_email" name="main_contact_email" required
                               value="<?php echo htmlspecialchars($partnerInfo['main_contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="main_contact_phone">Phone Number:</label>
                        <input type="text" id="main_contact_phone" name="main_contact_phone" required
                               value="<?php echo htmlspecialchars($partnerInfo['main_contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Card 3: Finance Contact -->
            <div class="card" style="margin-bottom:20px; padding:20px;">
                <h2>Finance Contact</h2>
                <div class="two-column-grid">
                    <div class="form-group">
                        <label for="finance_contact_name">Name:</label>
                        <input type="text" id="finance_contact_name" name="finance_contact_name" required
                               value="<?php echo htmlspecialchars($partnerInfo['finance_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="finance_contact_email">Email:</label>
                        <input type="email" id="finance_contact_email" name="finance_contact_email" required
                               value="<?php echo htmlspecialchars($partnerInfo['finance_contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="finance_contact_phone_number">Phone Number:</label>
                        <input type="text" id="finance_contact_phone_number" name="finance_contact_phone_number" required
                               value="<?php echo htmlspecialchars($partnerInfo['finance_contact_phone_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Card 4: Technical Contact -->
            <div class="card" style="margin-bottom:20px; padding:20px;">
                <h2>Technical Contact</h2>
                <div class="two-column-grid">
                    <div class="form-group">
                        <label for="technical_contact_name">Name:</label>
                        <input type="text" id="technical_contact_name" name="technical_contact_name" required
                               value="<?php echo htmlspecialchars($partnerInfo['technical_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="technical_contact_email">Email:</label>
                        <input type="email" id="technical_contact_email" name="technical_contact_email" required
                               value="<?php echo htmlspecialchars($partnerInfo['technical_contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="technical_contact_phone_number">Phone Number:</label>
                        <input type="text" id="technical_contact_phone_number" name="technical_contact_phone_number" required
                               value="<?php echo htmlspecialchars($partnerInfo['technical_contact_phone_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Full-width Submit Button -->
            <div style="text-align:center; margin-top:20px;">
                <button type="submit" class="submit-btn">Update Profile</button>
            </div>
        </form>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
