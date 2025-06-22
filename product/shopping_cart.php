<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 4) {
    header("Location: ../index.php");
    exit();
}

$cart = [];
if (isset($_SESSION['cart'])) {
    $cart = $_SESSION['cart'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['product_id'])) {
        $productId = $_POST['product_id'];
        $stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if ($product && $product['quantity'] > 0) {
            $cart[$productId] = $product;
            $_SESSION['cart'] = $cart;
            $stmt = $pdo->prepare("UPDATE product SET quantity = quantity - 1 WHERE id = ?");
            $stmt->execute([$productId]);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart</title>
    <style>
        .cart { padding: 20px; }
        .cart-item { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
        .compare { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="cart">
        <h2>Shopping Cart</h2>
        <?php if (empty($cart)): ?>
            <p>Cart is empty.</p>
        <?php else: ?>
            <?php foreach ($cart as $item): ?>
                <div class="cart-item">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <p>Price: RM<?php echo htmlspecialchars($item['price']); ?></p>
                </div>
            <?php endforeach; ?>
            <div class="compare">
                <h3>Compare Products</h3>
                <table>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                    </tr>
                    <?php foreach ($cart as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>RM<?php echo htmlspecialchars($item['price']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <a href="payment.php">Proceed to Payment</a>
        <?php endif; ?>
        <a href="product_listing.php">Continue Shopping</a>
    </div>
</body>
</html>