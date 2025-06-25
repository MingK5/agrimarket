<?php
session_start();
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $inputPassword = $_POST['password'];
    $userType_Id = $_POST['active_role'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND userType_Id = ?");
    $stmt->execute([$email, $userType_Id]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("SELECT SHA2(?, 256) AS hashed");
        $stmt->execute([$inputPassword]);
        $hashedInput = $stmt->fetchColumn();

        if ($hashedInput === $user['password']) {
            $_SESSION['user'] = $user;
            if ($user['userType_Id'] === 4) {
                header("Location: ../index.php");
            } elseif ($user['userType_Id'] === 3) {
                header("Location: ../index.php");
            } else {
                header("Location: ../unauthorized.php");
            }
            exit();
        }
    }

    $error = "Invalid credentials.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer/Vendor Login</title>
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
        .slider {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .slider button {
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            margin: 0 5px;
            font-weight: bold;
            cursor: pointer;
            background-color: #eee;
        }
        .slider button.active {
            background-color: orange;
            color: white;
        }
        input {
            width: 100%;
            padding: 12px 42px 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 25px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .input-group {
            position: relative;
        }
        .toggle-icon {
            position: absolute;
            right: 15px;
            top: 38%;
            transform: translateY(-50%);
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
            margin-top: 50px;
        }
        .error-msg {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
        .link-group p {
            text-align: center;
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class="left"></div>
<div class="right">
    <form method="POST" class="box">
        <img src="../assets/logo.jpg" class="logo" alt="Logo">
        <h2 id="formTitle">Customer Login</h2>

        <div class="slider">
            <button type="button" id="btn_customer" class="active" onclick="setRole(4)">Customer</button>
            <button type="button" id="btn_vendor" onclick="setRole(3)">Vendor</button>
        </div>

        <input type="hidden" name="active_role" id="active_role" value=4>
        <input type="email" name="email" placeholder="Email" required>

        <div class="input-group">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <img src="../assets/close_eye.png" class="toggle-icon" onclick="togglePassword(this, 'password')">
        </div>

        <button type="submit">Sign In</button>

        <?php if (!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>

        <div class="link-group">
            <p><a href="register_customer_vendor.php">Don't have an account? Sign up</a></p>
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

function setRole(userType_Id) {
    document.getElementById('active_role').value = userType_Id;
    document.getElementById('btn_customer').classList.remove('active');
    document.getElementById('btn_vendor').classList.remove('active');
    if (userType_Id === 3)
    {
        document.getElementById('btn_vendor').classList.add('active');
        document.getElementById('formTitle').textContent = "Vendor Login";
    } else 
    {
        document.getElementById('btn_customer').classList.add('active');
        document.getElementById('formTitle').textContent = "Customer Login";
    }
}
</script>
</body>
</html>
