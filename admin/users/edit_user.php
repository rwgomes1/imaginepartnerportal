<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and has appropriate permissions (admin or superadmin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: /login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Get user_id from query string
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    die("Invalid user ID.");
}
$userId = intval($_GET['user_id']);

// Retrieve user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Determine if current admin can change role and partner association (only superadmins can change these)
$canChangeRole = ($_SESSION['role'] === 'superadmin');

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // Basic fields
    $name  = htmlspecialchars(trim($_POST['name']));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // For role and partner, if allowed
    if ($canChangeRole) {
        $role = $_POST['role'] ?? $user['role'];
        // Use the partner dropdown value; if empty or "0", set to null.
        $partnerInput = $_POST['partner_id'] ?? '';
        $partnerId = ($partnerInput === '' || $partnerInput == '0') ? null : intval($partnerInput);
    } else {
        $role = $user['role'];
        $partnerId = $user['partner_id'];
    }
    
    // 2FA Toggle: Set force_2fa_setup based on checkbox
    $force2FA = isset($_POST['require_2fa']) ? 1 : 0;
    
    // Process temporary password change if provided
    $updatePassword = false;
    $newTemp = $_POST['new_temp_password'] ?? '';
    $confirmTemp = $_POST['confirm_temp_password'] ?? '';
    if (!empty($newTemp) || !empty($confirmTemp)) {
        if (empty($newTemp) || empty($confirmTemp)) {
            $error = 'To change the password, please fill in both temporary password fields.';
        } elseif ($newTemp !== $confirmTemp) {
            $error = 'Temporary password and confirmation do not match.';
        } elseif (strlen($newTemp) < 8) {
            $error = 'Temporary password must be at least 8 characters long.';
        } else {
            $tempHash = password_hash($newTemp, PASSWORD_DEFAULT);
            $updatePassword = true;
        }
    }
    
    if (!$error) {
        // Build the update query dynamically
        $updateFields = "name = :name, email = :email, role = :role, partner_id = :partner_id, force_2fa_setup = :force2fa";
        $params = [
            ':name'       => $name,
            ':email'      => $email,
            ':role'       => $role,
            ':partner_id' => $partnerId,
            ':force2fa'   => $force2FA,
            ':id'         => $userId
        ];
        if ($updatePassword) {
            $updateFields .= ", password_hash = :password_hash, forced_password_reset = 1";
            $params[':password_hash'] = $tempHash;
        }
        
        $stmt = $pdo->prepare("UPDATE users SET $updateFields WHERE id = :id");
        $stmt->execute($params);
        $success = "User updated successfully.";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content">
    <div class="container">
        <div class="card">
            <h2>Edit User</h2>
            <?php if ($success): ?>
                <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                <?php if ($canChangeRole): ?>
                    <label for="role">Role:</label>
                    <select id="role" name="role">
                        <option value="partner" <?php if ($user['role'] === 'partner') echo 'selected'; ?>>Partner</option>
                        <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                        <option value="superadmin" <?php if ($user['role'] === 'superadmin') echo 'selected'; ?>>SuperAdmin</option>
                    </select>

                    <label for="partner_id">Partner:</label>
                    <select id="partner_id" name="partner_id">
                        <option value="">None</option>
                        <?php
                        // Retrieve all partners ordered by company_name
                        $partnerStmt = $pdo->query("SELECT id, company_name FROM partners ORDER BY company_name ASC");
                        while ($p = $partnerStmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($p['id'] == $user['partner_id']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($p['id']) . "\" $selected>" . htmlspecialchars($p['company_name']) . "</option>";
                        }
                        ?>
                    </select>
                <?php else: ?>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
                <?php endif; ?>

                <!-- 2FA Toggle -->
                <label for="require_2fa">Require Two-Factor Authentication (2FA):</label>
                <input type="checkbox" id="require_2fa" name="require_2fa" value="1" <?php echo ($user['force_2fa_setup'] == 1) ? 'checked' : ''; ?>>

                <hr>
                <h3>Reset Temporary Password (Optional)</h3>
                <p style="text-align: center; font-size: 14px; color: #666;">
                    Leave these fields blank if you do not wish to reset the password.
                </p>
                <label for="new_temp_password">New Temporary Password:</label>
                <input type="password" id="new_temp_password" name="new_temp_password">

                <label for="confirm_temp_password">Confirm Temporary Password:</label>
                <input type="password" id="confirm_temp_password" name="confirm_temp_password">

                <button type="submit" class="button">Save Changes</button>
            </form>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
