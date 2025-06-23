<?php
session_start();
require '../includes/db.php';

$userType = $_SESSION['user']['userType_Id'] ?? 0;
$vendorId = $userType == 3 ? $_SESSION['user']['id'] : null;

if ($userType != 3) {
    header("Location: ../index.php");
    exit();
}

$productId = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM product WHERE id = ? AND vendor_id = ?");
$stmt->execute([$productId, $vendorId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: product.php");
    exit();
}

$allowedCategories = [];
$stmt = $pdo->prepare("SELECT service_listings FROM users WHERE id = ?");
$stmt->execute([$vendorId]);
$serviceListings = $stmt->fetchColumn();
$allowedCategories = $serviceListings ? array_map('trim', explode(',', $serviceListings)) : [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $category = trim($_POST['category']);
    $quantity = trim($_POST['quantity']);
    $reorder_level = trim($_POST['reorder_level']);
    $image = $_FILES['image']['name'] ? $_FILES['image']['name'] : $product['image'];

    $errors = [];
    if ($name && $price && $category && $quantity && $reorder_level && in_array($category, $allowedCategories)) {
        $price = floatval($price);
        $quantity = intval($quantity);
        $reorder_level = intval($reorder_level);
        $targetDir = "../assets/";
        $targetFile = $targetDir . basename($_FILES['image']['name']);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];

        if ($_FILES['image']['name'] && !in_array($imageFileType, $allowedTypes)) {
            $errors[] = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
        } else {
            if ($_FILES['image']['name'] && move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $stmt = $pdo->prepare("UPDATE product SET name = ?, description = ?, price = ?, category = ?, quantity = ?, reorder_level = ?, image = ? WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$name, $description, $price, $category, $quantity, $reorder_level, $image, $productId, $vendorId]);
            } else {
                $stmt = $pdo->prepare("UPDATE product SET name = ?, description = ?, price = ?, category = ?, quantity = ?, reorder_level = ? WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$name, $description, $price, $category, $quantity, $reorder_level, $productId, $vendorId]);
            }
            header("Location: product_details.php?id=" . $productId . "&success=1");
            exit();
        }
    } else {
        $errors[] = "Missing or invalid data. Category must be one of: " . implode(", ", $allowedCategories);
    }
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
$unit = $packagingToUnit[$product['packaging']] ?? 'piece';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
    <style>
        .edit-container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .edit-form { margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 5px; }
        .edit-form label { display: block; margin: 10px 0 5px; font-weight: bold; }
        .edit-form input, .edit-form textarea, .edit-form select { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .edit-form input[type="file"] { padding: 0; }
        .submit-btn { padding: 10px 20px; background-color: #2d6f2d; color: #fff; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        .submit-btn:hover { background-color: #1a5c1a; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="edit-container">
        <h2>Edit Product: <?php echo htmlspecialchars($product['name']); ?></h2>
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <p class="success">Product updated successfully!</p>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="edit-form">
                <label for="name">Product Name:</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                <label for="description">Description:</label>
                <textarea name="description" id="description" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                <label for="price">Price (RM):</label>
                <input type="number" name="price" id="price" step="0.01" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                <label for="category">Category:</label>
                <select name="category" id="category" required>
                    <?php foreach ($allowedCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $product['category'] === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" min="0" value="<?php echo htmlspecialchars($product['quantity']); ?>" required>
                <label for="reorder_level">Reorder Level:</label>
                <input type="number" name="reorder_level" id="reorder_level" min="0" value="<?php echo htmlspecialchars($product['reorder_level']); ?>" required>
                <label for="image">Image:</label>
                <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp">
                <?php if ($product['image']): ?>
                    <p>Current Image: <img src="../assets/<?php echo htmlspecialchars($product['image']); ?>" alt="Current Image" style="max-width: 200px; max-height: 200px;"></p>
                <?php endif; ?>
            </div>
            <button type="submit" class="submit-btn">Update Product</button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>