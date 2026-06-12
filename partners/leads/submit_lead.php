<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and is a partner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'partner') {
    header('Location: ../../login.php');
    exit;
}

// Retrieve partner_id from session or from the users table
$partner_id = isset($_SESSION['partner_id']) ? $_SESSION['partner_id'] : null;
$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

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

// Retrieve logged-in rep's name (stored in session) and email (from DB)
$repName = $_SESSION['user_name'];
$repEmail = '';
$stmtRep = $pdo->prepare("SELECT email FROM users WHERE id = :id");
$stmtRep->execute([':id' => $_SESSION['user_id']]);
$repData = $stmtRep->fetch(PDO::FETCH_ASSOC);
if ($repData && !empty($repData['email'])) {
    $repEmail = $repData['email'];
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gather values from form
    $company_name    = htmlspecialchars(trim($_POST['company_name']));
    $company_contact = htmlspecialchars(trim($_POST['company_contact']));
    $email           = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone_number    = htmlspecialchars(trim($_POST['phone_number']));
    $hq_address      = htmlspecialchars(trim($_POST['hq_address']));
    $hq_city         = htmlspecialchars(trim($_POST['hq_city']));
    $hq_state        = htmlspecialchars(trim($_POST['hq_state']));
    $hq_zip          = htmlspecialchars(trim($_POST['hq_zip']));

    // For the rep fields, we use the logged-in user's data
    $submitted_by     = $repName;
    $rep_contact_info = $repEmail;

    // lead_type is an array of checkboxes; join them as comma-separated
    $lead_types = isset($_POST['lead_type']) ? $_POST['lead_type'] : [];
    $lead_type  = implode(',', $lead_types);

    // Estimated revenue (as a string; you can cast to float if needed)
    $estimated_revenue = htmlspecialchars(trim($_POST['estimated_revenue']));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO partner_leads 
            (partner_id, company_name, company_contact, email, phone_number, hq_address, hq_city, hq_state, hq_zip, submitted_by, rep_contact_info, lead_type, estimated_revenue)
            VALUES 
            (:partner_id, :company_name, :company_contact, :email, :phone_number, :hq_address, :hq_city, :hq_state, :hq_zip, :submitted_by, :rep_contact_info, :lead_type, :estimated_revenue)
        ");
        $stmt->execute([
            ':partner_id'       => $partner_id,
            ':company_name'     => $company_name,
            ':company_contact'  => $company_contact,
            ':email'            => $email,
            ':phone_number'     => $phone_number,
            ':hq_address'       => $hq_address,
            ':hq_city'          => $hq_city,
            ':hq_state'         => $hq_state,
            ':hq_zip'           => $hq_zip,
            ':submitted_by'     => $submitted_by,
            ':rep_contact_info' => $rep_contact_info,
            ':lead_type'        => $lead_type,
            ':estimated_revenue'=> $estimated_revenue
        ]);
        $success = "Lead submitted successfully.";
    } catch (PDOException $e) {
        $error = "Error submitting lead: " . $e->getMessage();
    }
}

// Include unified header & sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<main class="main-content">
    <div class="container">
        <style>
            /* Two-column form styling */
            .two-column-form {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-gap: 20px;
            }
            .form-section {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            .form-group {
                display: flex;
                flex-direction: column;
            }
            .form-group label {
                font-weight: 600;
                margin-bottom: 5px;
                text-align: left;
                color: #444;
                font-size: 0.95rem;
            }
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 0.95rem;
                color: #333;
            }
            .full-width {
                grid-column: 1 / 3;
                display: flex;
                justify-content: center;
                margin-top: 20px;
            }
            button[type="submit"] {
                padding: 12px 30px;
                background: #0056b3;
                color: #fff;
                border: none;
                border-radius: 4px;
                font-size: 1rem;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            button[type="submit"]:hover {
                background: #003f8c;
            }
        </style>

        <div class="card">
            <h2>Lead Submission</h2>
            <?php if ($success): ?>
                <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
            <?php elseif ($error): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <!-- Two-column form layout -->
            <form method="POST" class="two-column-form">
                <!-- Column 1 -->
                <div class="form-section">
                    <div class="form-group">
                        <label for="company_name">Company Name:</label>
                        <input type="text" id="company_name" name="company_name" required>
                    </div>
                    <div class="form-group">
                        <label for="company_contact">Company Contact:</label>
                        <input type="text" id="company_contact" name="company_contact" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number:</label>
                        <input type="text" id="phone_number" name="phone_number" required>
                    </div>
                    <!-- Auto-populated fields for Rep Info -->
                    <div class="form-group">
                        <label for="submitted_by">Rep Who Submitted Lead:</label>
                        <input type="text" id="submitted_by" name="submitted_by" 
                               value="<?php echo htmlspecialchars($repName); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="rep_contact_info">Rep Contact Info:</label>
                        <input type="text" id="rep_contact_info" name="rep_contact_info" 
                               value="<?php echo htmlspecialchars($repEmail); ?>" readonly>
                    </div>
                </div>

                <!-- Column 2 -->
                <div class="form-section">
                    <div class="form-group">
                        <label for="hq_address">Corporate HQ Address:</label>
                        <input type="text" id="hq_address" name="hq_address" required>
                    </div>
                    <div class="form-group">
                        <label for="hq_city">Corporate HQ City:</label>
                        <input type="text" id="hq_city" name="hq_city" required>
                    </div>
                    <div class="form-group">
                        <label for="hq_state">Corporate HQ State:</label>
                        <select id="hq_state" name="hq_state" required>
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
                                echo "<option value='{$abbr}'>{$state}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="hq_zip">Corporate HQ Zip:</label>
                        <input type="text" id="hq_zip" name="hq_zip" required>
                    </div>
                    <!-- New: Lead Type Checkboxes -->
                    <div class="form-group">
                        <label>Lead Type:</label>
                        <div>
                            <label><input type="checkbox" name="lead_type[]" value="Billing System"> Billing System</label>
                        </div>
                        <div>
                            <label><input type="checkbox" name="lead_type[]" value="VAPS"> VAPS</label>
                        </div>
                        <div>
                            <label><input type="checkbox" name="lead_type[]" value="Data Sales"> Data Sales</label>
                        </div>
                    </div>
                    <!-- Estimated Revenue -->
                    <div class="form-group">
                        <label for="estimated_revenue">Estimated Revenue:</label>
                        <input type="number" step="0.01" id="estimated_revenue" name="estimated_revenue" 
                               placeholder="e.g., 50000.00">
                    </div>
                </div>

                <!-- Full-width row for Submit -->
                <div class="full-width">
                    <button type="submit" class="button">Submit Lead</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
