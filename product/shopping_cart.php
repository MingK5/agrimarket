<?php
session_start();
require '../includes/db.php';

$userType = $_SESSION['user']['userType_Id'] ?? 0;
$vendorId = $userType == 3 ? $_SESSION['user']['id'] : null;

if ($userType == 0) {
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
            if (!isset($cart[$productId])) {
                $cart[$productId] = $product;
                $cart[$productId]['cart_quantity'] = 1;
            } else {
                $cart[$productId]['cart_quantity']++;
            }
            $_SESSION['cart'] = $cart;
            $stmt = $pdo->prepare("UPDATE product SET quantity = quantity - 1 WHERE id = ?");
            $stmt->execute([$productId]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $deleteId = $_POST['delete_id'];
        if (isset($cart[$deleteId])) {
            $stmt = $pdo->prepare("UPDATE product SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$cart[$deleteId]['cart_quantity'], $deleteId]);
            unset($cart[$deleteId]);
            $_SESSION['cart'] = $cart;
        }
    } elseif (isset($_POST['update_quantity'])) {
        $productId = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        if (isset($cart[$productId]) && $quantity >= 0 && $quantity <= $cart[$productId]['quantity']) {
            $oldQuantity = $cart[$productId]['cart_quantity'];
            $diff = $quantity - $oldQuantity;
            $cart[$productId]['cart_quantity'] = $quantity;
            $_SESSION['cart'] = $cart;
            $stmt = $pdo->prepare("UPDATE product SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([-$diff, $productId]);
        }
    }
    header("Location: shopping_cart.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart</title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .header { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; background-color: #fff; border-bottom: 1px solid #ddd; }
        .header img { height: 40px; }
        .cart-icon { position: relative; }
        .cart-icon img { height: 30px; cursor: pointer; }
        .cart-container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .cart-item { display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; }
        .cart-item img { width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 5px; }
        .cart-item-details { flex-grow: 1; }
        .cart-item-details h4 { margin: 0 0 5px; color: #2d6f2d; }
        .cart-item-details p { margin: 2px 0; color: #666; }
        .quantity-input { width: 60px; padding: 5px; text-align: center; border: 1px solid #ccc; border-radius: 4px; }
        .delete-btn { padding: 5px 10px; background-color: #ff4444; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        .delete-btn:hover { background-color: #cc0000; }
        .total { font-size: 18px; font-weight: bold; color: #2d6f2d; text-align: right; margin: 15px 0; }
        .btn { padding: 10px 20px; margin: 5px; background-color: #2d6f2d; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
        .btn:hover { background-color: #1a5c1a; }
        .related-products { margin-top: 20px; }
        .related-products h3 { color: #2d6f2d; }
        .related-item { display: inline-block; margin: 10px; text-align: center; }
        .related-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; }
        footer { text-align: center; padding: 10px; background-color: #fff; border-top: 1px solid #ddd; margin-top: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="cart-container">
        <?php if (empty($cart)): ?>
            <p style="text-align: center; color: #666;">Your cart is empty.</p>
        <?php else: ?>
            <?php $grandTotal = 0; ?>
            <?php foreach ($cart as $item): ?>
                <?php
                $unit = $packagingToUnit[$item['packaging']] ?? 'piece';
                $itemTotal = $item['price'] * ($item['cart_quantity'] ?? 1);
                $grandTotal += $itemTotal;
                ?>
                <div class="cart-item">
                    <a href="product_details.php?id=<?php echo htmlspecialchars($item['id']); ?>" style="text-decoration: none; color: inherit;">
                        <img src="/agrimarket/assets/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    </a>
                    <div class="cart-item-details">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p>Price per <?php echo $unit; ?>: RM<?php echo htmlspecialchars($item['price']); ?></p>
                        <div>
                            <label>Quantity: </label>
                            <input type="number" class="quantity-input" value="<?php echo $item['cart_quantity'] ?? 1; ?>" min="1" max="<?php echo $item['quantity']; ?>" onchange="updateQuantity(<?php echo $item['id']; ?>, this.value)">
                        </div>
                        <p>Total: RM<?php echo number_format($itemTotal, 2); ?></p>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="total">Grand Total: RM<?php echo number_format($grandTotal, 2); ?></div>
            <a href="payment.php" class="btn">Proceed to Payment</a>
            <a href="product.php" class="btn">Continue Shopping</a>
        <?php endif; ?>
        
        <br><br><br><br>
        <div class="related-products">
            <h3>Related Products</h3>
            <?php
            $related = [];
            if (!empty($cart)) {
                $firstItem = reset($cart);
                $category = $firstItem['category'];
                $stmt = $pdo->prepare("SELECT * FROM product WHERE category = ? AND id NOT IN (" . implode(',', array_keys($cart)) . ") LIMIT 3");
                $stmt->execute([$category]);
                $related = $stmt->fetchAll();
            }
            foreach ($related as $item): ?>
                <div class="related-item">
                    <a href="product_details.php?id=<?php echo htmlspecialchars($item['id']); ?>" style="text-decoration: none; color: inherit;">
                        <img src="/agrimarket/assets/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    </a>
                    <p><?php echo htmlspecialchars($item['name']); ?></p>
                    <p>RM<?php echo htmlspecialchars($item['price']); ?>/<?php echo $packagingToUnit[$item['packaging']] ?? 'piece'; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function updateQuantity(productId, quantity) {
            fetch('update_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // reload to reflect new total
                } else {
                    alert("Quantity update failed. Please try again.");
                }
            });
        }
    </script>
</body>
</html>