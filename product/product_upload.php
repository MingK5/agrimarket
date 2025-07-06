<?php
session_start();
require '../includes/db.php';

$userType = $_SESSION['user']['userType_Id'] ?? 0;
$vendorId = $userType == 3 ? $_SESSION['user']['id'] : null;

if ($userType != 3) {
    header("Location: ../index.php");
    exit();
}

$allowedCategories = [];
$stmt = $pdo->prepare("SELECT service_listings FROM users WHERE id = ?");
$stmt->execute([$vendorId]);
$serviceListings = $stmt->fetchColumn();
$allowedCategories = $serviceListings ? array_map('trim', explode(',', $serviceListings)) : [];

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_products'])) {
    $products = [];
    $errors = [];
    for ($i = 0; $i < count($_POST['name']); $i++) {
        $name = trim($_POST['name'][$i]);
        $description = trim($_POST['description'][$i]);
        $price = trim($_POST['price'][$i]);
        $category = trim($_POST['category'][$i]);
        $quantity = trim($_POST['quantity'][$i]);
        $reorder_level = trim($_POST['reorder_level'][$i]);
        $packaging = trim($_POST['packaging'][$i]);
        $packaging_quantity = trim($_POST['packaging_quantity'][$i]);
        $image = $_FILES['image']['name'][$i] ? $_FILES['image']['name'][$i] : null;

        if ($name && $price && $category && $quantity && $reorder_level && $packaging && $packaging_quantity && in_array($category, $allowedCategories) && array_key_exists($packaging, $packagingToUnit) && $packaging_quantity > 0) {
            $price = floatval($price);
            $quantity = intval($quantity);
            $reorder_level = intval($reorder_level);
            $packaging_quantity = floatval($packaging_quantity);
            $targetDir = "../assets/";
            $targetFile = $targetDir . basename($_FILES['image']['name'][$i]);
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
            if ($image && !in_array($imageFileType, $allowedTypes)) {
                $errors[] = "Only JPG, JPEG, PNG, and WEBP files are allowed for product $name.";
            } else {
                if ($image && move_uploaded_file($_FILES['image']['tmp_name'][$i], $targetFile)) {
                    $stmt = $pdo->prepare("INSERT INTO product (name, description, price, category, vendor_id, quantity, reorder_level, image, packaging, packaging_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $price, $category, $vendorId, $quantity, $reorder_level, $image, $packaging, $packaging_quantity]);
                } elseif (!$image) {
                    $stmt = $pdo->prepare("INSERT INTO product (name, description, price, category, vendor_id, quantity, reorder_level, packaging, packaging_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $price, $category, $vendorId, $quantity, $reorder_level, $packaging, $packaging_quantity]);
                }
            }
        } else {
            $errors[] = "Missing or invalid data for product $name. Category must be one of: " . implode(", ", $allowedCategories) . "; Packaging must be valid; Packaging quantity must be positive.";
        }
    }
    if (empty($errors)) {
        header("Location: product.php?success=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Upload</title>
    <style>
        .upload-container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .product-form { margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 5px; }
        .product-form label { display: block; margin: 10px 0 5px; font-weight: bold; }
        .product-form input, .product-form textarea, .product-form select { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .product-form input[type="file"] { padding: 0; }
        .add-more-btn, .submit-btn { padding: 10px 20px; background-color: #2d6f2d; color: #fff; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        .add-more-btn:hover, .submit-btn:hover { background-color: #1a5c1a; }
        .error { color: red; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="upload-container">
        <h2>Bulk Upload Products</h2>
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <p style="color: green;">Products uploaded successfully!</p>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div id="productForms">
                <div class="product-form">
                    <label for="name_0">Product Name:</label>
                    <input type="text" name="name[]" id="name_0" required>
                    <label for="description_0">Description:</label>
                    <textarea name="description[]" id="description_0" rows="3"></textarea>
                    <label for="price_0">Price (RM):</label>
                    <input type="number" name="price[]" id="price_0" step="0.01" required>
                    <label for="category_0">Category:</label>
                    <select name="category[]" id="category_0" required>
                        <option value="">Select Category</option>
                        <?php foreach ($allowedCategories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="quantity_0">Quantity (number of packages):</label>
                    <input type="number" name="quantity[]" id="quantity_0" min="1" required>
                    <label for="reorder_level_0">Reorder Level (number of packages):</label>
                    <input type="number" name="reorder_level[]" id="reorder_level_0" min="0" required>
                    <label for="packaging_0">Packaging:</label>
                    <select name="packaging[]" id="packaging_0" required>
                        <option value="">Select Packaging</option>
                        <?php foreach ($packagingToUnit as $pack => $unit): ?>
                            <option value="<?php echo htmlspecialchars($pack); ?>"><?php echo htmlspecialchars($pack); ?> (<?php echo $unit; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <label for="packaging_quantity_0">Quantity per Package (e.g., kg per Jar, pieces per Bunch):</label>
                    <input type="number" name="packaging_quantity[]" id="packaging_quantity_0" step="0.01" min="0.01" required>
                    <label for="image_0">Image:</label>
                    <input type="file" name="image[]" id="image_0" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>
            <button type="button" class="add-more-btn" onclick="addProductForm()">+ Add More Products</button>
            <button type="submit" name="submit_products" class="submit-btn">Upload Products</button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        let formCount = 1;
        function addProductForm() {
            const container = document.getElementById('productForms');
            const newForm = document.createElement('div');
            newForm.className = 'product-form';
            newForm.innerHTML = `
                <label for="name_${formCount}">Product Name:</label>
                <input type="text" name="name[]" id="name_${formCount}" required>
                <label for="description_${formCount}">Description:</label>
                <textarea name="description[]" id="description_${formCount}" rows="3"></textarea>
                <label for="price_${formCount}">Price (RM):</label>
                <input type="number" name="price[]" id="price_${formCount}" step="0.01" required>
                <label for="category_${formCount}">Category:</label>
                <select name="category[]" id="category_${formCount}" required>
                    <option value="">Select Category</option>
                    <?php foreach ($allowedCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="quantity_${formCount}">Quantity (number of packages):</label>
                <input type="number" name="quantity[]" id="quantity_${formCount}" min="1" required>
                <label for="reorder_level_${formCount}">Reorder Level (number of packages):</label>
                <input type="number" name="reorder_level[]" id="reorder_level_${formCount}" min="0" required>
                <label for="packaging_${formCount}">Packaging:</label>
                <select name="packaging[]" id="packaging_${formCount}" required>
                    <option value="">Select Packaging</option>
                    <?php foreach ($packagingToUnit as $pack => $unit): ?>
                        <option value="<?php echo htmlspecialchars($pack); ?>"><?php echo htmlspecialchars($pack); ?> (<?php echo $unit; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label for="packaging_quantity_${formCount}">Quantity per Package (e.g., kg per Jar, pieces per Bunch):</label>
                <input type="number" name="packaging_quantity[]" id="packaging_quantity_${formCount}" step="0.01" min="0.01" required>
                <label for="image_${formCount}">Image:</label>
                <input type="file" name="image[]" id="image_${formCount}" accept=".jpg,.jpeg,.png,.webp">
            `;
            container.appendChild(newForm);
            formCount++;
        }
    </script>
</body>
</html>