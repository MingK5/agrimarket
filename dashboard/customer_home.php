<?php
require '../includes/session.php';
checkLogin('customer');

include '../includes/header.php';
?>

<div style="padding: 40px;">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?>!</h1>
    <h3>Customer Dashboard</h3>
    <ul>
        <li><a href="/agrimarket/product/product.php">Browse Products</a></li>
        <li><a href="/agrimarket/customer_orders.php">My Orders</a></li>
        <li><a href="/agrimarket/profile/edit_profile.php">Edit Profile</a></li>
    </ul>
</div>

<?php include '../includes/footer.php';