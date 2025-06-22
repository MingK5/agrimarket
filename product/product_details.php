<?php
session_start();
require '../includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: product_listing.php");
    exit();
}

$productId = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: product_listing.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Details</title>
    <style>
        .details { padding: 20px; max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="details">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($product['description']); ?></p>
        <p><strong>Price:</strong> RM<?php echo htmlspecialchars($product['price']); ?></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?></p>
        <p><strong>Packaging:</strong> <?php echo htmlspecialchars($product['packaging']); ?></p>
        <p><strong>Quantity:</strong> <?php echo htmlspecialchars($product['quantity']); ?></p>
        <?php if ($_SESSION['user']['userType_Id'] == 4): ?>
            <form method="POST" action="shopping_cart.php">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                <button type="submit">Add to Cart</button>
            </form>
        <?php endif; ?>
        <a href="product_listing.php">Back to Listing</a>
    </div>
</body>
</html>