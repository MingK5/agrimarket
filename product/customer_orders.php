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
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-link { 
            padding: 10px 20px; 
            cursor: pointer; 
            background: #f1f1f1; 
            border-radius: 5px; 
            transition: background 0.3s; 
        }
        .tab-link:hover { background: #ddd; }
        .tab-link.active { background: #007bff; color: #fff; }
        .tab-content { display: none; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .tab-content.active { display: block; }
        .order { border-bottom: 1px solid #ccc; padding: 15px 0; }
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
        <!-- Tab Links -->
        <div class="tabs">
            <?php foreach ($statusSteps as $index => $status): ?>
                <div class="tab-link <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="tab-<?php echo strtolower($status); ?>">
                    <?php echo $status; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tab Content -->
        <?php foreach ($statusSteps as $index => $status): ?>
            <div class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo strtolower($status); ?>">
                <?php
                // Filter orders by current status
                $filteredOrders = array_filter($orders, function($order) use ($status) {
                    return $order['status'] === $status;
                });
                ?>
                <?php if (empty($filteredOrders)): ?>
                    <p>No orders in <?php echo $status; ?> status.</p>
                <?php else: ?>
                    <?php foreach ($filteredOrders as $order): ?>
                        <div class="order">
                            <strong>Order #<?php echo $order['id']; ?></strong><br>
                            Items: <?php echo htmlspecialchars($order['item_description']); ?><br>
                            Amount: RM<?php echo number_format($order['amount'], 2); ?><br>
                            Placed on: <?php echo $order['created_date']; ?><br>
                            Address: <?php echo htmlspecialchars($order['delivery_address']); ?><br>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    // JavaScript for tab switching
    document.querySelectorAll('.tab-link').forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            // Add active class to clicked tab and corresponding content
            button.classList.add('active');
            const tabId = button.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
</script>
</body>
</html>