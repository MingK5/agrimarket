<?php
require '../includes/session.php';
checkLogin('admin');

include '../includes/header.php';
?>

<div style="padding: 40px;">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?>!</h1>
    <h3>Admin Dashboard</h3>
    <ul>
        <li><a href="/agrimarket/admin_users.php">Manage Users</a></li>
        <li><a href="/agrimarket/reports.php">View Reports</a></li>
        <li><a href="/agrimarket/tasks.php">Assign Tasks to Staff</a></li>
    </ul>
</div>

<?php include '../includes/footer.php';