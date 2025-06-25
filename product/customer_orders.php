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

$productStmt = $pdo->prepare("SELECT id, name, image, price FROM product WHERE name = ?");
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <style>
        .order-container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-link { padding: 10px 20px; cursor: pointer; background: #f1f1f1; border-radius: 5px; transition: background 0.3s; }
        .tab-link:hover { background: #ddd; }
        .tab-link.active { background: #007bff; color: #fff; }
        .tab-content { display: none; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .tab-content.active { display: block; }

        .order { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px; padding: 15px; overflow: auto; }
        .order-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #ccc; }
        .order-item img { width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 5px; }
        .order-item-details { flex-grow: 1; }
        .order-item-details p { margin: 2px 0; color: #666; }

        .action-buttons { display: flex; flex-direction: column; gap: 8px; }
        .action-btn { padding: 5px 10px; background: #fff; border: 1px solid #ccc; border-radius: 5px; cursor: pointer; width: 80px; text-align: center; }
        .action-btn:hover { background: #f1f1f1; }

        .debug { color: red; font-size: 12px; }
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
                $filteredOrders = array_filter($orders, fn($order) => strtoupper(trim($order['status'])) === strtoupper($status));
                ?>
                <?php if (empty($filteredOrders)): ?>
                    <p>No orders in <?php echo $status; ?> status.</p>
                <?php else: ?>
                    <?php foreach ($filteredOrders as $order): ?>
                        <div class="order">
                            <strong>Order #<?php echo $order['id']; ?></strong><br>
                            <p>Amount: RM<?php echo number_format($order['amount'], 2); ?></p>
                            <p>Placed on: <?php echo $order['created_date']; ?></p>
                            <p>Address: <?php echo htmlspecialchars($order['delivery_address']); ?></p>

                            <?php
                            $items = preg_split('/[;,]/', $order['item_description']);
                            $hasItems = false;

                            foreach ($items as $itemStr) {
                                $itemStr = trim($itemStr);
                                if (empty($itemStr)) continue;

                                $matches = [];
                                if (preg_match('/^(.+?)\s*(?:x|\*)\s*(\d+)$/i', $itemStr, $matches)) {
                                    $itemName = trim($matches[1]);
                                    $quantity = (int)$matches[2];

                                    $productStmt->execute([$itemName]);
                                    $product = $productStmt->fetch();

                                    $productId = $product['id'] ?? null;
                                    $image = $product && $product['image'] ? "../assets/" . htmlspecialchars($product['image']) : "../assets/default.jpg";
                                    $price = $product['price'] ?? 0;
                                    $itemTotal = $price * $quantity;
                                    $hasItems = true;
                                    ?>
                                    <div class="order-item">
                                        <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($itemName); ?>" onerror="this.src='../assets/default.jpg';">
                                        <div class="order-item-details">
                                            <p><?php echo htmlspecialchars($itemName); ?> * <?php echo $quantity; ?></p>
                                            <p>Amount: RM<?php echo number_format($itemTotal, 2); ?></p>
                                        </div>
                                        <?php if (strtolower($status) === 'delivered' && $productId): ?>
                                            <div class="action-buttons">
                                                <form action="product_details.php" method="get" style="margin:0;">
                                                    <input type="hidden" name="id" value="<?php echo $productId; ?>">
                                                    <button type="submit" class="action-btn">Buy Again</button>
                                                </form>
                                                <button class="action-btn">Rate</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                } else {
                                    echo "<div class='debug'>Failed to parse item: '$itemStr' in order #{$order['id']}</div>";
                                }
                            }

                            if (!$hasItems) {
                                echo "<div class='debug'>No valid items found in order #{$order['id']}</div>";
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    document.querySelectorAll('.tab-link').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            const tabId = button.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
</script>
</body>
</html>
