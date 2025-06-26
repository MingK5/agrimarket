<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 1) {
    header("Location: ../index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $userId = $_POST['user_id'];
    
    // Delete user with userType_id = 2 (Staff)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND userType_Id = 2");
    $stmt->execute([$userId]);
    
    // Refresh page to show updated list
    header("Location: staff_management.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    $username        = trim($_POST['username']);
    $email           = trim($_POST['email']);
    $plainPassword   = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $passwordPattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@\$!%*#?&])[A-Za-z\d@\$!%*#?&]{8,}$/";

    // 1) Basic password checks
    if ($plainPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!preg_match($passwordPattern, $plainPassword)) {
        $error = "Password must be at least 8 characters long and include at least 1 uppercase, 1 lowercase, 1 digit, and 1 special character.";
    }

    // 2) Username/email uniqueness
    if (!$error) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            $error = "Username is already taken.";
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $error = "Email is already registered.";
        }
    }

    // 3) All good → hash & insert
    if (!$error) {
        // Hash password
        $stmt = $pdo->prepare("SELECT SHA2(?, 256) AS hp");
        $stmt->execute([$plainPassword]);
        $hashedPassword = $stmt->fetchColumn();

        // Insert into users with userType_id = 2 (Staff)
        $stmt = $pdo->prepare("
            INSERT INTO users
                (username, email, password, userType_Id)
            VALUES (?, ?, ?, 2)
        ");
        $stmt->execute([$username, $email, $hashedPassword]);

        // Refresh page to show updated list
        header("Location: staff_management.php");
        exit();
    }
}

// Fetch users with userType_id = 2
$stmt = $pdo->prepare("
    SELECT id, username, email, phone, address, created_at
    FROM users
    WHERE userType_Id = 2
    ORDER BY created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Management</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            padding: 40px;
        }
        dialog {
            width: 80%;
            max-width: 400px;
            margin: auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 20px;
        }
        dialog::backdrop {
            background: rgba(0,0,0,0.5);
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
        
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 45px;
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
        .dialog-buttons {
            display: flex;
            justify-content: space-between;
        }
        .toggle-form-btn {
            padding: 12px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        button[type="submit"] {
            padding: 12px;
            background-color: #2e7d32;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            width: 45%;
        }
        button[type="cancel-btn"] {
            padding: 12px;
            background-color: orange;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            width: 45%;
        }
        .cancel-btn {
            background-color: #d32f2f;
        }
        .delete-btn {
            padding: 8px 12px;
            background-color: #d32f2f;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
        }
        .delete-btn:hover {
            background-color: #b71c1c;
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
        .error-msg {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Staff Management</h1>

        <button class="toggle-form-btn" onclick="openDialog()">Add New Staff</button>

        <dialog id="addStaffDialog">
            <form method="POST" id="addStaffForm">
                <h2>Add New Staff</h2>

                <?php if (!empty($error)): ?>
                    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <input type="text" name="username"
                       placeholder="Username"
                       value="<?= htmlspecialchars($username ?? '') ?>"
                       required>

                <input type="email" name="email"
                       placeholder="Email"
                       value="<?= htmlspecialchars($email ?? '') ?>"
                       required>

                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <img src="../assets/close_eye.png"
                         class="toggle-icon"
                         onclick="togglePassword(this, 'password')">
                </div>

                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    <img src="../assets/close_eye.png"
                         class="toggle-icon"
                         onclick="togglePassword(this, 'confirm_password')">
                </div>

                <div class="dialog-buttons">
                    <button type="submit">Add Staff</button>
                    <button type="cancel-btn" onclick="closeDialog()">Cancel</button>
                </div>
            </form>
        </dialog>

        <?php if (empty($users)): ?>
            <p>No staff found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(icon, inputId) {
            const input = document.getElementById(inputId);
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.src = isHidden
                ? '../assets/open_eye.png'
                : '../assets/close_eye.png';
        }

        function openDialog() {
            document.getElementById('addStaffDialog').showModal();
        }

        function closeDialog() {
            document.getElementById('addStaffDialog').close();
        }

        // Open dialog if there's an error
        <?php if (!empty($error)): ?>
            window.addEventListener('DOMContentLoaded', () => {
                openDialog();
            });
        <?php endif; ?>
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>