<?php
require '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $plainPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name']);

    $passwordPattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/";
    if ($plainPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!preg_match($passwordPattern, $plainPassword)) {
        $error = "Password must be at least 8 characters long and include at least 1 uppercase, 1 lowercase, 1 digit, and 1 special character.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $usernameExists = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = $stmt->fetchColumn();

        if ($usernameExists) {
            $error = "Username is already taken.";
        } elseif ($emailExists) {
            $error = "Email is already registered.";
        } else {
            $stmt = $pdo->prepare("SELECT SHA2(?, 256) AS hashed_password");
            $stmt->execute([$plainPassword]);
            $hashedPassword = $stmt->fetchColumn();

            $prefix = ($role === 'customer') ? 'C' : (($role === 'vendor') ? 'V' : 'U');

            $stmt = $pdo->prepare("SELECT public_id FROM users WHERE role = ? AND public_id IS NOT NULL ORDER BY id DESC LIMIT 1");
            $stmt->execute([$role]);
            $lastUserId = $stmt->fetchColumn();

            if ($lastUserId && preg_match("/{$prefix}(\d+)/", $lastUserId, $matches)) {
                $newNumber = str_pad($matches[1] + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '001';
            }

            $newUserId = $prefix . $newNumber;

            $stmt = $pdo->prepare("INSERT INTO users (public_id, username, email, password, role, full_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newUserId, $username, $email, $hashedPassword, $role, $full_name]);

            $userId = $pdo->lastInsertId();

            if ($role === 'customer') {
                $pdo->prepare("INSERT INTO customers (user_id) VALUES (?)")->execute([$userId]);
            } elseif ($role === 'vendor') {
                $pdo->prepare("INSERT INTO vendors (user_id) VALUES (?)")->execute([$userId]);
            }

            header("Location: login_customer_vendor.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            height: 100vh;
        }
        .left {
            width: 50%;
            background: url('../assets/veggie.jpg') center/cover no-repeat;
        }
        .right {
            width: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .box {
            width: 80%;
            max-width: 400px;
        }
        input, select {
            width: 100%;
            padding: 12px 42px 12px 15px; /* enough right-padding for the eye icon */
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 25px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 45px; /* more room for eye icon */
        }
        .toggle-icon {
            position: absolute;
            right: 18px;
            top: 10px; 
            width: 22px;
            height: 22px;
            cursor: pointer;
            opacity: 0.8;
        }
        .toggle-icon:hover {
            opacity: 1;
        }
        button[type="submit"] {
            display: block;
            margin: 0 auto 15px auto;
            width: 60%;
            padding: 12px;
            background-color: #2e7d32;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 25px;
            cursor: pointer;
        }
        .logo {
            display: block;
            margin: 0 auto 10px auto;
            height: 80px;
        }
        h2 {
            text-align: center;
            margin-top: 20px;
        }
        .link-group p {
            text-align: center;
            margin: 5px 0;
        }
        .error-msg {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="left"></div>
<div class="right">
    <form method="POST" class="box">
        <img src="../assets/logo.jpg" class="logo" alt="Logo">
        <h2>Register Account</h2>

        <?php if (!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>

        <select name="role" required>
            <option value="">-- Select Role --</option>
            <option value="customer" <?= (isset($role) && $role == 'customer') ? 'selected' : '' ?>>Customer</option>
            <option value="vendor" <?= (isset($role) && $role == 'vendor') ? 'selected' : '' ?>>Vendor</option>
        </select>

        <input type="text" name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($full_name ?? '') ?>" required>
        <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($username ?? '') ?>" required>
        <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($email ?? '') ?>" required>

        <div class="password-wrapper">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <img src="../assets/close_eye.png" class="toggle-icon" onclick="togglePassword(this, 'password')">
        </div>

        <div class="password-wrapper">
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            <img src="../assets/close_eye.png" class="toggle-icon" onclick="togglePassword(this, 'confirm_password')">
        </div>

        <button type="submit">Sign Up</button>

        <div class="link-group">
            <p><a href="login_customer_vendor.php">Already have an account? Login</a></p>
            <p><a href="../index.php">Return to Home</a></p>
        </div>
    </form>
</div>

<script>
function togglePassword(icon, inputId) {
    const input = document.getElementById(inputId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.src = isHidden ? '../assets/open_eye.png' : '../assets/close_eye.png';
}
</script>
</body>
</html>
