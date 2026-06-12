<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and is an admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: /login.php');
    exit;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // Sanitize inputs – Company Information
    $company_name          = htmlspecialchars(trim($_POST['company_name']));
    $company_legal_name    = htmlspecialchars(trim($_POST['company_legal_name']));
    $dba_fka               = htmlspecialchars(trim($_POST['dba_fka']));
    $company_hq_address    = htmlspecialchars(trim($_POST['company_hq_address']));
    $company_hq_city       = htmlspecialchars(trim($_POST['company_hq_city']));
    $company_hq_state      = htmlspecialchars(trim($_POST['company_hq_state']));
    $company_hq_zip_code   = htmlspecialchars(trim($_POST['company_hq_zip_code']));
    $company_phone_number  = htmlspecialchars(trim($_POST['company_phone_number']));
    $ein_number            = htmlspecialchars(trim($_POST['ein_number']));
    
    // Main Contact Section
    $main_contact_name  = htmlspecialchars(trim($_POST['main_contact_name']));
    $main_contact_email = filter_var($_POST['main_contact_email'], FILTER_SANITIZE_EMAIL);
    $main_contact_phone = htmlspecialchars(trim($_POST['main_contact_phone']));
    
    // Finance Contact Section
    $finance_contact_name          = htmlspecialchars(trim($_POST['finance_contact_name']));
    $finance_contact_email         = filter_var($_POST['finance_contact_email'], FILTER_SANITIZE_EMAIL);
    $finance_contact_phone_number  = htmlspecialchars(trim($_POST['finance_contact_phone_number']));
    
    // Technical Contact Section
    $technical_contact_name         = htmlspecialchars(trim($_POST['technical_contact_name']));
    $technical_contact_email        = filter_var($_POST['technical_contact_email'], FILTER_SANITIZE_EMAIL);
    $technical_contact_phone_number = htmlspecialchars(trim($_POST['technical_contact_phone_number']));
    
    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare(
            'INSERT INTO partners (
                company_name, company_legal_name, dba_fka,
                company_hq_address, company_hq_city, company_hq_state, company_hq_zip_code,
                company_phone_number, ein_number,
                main_contact_name, main_contact_email, main_contact_phone,
                finance_contact_name, finance_contact_email, finance_contact_phone_number,
                technical_contact_name, technical_contact_email, technical_contact_phone_number
            ) VALUES (
                :company_name, :company_legal_name, :dba_fka,
                :company_hq_address, :company_hq_city, :company_hq_state, :company_hq_zip_code,
                :company_phone_number, :ein_number,
                :main_contact_name, :main_contact_email, :main_contact_phone,
                :finance_contact_name, :finance_contact_email, :finance_contact_phone_number,
                :technical_contact_name, :technical_contact_email, :technical_contact_phone_number
            )'
        );
        
        $stmt->execute([
            ':company_name'                     => $company_name,
            ':company_legal_name'               => $company_legal_name,
            ':dba_fka'                          => $dba_fka,
            ':company_hq_address'               => $company_hq_address,
            ':company_hq_city'                  => $company_hq_city,
            ':company_hq_state'                 => $company_hq_state,
            ':company_hq_zip_code'              => $company_hq_zip_code,
            ':company_phone_number'             => $company_phone_number,
            ':ein_number'                       => $ein_number,
            ':main_contact_name'                => $main_contact_name,
            ':main_contact_email'               => $main_contact_email,
            ':main_contact_phone'               => $main_contact_phone,
            ':finance_contact_name'             => $finance_contact_name,
            ':finance_contact_email'            => $finance_contact_email,
            ':finance_contact_phone_number'     => $finance_contact_phone_number,
            ':technical_contact_name'           => $technical_contact_name,
            ':technical_contact_email'          => $technical_contact_email,
            ':technical_contact_phone_number'   => $technical_contact_phone_number
        ]);
        
        $success = 'Partner account created successfully!';
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>

