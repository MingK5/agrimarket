<?php
require '../includes/session.php';
checkLogin(2);

include '../includes/header.php';
?>

<div style="padding: 40px;">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['username']) ?>!</h1>
    <h3>Staff Dashboard</h3>
    <ul>
        <li><a href="/agrimarket/tasks.php">My Tasks</a></li>
        <li><a href="/agrimarket/profile/edit_profile.php">Edit Profile</a></li>
        <li><a href="/agrimarket/vendor_tracking.php">Track Vendor Activities</a></li>
    </ul>
</div>

<?php include '../includes/footer.php';