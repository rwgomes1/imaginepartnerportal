<?php
session_start();
require_once '../../includes/config.php';

// Check if the user is logged in and has appropriate permissions
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

    // Sanitize inputs
    $username   = trim(htmlspecialchars($_POST['username']));
    $user_name  = trim(htmlspecialchars($_POST['user_name']));
    $user_email = filter_var($_POST['user_email'], FILTER_SANITIZE_EMAIL);
    $user_role  = htmlspecialchars(trim($_POST['user_role']));
    $password   = $_POST['password'];

    // Validate required fields
    if (empty($username) || empty($password)) {
        $error = 'Username and Password are required.';
    } else {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $checkStmt->execute([':username' => $username]);
            if ($checkStmt->fetch()) {
                $error = 'Username already exists. Please choose another.';
            } else {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user, forcing password reset on first login
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, name, email, role, forced_password_reset)
                    VALUES (:username, :password_hash, :name, :email, :role, 1)
                ");
                $stmt->execute([
                    ':username'      => $username,
                    ':password_hash' => $password_hash,
                    ':name'          => $user_name,
                    ':email'         => $user_email,
                    ':role'          => $user_role
                ]);

                $success = 'User added successfully! They will be prompted to reset their password on first login.';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content">
    <div class="container">
        <div class="card">
            <h2>Add New User</h2>
            <?php if (!empty($success)): ?>
                <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
            <?php elseif (!empty($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Temporary Password:</label>
                <input type="password" id="password" name="password" required>

                <label for="user_name">Full Name:</label>
                <input type="text" id="user_name" name="user_name" required>

                <label for="user_email">Email:</label>
                <input type="email" id="user_email" name="user_email" required>

                <label for="user_role">Role:</label>
                <select id="user_role" name="user_role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="superadmin">Super Admin</option>
                    <option value="partner">Partner</option>
                    <option value="user">User</option>
                </select>

                <button type="submit" class="button">Add User</button>
            </form>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
