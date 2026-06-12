<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and is admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: /login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (!isset($_GET['partner_id']) || !is_numeric($_GET['partner_id'])) {
    die("Invalid partner ID.");
}
$partnerId = intval($_GET['partner_id']);

// Retrieve partner info
$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = :id");
$stmt->execute([':id' => $partnerId]);
$partner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    die("Partner not found.");
}

// Only show if application is pending
if ($partner['application_status'] !== 'Pending Application') {
    die("This application is not pending or has already been processed.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $tempPassword = trim($_POST['temp_password']);
        $chosenType   = trim($_POST['partner_type']);
        if (strlen($tempPassword) < 8) {
            $error = "Temporary password must be at least 8 characters.";
        } elseif ($chosenType === '') {
            $error = "Please select a Partner Type before approving.";
        } else {
            // Update partner record with approved status, chosen type, and capture approval details
            $updateStmt = $pdo->prepare("
                UPDATE partners
                SET application_status = 'Approved',
                    partner_type       = :ptype,
                    approved_at        = NOW(),
                    approved_by        = :approver
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':ptype'    => $chosenType,
                ':approver' => $_SESSION['user_name'],
                ':id'       => $partnerId
            ]);

            // Create user for this partner using main_contact_email as username
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $userStmt = $pdo->prepare("
                INSERT INTO users (username, password_hash, name, email, role, partner_id, forced_password_reset)
                VALUES (:username, :password_hash, :name, :email, 'partner', :partner_id, 1)
            ");
            $userStmt->execute([
                ':username'      => $partner['main_contact_email'],
                ':password_hash' => $passwordHash,
                ':name'          => $partner['main_contact_name'],
                ':email'         => $partner['main_contact_email'],
                ':partner_id'    => $partnerId
            ]);

            $success = "Partner application approved and user created successfully!";
        }
    } elseif (isset($_POST['deny'])) {
        $updateStmt = $pdo->prepare("UPDATE partners SET application_status = 'Denied' WHERE id = :id");
        $updateStmt->execute([':id' => $partnerId]);
        $success = "Partner application denied.";
    }
    // Refresh partner data
    $stmt->execute([':id' => $partnerId]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Include unified header and sidebar
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pending Partner Details</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: auto;
        }
        .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .center {
            text-align: center;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .approval-form {
            text-align: center;
        }
        .approval-form input[type="password"],
        .approval-form select {
            padding: 10px;
            width: 80%;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .approval-form button {
            padding: 10px 20px;
            background: #0056b3;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            margin: 5px;
            cursor: pointer;
        }
        .approval-form button.delete-button {
            background: #c00;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Top Card: Company Information -->
    <div class="card">
        <h2 class="center">Company Information</h2>
        <p><strong>Company Name:</strong> <?php echo htmlspecialchars($partner['company_name']); ?></p>
        <p><strong>Company Legal Name:</strong> <?php echo htmlspecialchars($partner['company_legal_name']); ?></p>
        <p><strong>DBA / FKA:</strong> <?php echo htmlspecialchars($partner['dba_fka']); ?></p>
        <p><strong>HQ Address:</strong> <?php echo htmlspecialchars($partner['company_hq_address']); ?></p>
        <p><strong>City:</strong> <?php echo htmlspecialchars($partner['company_hq_city']); ?></p>
        <p><strong>State:</strong> <?php echo htmlspecialchars($partner['company_hq_state']); ?></p>
        <p><strong>Zip Code:</strong> <?php echo htmlspecialchars($partner['company_hq_zip_code']); ?></p>
        <p><strong>Company Phone:</strong> <?php echo htmlspecialchars($partner['company_phone_number']); ?></p>
        <p><strong>EIN:</strong> <?php echo htmlspecialchars($partner['ein_number']); ?></p>
    </div>

    <!-- Contact Information: 3 Cards in a Grid -->
    <div class="grid-3">
        <!-- Main Contact -->
        <div class="card">
            <h3 class="center">Main Contact</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($partner['main_contact_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($partner['main_contact_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['main_contact_phone']); ?></p>
        </div>
        <!-- Finance Contact -->
        <div class="card">
            <h3 class="center">Finance Contact</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($partner['finance_contact_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($partner['finance_contact_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['finance_contact_phone_number']); ?></p>
        </div>
        <!-- Technical Contact -->
        <div class="card">
            <h3 class="center">Technical Contact</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($partner['technical_contact_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($partner['technical_contact_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($partner['technical_contact_phone_number']); ?></p>
        </div>
    </div>

    <!-- Approval / Denial Form Card -->
    <div class="card approval-form">
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($partner['application_status'] === 'Pending Application'): ?>
            <h3>Approve or Deny Application</h3>
            <form method="POST">
                <label for="temp_password">Temporary Password (8+ characters):</label><br>
                <input type="password" id="temp_password" name="temp_password" placeholder="Required if approving" required>
                <br><br>
                <label for="partner_type">Select Partner Type:</label><br>
                <select id="partner_type" name="partner_type" required>
                    <option value="">-- Select Partner Type --</option>
                    <option value="Approved Partner">Approved Partner</option>
                    <option value="Channel Partner">Channel Partner</option>
                    <option value="Integrated Partner">Integrated Partner</option>
                    <option value="Certified Partner">Certified Partner</option>
                    <option value="Preferred Partner">Preferred Partner</option>
                </select>
                <br><br>
                <button type="submit" name="approve" class="button">Approve</button>
                <button type="submit" name="deny" class="button delete-button">Deny</button>
            </form>
        <?php else: ?>
            <p><strong>Application Status:</strong> <?php echo htmlspecialchars($partner['application_status']); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>
