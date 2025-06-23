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
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Fetch vendor's service listings if vendor
$allowedCategories = [];
if ($userType == 3) {
    $stmt = $pdo->prepare("SELECT service_listings FROM users WHERE id = ?");
    $stmt->execute([$vendorId]);
    $serviceListings = $stmt->fetchColumn();
    $allowedCategories = $serviceListings ? array_map('trim', explode(',', $serviceListings)) : [];
}

if ($userType == 3) {
    $whereClause = "vendor_id = ? AND category IN (" . implode(',', array_fill(0, count($allowedCategories), '?')) . ")";
    $params = array_merge([$vendorId], $allowedCategories);
    if ($categoryFilter && in_array($categoryFilter, $allowedCategories)) {
        $whereClause .= " AND category = ?";
        $params[] = $categoryFilter;
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE " . $whereClause);
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare("SELECT * FROM product " . ($categoryFilter ? "WHERE category = ?" : ""));
    $params = $categoryFilter ? [$categoryFilter] : [];
    $stmt->execute($params);
}
$products = $stmt->fetchAll();

// Fetch unique categories for the filter, limited to vendor's service listings if vendor
$categories = [];
if ($userType == 3 && !empty($allowedCategories)) {
    $placeholders = implode(',', array_fill(0, count($allowedCategories), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM product WHERE category IN ($placeholders) AND vendor_id = ?");
    $stmt->execute(array_merge($allowedCategories, [$vendorId]));
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $stmt = $pdo->query("SELECT DISTINCT category FROM product ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Map packaging to units
$packagingToUnit = [
    'Bag' => 'kg',
    'Box' => 'piece',
    'Piece' => 'piece',
    'Tray' => 'dozen',
    'Jar' => 'kg',
    'Cup' => 'liter',
    'Pack' => 'kg',
    'Wheel' => 'kg',
    'Bottle' => 'liter',
    'Root' => 'kg',
    'Punnet' => 'kg',
    'Head' => 'piece',
    'Bunch' => 'piece',
    'Whole' => 'piece',
    'Cage' => 'piece',
    'Pen' => 'piece',
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $userType == 3) {
    if (isset($_POST['update_quantity'])) {
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
        .product-item { border: 1px solid #ccc; padding: 10px; width: 200px; text-align: center; cursor: pointer; }
        .product-item img { max-width: 100%; height: 150px; object-fit: cover; }
        .product-item h4 { margin: 5px 0; font-size: 16px; }
        .product-item p { margin: 2px 0; font-size: 14px; }
        .btn { padding: 5px 10px; margin: 5px; }
        .bulk-upload { margin-top: 20px; }
        .filter { margin-bottom: 20px; padding: 0 20px; }
        .filter select { padding: 5px; font-size: 14px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Product title + Shopping Cart in same row -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
        <h2 style="margin: 0;">Product Listing</h2>
        <?php if ($userType == 4): ?>
            <a href="shopping_cart.php">
                <img src="/agrimarket/assets/Shopping Cart.jpg" alt="Shopping Cart" style="height: 40px; cursor: pointer;">
            </a>
        <?php endif; ?>
    </div>

    <div class="filter">
        <label for="category">Filter by Category:</label>
        <select name="category" id="category" onchange="window.location.href='?category=' + encodeURIComponent(this.value)">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="product-list">
        <?php if (empty($products)): ?>
            <p>No products found.</p>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <a href="product_details.php?id=<?php echo $product['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="product-item">
                        <?php if ($product['image']): ?>
                            <img src="../assets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php endif; ?>
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p>Price: RM<?php echo htmlspecialchars($product['price']); ?>/<?php echo $packagingToUnit[$product['packaging']] ?? 'piece'; ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>