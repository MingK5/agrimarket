<?php
session_start();
require '../includes/db.php';

$userType = $_SESSION['user']['userType_Id'] ?? 0;
$vendorId = $userType == 3 ? $_SESSION['user']['id'] : null;

if ($userType == 0) {
    header("Location: ../index.php");
    exit();
}

$productId = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: product.php");
    exit();
}

// ✅ Only customer visits are counted
if ($userType == 4) {
    $pdo->prepare("UPDATE product SET visit_count = visit_count + 1 WHERE id = ?")->execute([$productId]);
}

// Check if bookmarked
$isBookmarked = false;
if ($userType == 4) {
    $userId = $_SESSION['user']['id'];
    $stmt = $pdo->prepare("SELECT * FROM bookmark WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $isBookmarked = $stmt->fetch() !== false;
}

// Map packaging to units
$packagingToUnit = [
    'Bag' => 'kg', 'Box' => 'piece', 'Piece' => 'piece', 'Tray' => 'dozen',
    'Jar' => 'kg', 'Cup' => 'liter', 'Pack' => 'kg', 'Wheel' => 'kg',
    'Bottle' => 'liter', 'Root' => 'kg', 'Punnet' => 'kg', 'Head' => 'piece',
    'Bunch' => 'piece', 'Whole' => 'piece', 'Cage' => 'piece', 'Pen' => 'piece'
];
$unit = $packagingToUnit[$product['packaging']] ?? 'piece';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Details</title>
    <style>
        .details { padding: 30px; max-width: 700px; margin: 0 auto; background: #f9f9f9; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .details img { max-width: 100%; height: 300px; object-fit: cover; border-radius: 5px; margin-bottom: 20px; }
        .details h2 { font-size: 28px; color: #2d6f2d; margin: 15px 0; }
        .details p { font-size: 16px; color: #444; line-height: 1.6; }
        .alert { color: red; font-weight: bold; }
        button { padding: 12px 25px; background-color: #3a8f3a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #2d6f2d; }
        a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #ddd; color: #333; text-decoration: none; border-radius: 5px; }
        .quantity input { width: 80px; padding: 8px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="details">
    <?php if ($product['image']): ?>
        <img src="../assets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
    <?php endif; ?>
    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
    <p><strong>Description:</strong> <?php echo htmlspecialchars($product['description'] ?? 'No description available.'); ?></p>
    <p><strong>Price:</strong> RM<?php echo htmlspecialchars($product['price']); ?>/<?php echo $unit; ?></p>
    <p><strong>Stock:</strong> <?php echo htmlspecialchars($product['quantity']); ?> <?php echo $unit; ?></p>

    <!-- ✅ Show these only to admin, staff, vendor -->
    <?php if (in_array($userType, [1, 2, 3])): ?>
        <p><strong>Visited:</strong> <?php echo $product['visit_count']; ?> times</p>
        <p><strong>Ordered:</strong> <?php echo $product['order_count']; ?> times</p>
    <?php endif; ?>

    <?php if ($userType == 3 && $product['vendor_id'] == $vendorId && $product['quantity'] < $product['reorder_level']): ?>
        <p class="alert">Low Stock Alert: below reorder level (<?php echo $product['reorder_level']; ?> <?php echo $unit; ?>)</p>
    <?php endif; ?>

    <?php if ($userType == 4): ?>
        <form method="POST" action="shopping_cart.php" style="display: inline;">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <label for="quantity">Quantity:</label>
            <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['quantity']; ?>">
            <br><br>
            <button type="submit">Add to Cart</button>
        </form>
        <form method="POST" action="bookmark.php" style="display: inline;">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <input type="hidden" name="action" value="<?php echo $isBookmarked ? 'remove' : 'add'; ?>">
            <button type="submit" style="background-color:#ffa500">
                <?php echo $isBookmarked ? 'Remove Bookmark' : 'Add Bookmark'; ?>
            </button>
        </form>
    <?php endif; ?>

    <a href="product.php">Back to Products</a>
    <?php if ($userType == 3 && $product['vendor_id'] == $vendorId): ?>
        <a href="edit_product.php?id=<?php echo $product['id']; ?>" style="background-color: #ffa500; color: #fff;">Edit</a>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
