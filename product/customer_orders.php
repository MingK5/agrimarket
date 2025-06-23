<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 4) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM sales_order WHERE customer_id = ? ORDER BY created_date DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();
$statusSteps = ['Confirmed', 'Packed', 'Shipped', 'Delivered'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <style>
        .order-container { max-width: 800px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; }
        .order { border-bottom: 1px solid #ccc; padding: 15px 0; }
        .progress { display: flex; gap: 20px; margin-top: 10px; }
        .step { display: flex; align-items: center; gap: 5px; }
        .tick { color: green; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="order-container">
    <h2>My Orders</h2>
    <?php if (empty($orders)): ?>
        <p>No orders yet.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="order">
                <strong>Order #<?= $order['id'] ?></strong><br>
                Items: <?= htmlspecialchars($order['item_description']) ?><br>
                Amount: RM<?= number_format($order['amount'], 2) ?><br>
                Placed on: <?= $order['created_date'] ?><br>
                Address: <?= htmlspecialchars($order['delivery_address']) ?><br>
                <div class="progress">
                    <?php foreach ($statusSteps as $step): ?>
                        <div class="step">
                            <?php if (in_array($order['status'], $statusSteps) && array_search($order['status'], $statusSteps) >= array_search($step, $statusSteps)): ?>
                                ✅ <span class="tick"><?= $step ?></span>
                            <?php else: ?>
                                ⬜ <span><?= $step ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