<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        <h2 style="margin-bottom:20px;">Create Partner Account</h2>
        
        <!-- Display success or error messages -->
        <?php if (!empty($success)): ?>
            <div class="card" style="margin-bottom:20px; padding:15px; background:#d4edda; color:#155724;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="card" style="margin-bottom:20px; padding:15px; background:#f8d7da; color:#721c24;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Single Form for All Sections -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Card: Company Information -->
            <div class="card" style="text-align:left; margin-bottom:20px; padding:20px;">
                <h3 style="margin-top:0;">Company Information</h3>
                <div style="
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 20px; 
                    margin-top:10px;
                ">
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_name" style="font-weight:bold; margin-bottom:5px;">Company Name:</label>
                        <input type="text" id="company_name" name="company_name" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_legal_name" style="font-weight:bold; margin-bottom:5px;">Company Legal Name:</label>
                        <input type="text" id="company_legal_name" name="company_legal_name" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <div style="display:flex; flex-direction:column;">
                        <label for="dba_fka" style="font-weight:bold; margin-bottom:5px;">DBA / FKA:</label>
                        <input type="text" id="dba_fka" name="dba_fka"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_hq_address" style="font-weight:bold; margin-bottom:5px;">Company HQ Address:</label>
                        <input type="text" id="company_hq_address" name="company_hq_address" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_hq_city" style="font-weight:bold; margin-bottom:5px;">Company HQ City:</label>
                        <input type="text" id="company_hq_city" name="company_hq_city" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_hq_state" style="font-weight:bold; margin-bottom:5px;">Company HQ State:</label>
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
                                echo "<option value='$abbr'>$state</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_hq_zip_code" style="font-weight:bold; margin-bottom:5px;">Company HQ Zip Code:</label>
                        <input type="text" id="company_hq_zip_code" name="company_hq_zip_code" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="company_phone_number" style="font-weight:bold; margin-bottom:5px;">Company Phone Number:</label>
                        <input type="text" id="company_phone_number" name="company_phone_number" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <div style="display:flex; flex-direction:column;">
                        <label for="ein_number" style="font-weight:bold; margin-bottom:5px;">EIN Number:</label>
                        <input type="text" id="ein_number" name="ein_number"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>
            </div>

            <!-- Card: Main Contact -->
            <div class="card" style="text-align:left; margin-bottom:20px; padding:20px;">
                <h3 style="margin-top:0;">Main Contact</h3>
                <div style="
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 20px; 
                    margin-top:10px;
                ">
                    <div style="display:flex; flex-direction:column;">
                        <label for="main_contact_name" style="font-weight:bold; margin-bottom:5px;">Name:</label>
                        <input type="text" id="main_contact_name" name="main_contact_name" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="main_contact_email" style="font-weight:bold; margin-bottom:5px;">Email:</label>
                        <input type="email" id="main_contact_email" name="main_contact_email" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="grid-column: span 2; display:flex; flex-direction:column;">
                        <label for="main_contact_phone" style="font-weight:bold; margin-bottom:5px;">Phone Number:</label>
                        <input type="text" id="main_contact_phone" name="main_contact_phone"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>
            </div>

            <!-- Card: Finance Contact -->
            <div class="card" style="text-align:left; margin-bottom:20px; padding:20px;">
                <h3 style="margin-top:0;">Finance Contact</h3>
                <div style="
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 20px; 
                    margin-top:10px;
                ">
                    <div style="display:flex; flex-direction:column;">
                        <label for="finance_contact_name" style="font-weight:bold; margin-bottom:5px;">Name:</label>
                        <input type="text" id="finance_contact_name" name="finance_contact_name" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="finance_contact_email" style="font-weight:bold; margin-bottom:5px;">Email:</label>
                        <input type="email" id="finance_contact_email" name="finance_contact_email" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="grid-column: span 2; display:flex; flex-direction:column;">
                        <label for="finance_contact_phone_number" style="font-weight:bold; margin-bottom:5px;">Phone Number:</label>
                        <input type="text" id="finance_contact_phone_number" name="finance_contact_phone_number"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>
            </div>

            <!-- Card: Technical Contact -->
            <div class="card" style="text-align:left; margin-bottom:20px; padding:20px;">
                <h3 style="margin-top:0;">Technical Contact</h3>
                <div style="
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    gap: 20px; 
                    margin-top:10px;
                ">
                    <div style="display:flex; flex-direction:column;">
                        <label for="technical_contact_name" style="font-weight:bold; margin-bottom:5px;">Name:</label>
                        <input type="text" id="technical_contact_name" name="technical_contact_name" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label for="technical_contact_email" style="font-weight:bold; margin-bottom:5px;">Email:</label>
                        <input type="email" id="technical_contact_email" name="technical_contact_email" required
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="grid-column: span 2; display:flex; flex-direction:column;">
                        <label for="technical_contact_phone_number" style="font-weight:bold; margin-bottom:5px;">Phone Number:</label>
                        <input type="text" id="technical_contact_phone_number" name="technical_contact_phone_number"
                               style="padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>
            </div>

            <!-- Final Submit Button -->
            <div style="text-align:center; margin-bottom:40px;">
                <button type="submit" style="
                    padding:10px 20px; 
                    background:#0056b3; 
                    color:#fff; 
                    border:none; 
                    border-radius:4px; 
                    font-size:16px; 
                    cursor:pointer;
                ">
                    Create Partner Account
                </button>
            </div>
        </form>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
