<?php
session_start();
require '../includes/db.php';

$userType = $_SESSION['user']['userType_Id'] ?? 0;
$vendorId = $userType == 3 ? $_SESSION['user']['id'] : null;

if ($userType == 0) {
    header("Location: ../index.php");
    exit();
}

$products = [];
if ($userType == 3) {
    $stmt = $pdo->prepare("SELECT * FROM product WHERE vendor_id = ?");
    $stmt->execute([$vendorId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM product");
    $stmt->execute();
}
$products = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $userType == 3) {
    if (isset($_POST['bulk_upload'])) {
        $data = $_POST['bulk_data'];
        $rows = explode("\n", $data);
        foreach ($rows as $row) {
            $cols = str_getcsv($row);
            if (count($cols) >= 5) {
                $stmt = $pdo->prepare("INSERT INTO product (name, description, price, category, vendor_id, quantity) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cols[0], $cols[1], $cols[2], $cols[3], $vendorId, $cols[4]]);
            }
        }
    } elseif (isset($_POST['update_quantity'])) {
        $productId = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $stmt = $pdo->prepare("UPDATE product SET quantity = ? WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$quantity, $productId, $vendorId]);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Listing</title>
    <style>
        .product-list { display: flex; flex-wrap: wrap; gap: 20px; padding: 20px; }
        .product-item { border: 1px solid #ccc; padding: 10px; width: 200px; text-align: center; }
        .btn { padding: 5px 10px; margin: 5px; }
        .bulk-upload { margin-top: 20px; }
    </style>
</head>
<body>
    <h2>Product Listing</h2>
    <?php if ($userType == 3): ?>
        <div class="bulk-upload">
            <h3>Bulk Upload</h3>
            <form method="POST">
                <textarea name="bulk_data" rows="5" cols="50" placeholder="name,description,price,category,quantity (one per line)"></textarea>
                <button type="submit" name="bulk_upload">Upload</button>
            </form>
        </div>
    <?php endif; ?>
    <div class="product-list">
        <?php foreach ($products as $product): ?>
            <div class="product-item">
                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                <p><?php echo htmlspecialchars($product['description']); ?></p>
                <p>Price: RM<?php echo htmlspecialchars($product['price']); ?></p>
                <p>Category: <?php echo htmlspecialchars($product['category']); ?></p>
                <?php if ($userType == 4): ?>
                    <form method="POST" action="shopping_cart.php">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="btn">Add to Cart</button>
                    </form>
                <?php elseif ($userType == 3): ?>
                    <form method="POST">
                        <input type="number" name="quantity" value="<?php echo $product['quantity']; ?>" min="0">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" name="update_quantity" class="btn">Update Quantity</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>