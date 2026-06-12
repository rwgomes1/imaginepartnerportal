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
$error = '';
$user_id = $_SESSION['user_id'];

// Fetch current user info
$stmt = $pdo->prepare("SELECT username, name, email FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: /logout.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get updated profile data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Check if user wants to update their password
    $updatePassword = false;
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($currentPassword || $newPassword || $confirmPassword) {
        // All three fields are required for a password change
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'To change your password, please fill in all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            $userPasswordData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userPasswordData && password_verify($currentPassword, $userPasswordData['password_hash'])) {
                $updatePassword = true;
                $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
    
    if (!$error) {
        // Update profile information (and password if requested)
        if ($updatePassword) {
            $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':id' => $user_id
            ]);
            $message = 'Profile and password updated successfully.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':id' => $user_id
            ]);
            $message = 'Profile updated successfully.';
        }
        
        // Refresh user data for display
        $stmt = $pdo->prepare("SELECT username, name, email FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 600px;
            margin: 40px auto;
        }
        .card {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
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
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .card button {
            width: 100%;
            padding: 10px;
            background: #0056b3;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .card button:hover {
            background: #003f8c;
        }
        .success-message {
            color: green;
            text-align: center;
        }
        .error-message {
            color: red;
            text-align: center;
        }
        hr {
            margin: 20px 0;
        }
        .card h3 {
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="card">
            <h2>My Profile</h2>
            <?php if ($message): ?>
                <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST">
                <label for="username">Username (cannot be changed):</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                
                <label for="name">Full Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                <hr>
                <h3>Change Password</h3>
                <p style="text-align: center; font-size: 14px; color: #666;">Leave blank if you do not want to change your password.</p>

                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password">

                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password">

                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password">

                <button type="submit">Update Profile</button>
            </form>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
