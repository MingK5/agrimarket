<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 3) {
    header("Location: ../index.php");
    exit();
}

$vendorId = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM sales_order WHERE vendor_id = ? ORDER BY created_date DESC");
$stmt->execute([$vendorId]);
$orders = $stmt->fetchAll();
$statusOptions = ['Confirmed', 'Packed', 'Shipped', 'Delivered'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['status'];
    if (in_array($newStatus, $statusOptions)) {
        $stmt = $pdo->prepare("UPDATE sales_order SET status = ? WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$newStatus, $orderId, $vendorId]);
    }
    header("Location: vendor_orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vendor Orders</title>
    <style>
        .order-container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; }
        .order { border-bottom: 1px solid #ccc; padding: 15px 0; }
        .order-details { margin-bottom: 10px; }
        .order-details strong { color: #2d6f2d; }
        .status-update { margin-top: 10px; }
        .status-update select { padding: 5px; font-size: 14px; border-radius: 4px; }
        .status-update button { padding: 5px 10px; background-color: #2d6f2d; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        .status-update button:hover { background-color: #1a5c1a; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="order-container">
    <h2>Customers' Orders</h2>
    <?php if (empty($orders)): ?>
        <p>No orders yet.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="order">
                <div class="order-details">
                    <strong>Order #<?= htmlspecialchars($order['id']) ?></strong><br>
                    Customer: <?= htmlspecialchars($order['customer_id']) ?><br>
                    Items: <?= htmlspecialchars($order['item_description']) ?><br>
                    Amount: RM<?= number_format($order['amount'], 2) ?><br>
                    Placed on: <?= htmlspecialchars($order['created_date']) ?><br>
                    Address: <?= htmlspecialchars($order['delivery_address']) ?><br>
                    Current Status: <strong><?= htmlspecialchars($order['status']) ?></strong>
                </div>
                <div class="status-update">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                        <select name="status" required>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_status">Update Status</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>