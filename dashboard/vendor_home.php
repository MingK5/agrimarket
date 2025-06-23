<?php
require '../includes/session.php';
checkLogin(3);

include '../includes/header.php';
?>

<div style="padding: 40px;">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['username']) ?>!</h1>
    <h3>Vendor Dashboard</h3>
    <ul>
        <li><a href="/agrimarket/product/product_upload.php">Upload Product</a></li>
        <li><a href="/agrimarket/product/vendor_orders.php">View Orders</a></li>
        <li><a href="/agrimarket/profile/edit_profile.php">Edit Profile</a></li>
    </ul>
</div>

<?php include '../includes/footer.php';