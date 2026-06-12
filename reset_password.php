<?php
session_start();
require_once 'includes/config.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$message = '';

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        // Hash the new password and update the user record
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users
            SET password_hash = :hash, forced_password_reset = 0
            WHERE id = :id
        ");
        $stmt->execute([
            ':hash' => $hashed,
            ':id'   => $_SESSION['user_id']
        ]);
        
        // Redirect to the appropriate page after successful reset
        header('Location: /index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Your Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Optional inline styling for a cleaner look */
        .container {
            max-width: 500px;
            margin: 40px auto;
        }
        .card {
            padding: 20px;
            text-align: left;
        }
        .card h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .card label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .card input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .card button {
            width: 100%;
            padding: 10px;
            background-color: #0056b3;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .card button:hover {
            background-color: #003f8c;
        }
        .error-message {
            color: red;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="card">
            <h2>Reset Your Password</h2>
            <?php if ($message): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <form method="POST">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>

                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <button type="submit">Reset Password</button>
            </form>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
