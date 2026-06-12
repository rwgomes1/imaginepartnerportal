<?php
session_start();
require_once 'includes/config.php';
require_once 'vendor/autoload.php';

$error = '';

// Process login when the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = trim(htmlspecialchars($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Fetch user record by username
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify that the user exists and the password is correct
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if the user must set up two-factor authentication first
            if ((int)$user['force_2fa_setup'] === 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                header('Location: /admin/security/setup_2fa.php');
                exit;
            }
            
            // Check if two-factor authentication is enabled
            if ((int)$user['twofa_enabled'] === 1) {
                // Store temporary user data for 2FA verification
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_user_role'] = $user['role'];
                header('Location: /admin/security/2fa_verify.php');
                exit;
            }
            
            // Log successful login in firewall_logs
            $logStmt = $pdo->prepare("
                INSERT INTO firewall_logs (event_type, ip_address, user_id, details)
                VALUES ('LOGIN_SUCCESS', :ip, :user_id, 'User logged in successfully')
            ");
            $logStmt->execute([
                ':ip'      => $_SERVER['REMOTE_ADDR'],
                ':user_id' => $user['id']
            ]);
            
            // Update the last_login timestamp for the user
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);
            
            // Set session variables for a full login
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];
            
            // Redirect user based on their role
            $redirects = [
                'partner'    => '/partners/dashboard.php',
                'admin'      => '/admin/dashboard.php',
                'superadmin' => '/admin/dashboard.php',
                'user'       => '/index.php'
            ];
            header('Location: ' . ($redirects[$user['role']] ?? '/index.php'));
            exit;
        } else {
            // Log unsuccessful login attempt
            $logStmt = $pdo->prepare("
                INSERT INTO firewall_logs (event_type, ip_address, details)
                VALUES ('LOGIN_FAIL', :ip, 'Unsuccessful login attempt for username: {$username}')
            ");
            $logStmt->execute([
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $error = 'Invalid username or password.';
        }
    } catch (PDOException $e) {
        error_log('DB error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?> <!-- If you want the sidebar on login, uncomment this line -->

    <div class="container">
        <div class="card">
            <h2>Login</h2>
            <?php if (!empty($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST">
                <label for="username">Username:</label>
                <input type="text" name="username" required>

                <label for="password">Password:</label>
                <input type="password" name="password" required>

                <button type="submit">Login</button>
            </form>
        </div>
    </div>

    <div style="margin-top:15px; text-align:center;">
        <a href="forgot_username.php" style="color:#007bff; text-decoration:none;">Forgot Username</a> |
        <a href="forgot_password.php" style="color:#007bff; text-decoration:none;">Forgot Password</a>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
