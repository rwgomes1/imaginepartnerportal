<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and is an admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: /login.php');
    exit;
}

// Check for partner_id in the query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid partner ID.");
}
$partner_id = (int)$_GET['id'];

// Attempt to fetch the existing partner record
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id');
    $stmt->execute([':id' => $partner_id]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Redirect if partner not found
if (!$partner) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    // Sanitize inputs – Company Information
    $company_name          = htmlspecialchars(trim($_POST['company_name'] ?? ''));
    $company_legal_name    = htmlspecialchars(trim($_POST['company_legal_name'] ?? ''));
    $dba_fka               = htmlspecialchars(trim($_POST['dba_fka'] ?? ''));
    $company_hq_address    = htmlspecialchars(trim($_POST['company_hq_address'] ?? ''));
    $company_hq_city       = htmlspecialchars(trim($_POST['company_hq_city'] ?? ''));
    $company_hq_state      = htmlspecialchars(trim($_POST['company_hq_state'] ?? ''));
    $company_hq_zip_code   = htmlspecialchars(trim($_POST['company_hq_zip_code'] ?? ''));
    $company_phone_number  = htmlspecialchars(trim($_POST['company_phone_number'] ?? ''));
    $ein_number            = htmlspecialchars(trim($_POST['ein_number'] ?? ''));

    // Main Contact Section (split into name, email, phone)
    $main_contact_name     = htmlspecialchars(trim($_POST['main_contact_name'] ?? ''));
    $main_contact_email    = filter_var($_POST['main_contact_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $main_contact_phone    = htmlspecialchars(trim($_POST['main_contact_phone'] ?? ''));

    // Finance Contact Section
    $finance_contact_name  = htmlspecialchars(trim($_POST['finance_contact_name'] ?? ''));
    $finance_contact_email = filter_var($_POST['finance_contact_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $finance_contact_phone = htmlspecialchars(trim($_POST['finance_contact_phone_number'] ?? ''));

    // Technical Contact Section
    $technical_contact_name  = htmlspecialchars(trim($_POST['technical_contact_name'] ?? ''));
    $technical_contact_email = filter_var($_POST['technical_contact_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $technical_contact_phone = htmlspecialchars(trim($_POST['technical_contact_phone_number'] ?? ''));

    try {
        // Update partner record
        $update = $pdo->prepare("
            UPDATE partners
            SET
                company_name                    = :company_name,
                company_legal_name             = :company_legal_name,
                dba_fka                        = :dba_fka,
                company_hq_address             = :company_hq_address,
                company_hq_city                = :company_hq_city,
                company_hq_state               = :company_hq_state,
                company_hq_zip_code            = :company_hq_zip_code,
                company_phone_number           = :company_phone_number,
                ein_number                     = :ein_number,
                main_contact_name              = :main_contact_name,
                main_contact_email             = :main_contact_email,
                main_contact_phone             = :main_contact_phone,
                finance_contact_name           = :finance_contact_name,
                finance_contact_email          = :finance_contact_email,
                finance_contact_phone_number   = :finance_contact_phone,
                technical_contact_name         = :technical_contact_name,
                technical_contact_email        = :technical_contact_email,
                technical_contact_phone_number = :technical_contact_phone
            WHERE id = :partner_id
        ");

        $update->execute([
            ':company_name'           => $company_name,
            ':company_legal_name'     => $company_legal_name,
            ':dba_fka'                => $dba_fka,
            ':company_hq_address'     => $company_hq_address,
            ':company_hq_city'        => $company_hq_city,
            ':company_hq_state'       => $company_hq_state,
            ':company_hq_zip_code'    => $company_hq_zip_code,
            ':company_phone_number'   => $company_phone_number,
            ':ein_number'             => $ein_number,
            ':main_contact_name'      => $main_contact_name,
            ':main_contact_email'     => $main_contact_email,
            ':main_contact_phone'     => $main_contact_phone,
            ':finance_contact_name'   => $finance_contact_name,
            ':finance_contact_email'  => $finance_contact_email,
            ':finance_contact_phone'  => $finance_contact_phone,
            ':technical_contact_name' => $technical_contact_name,
            ':technical_contact_email'=> $technical_contact_email,
            ':technical_contact_phone'=> $technical_contact_phone,
            ':partner_id'             => $partner_id
        ]);

        $message = 'Partner information updated successfully.';

        // Reload updated partner details
        $stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id');
        $stmt->execute([':id' => $partner_id]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $message = 'Failed to update partner information. ' . $e->getMessage();
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="container">
        <div class="card">
            <h2>Edit Partner: <?php echo htmlspecialchars($partner['company_name'] ?? ''); ?></h2>
            <?php if ($message): ?>
                <p class="message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <!-- 2-column layout for editing partner -->
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Company Info -->
                    <div style="display: flex; flex-direction: column;">
                        <label for="company_name" style="font-weight:bold; margin-bottom:5px;">
                            Company Name:
                        </label>
                        <input type="text" id="company_name" name="company_name" required
                               value="<?php echo htmlspecialchars($partner['company_name'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="company_legal_name" style="font-weight:bold; margin-bottom:5px;">
                            Company Legal Name:
                        </label>
                        <input type="text" id="company_legal_name" name="company_legal_name" required
                               value="<?php echo htmlspecialchars($partner['company_legal_name'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <div style="display: flex; flex-direction: column;">
                        <label for="dba_fka" style="font-weight:bold; margin-bottom:5px;">
                            DBA / FKA:
                        </label>
                        <input type="text" id="dba_fka" name="dba_fka" required
                               value="<?php echo htmlspecialchars($partner['dba_fka'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="company_hq_address" style="font-weight:bold; margin-bottom:5px;">
                            Company HQ Address:
                        </label>
                        <input type="text" id="company_hq_address" name="company_hq_address" required
                               value="<?php echo htmlspecialchars($partner['company_hq_address'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <div style="display: flex; flex-direction: column;">
                        <label for="company_hq_city" style="font-weight:bold; margin-bottom:5px;">
                            Company HQ City:
                        </label>
                        <input type="text" id="company_hq_city" name="company_hq_city" required
                               value="<?php echo htmlspecialchars($partner['company_hq_city'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="company_hq_state" style="font-weight:bold; margin-bottom:5px;">
                            Company HQ State:
                        </label>
                        <select id="company_hq_state" name="company_hq_state" required
                                style="padding:10px; border:1px solid #ccc; border-radius:4px;">
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
                                $selected = ($partner['company_hq_state'] === $abbr) ? 'selected' : '';
                                echo "<option value='$abbr' $selected>$state</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column;">
                        <label for="company_hq_zip_code" style="font-weight:bold; margin-bottom:5px;">
                            Company HQ Zip Code:
                        </label>
                        <input type="text" id="company_hq_zip_code" name="company_hq_zip_code" required
                               value="<?php echo htmlspecialchars($partner['company_hq_zip_code'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="company_phone_number" style="font-weight:bold; margin-bottom:5px;">
                            Company Phone Number:
                        </label>
                        <input type="text" id="company_phone_number" name="company_phone_number" required
                               value="<?php echo htmlspecialchars($partner['company_phone_number'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <div style="display: flex; flex-direction: column;">
                        <label for="ein_number" style="font-weight:bold; margin-bottom:5px;">
                            EIN Number:
                        </label>
                        <input type="text" id="ein_number" name="ein_number" required
                               value="<?php echo htmlspecialchars($partner['ein_number'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <!-- Main Contact Section -->
                    <div style="grid-column: span 2; font-size:1.1rem; font-weight:bold; margin:20px 0 10px;">
                        Main Contact
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="main_contact_name" style="font-weight:bold; margin-bottom:5px;">
                            Name:
                        </label>
                        <input type="text" id="main_contact_name" name="main_contact_name" required
                               value="<?php echo htmlspecialchars($partner['main_contact_name'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="main_contact_email" style="font-weight:bold; margin-bottom:5px;">
                            Email:
                        </label>
                        <input type="email" id="main_contact_email" name="main_contact_email" required
                               value="<?php echo htmlspecialchars($partner['main_contact_email'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="grid-column: span 2; display: flex; flex-direction: column;">
                        <label for="main_contact_phone" style="font-weight:bold; margin-bottom:5px;">
                            Phone Number:
                        </label>
                        <input type="text" id="main_contact_phone" name="main_contact_phone" required
                               value="<?php echo htmlspecialchars($partner['main_contact_phone'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <!-- Finance Contact Section -->
                    <div style="grid-column: span 2; font-size:1.1rem; font-weight:bold; margin:20px 0 10px;">
                        Finance Contact
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="finance_contact_name" style="font-weight:bold; margin-bottom:5px;">
                            Name:
                        </label>
                        <input type="text" id="finance_contact_name" name="finance_contact_name" required
                               value="<?php echo htmlspecialchars($partner['finance_contact_name'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="finance_contact_email" style="font-weight:bold; margin-bottom:5px;">
                            Email:
                        </label>
                        <input type="email" id="finance_contact_email" name="finance_contact_email" required
                               value="<?php echo htmlspecialchars($partner['finance_contact_email'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="grid-column: span 2; display: flex; flex-direction: column;">
                        <label for="finance_contact_phone_number" style="font-weight:bold; margin-bottom:5px;">
                            Phone Number:
                        </label>
                        <input type="text" id="finance_contact_phone_number" name="finance_contact_phone_number" required
                               value="<?php echo htmlspecialchars($partner['finance_contact_phone_number'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <!-- Technical Contact Section -->
                    <div style="grid-column: span 2; font-size:1.1rem; font-weight:bold; margin:20px 0 10px;">
                        Technical Contact
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="technical_contact_name" style="font-weight:bold; margin-bottom:5px;">
                            Name:
                        </label>
                        <input type="text" id="technical_contact_name" name="technical_contact_name" required
                               value="<?php echo htmlspecialchars($partner['technical_contact_name'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <label for="technical_contact_email" style="font-weight:bold; margin-bottom:5px;">
                            Email:
                        </label>
                        <input type="email" id="technical_contact_email" name="technical_contact_email" required
                               value="<?php echo htmlspecialchars($partner['technical_contact_email'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="grid-column: span 2; display: flex; flex-direction: column;">
                        <label for="technical_contact_phone_number" style="font-weight:bold; margin-bottom:5px;">
                            Phone Number:
                        </label>
                        <input type="text" id="technical_contact_phone_number" name="technical_contact_phone_number" required
                               value="<?php echo htmlspecialchars($partner['technical_contact_phone_number'] ?? ''); ?>"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <!-- Save Changes Button -->
                    <button type="submit" style="grid-column: span 2; padding:10px; background:#0056b3; color:#fff; border:none; border-radius:4px; font-size:16px; cursor:pointer;">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
