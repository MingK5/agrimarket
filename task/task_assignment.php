<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 1 && $_SESSION['user']['userType_Id'] != 2) {
    header("Location: ../index.php");
    exit();
}

$vendorId = $_SESSION['user']['id'];
$ordersPerPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $ordersPerPage;

// Get total number of orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales_order");
$stmt->execute();
$totalOrders = $stmt->fetchColumn();
$totalPages = ceil($totalOrders / $ordersPerPage);

// Get orders for current page
$stmt = $pdo->prepare("SELECT * FROM sales_order ORDER BY created_date DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', (int)$ordersPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

$statusOptions = ['Confirmed', 'Packed', 'Shipped', 'Delivered'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['status'];
    if (in_array($newStatus, $statusOptions)) {
        if ($newStatus === 'Delivered') {
            $stmt = $pdo->prepare("UPDATE sales_order SET status = ?, delivered_date = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
        } else {
            $stmt = $pdo->prepare("UPDATE sales_order SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
        }
    }
    header("Location: task_assignment.php?page=$page");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Orders</title>
    <style>
        .order-container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; }
        .order { border-bottom: 1px solid #ccc; padding: 15px 0; }
        .order-details { margin-bottom: 10px; }
        .status-update { margin-top: 10px; }
        .status-update select { padding: 5px; font-size: 14px; border-radius: 4px; }
        .status-update button { padding: 5px 10px; background-color: #2d6f2d; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        .status-update button:hover { background-color: #1a5c1a; }
        .status-Confirmed { color: #3B82F6; }
        .status-Packed { color: #EAB308; }
        .status-Shipped { color: #F97316; }
        .status-Delivered { color: #22C55E; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { margin: 0 5px; padding: 5px 10px; text-decoration: none; color: #2d6f2d; border: 1px solid #2d6f2d; border-radius: 4px; }
        .pagination a:hover { background-color: #2d6f2d; color: #fff; }
        .pagination a.disabled { color: #ccc; border-color: #ccc; pointer-events: none; }
        .pagination a.current { background-color: #2d6f2d; color: #fff; }
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
                    Current Status: <strong class="status-<?= htmlspecialchars($order['status']) ?>">
                        <?= htmlspecialchars($order['status']) ?>
                    </strong>
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
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">Previous</a>
            <?php else: ?>
                <a href="#" class="disabled">Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Next</a>
            <?php else: ?>
                <a href="#" class="disabled">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>