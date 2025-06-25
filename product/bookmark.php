<?php
session_start();
require '../includes/db.php';

$userId = $_SESSION['user']['id'];
$userType = $_SESSION['user']['userType_Id'] ?? 0;

if ($userType != 4) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['userType_Id'] != 4) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'])) {
    $userId = $_SESSION['user']['id'];
    $productId = $_POST['product_id'];
    $action = $_POST['action'] ?? 'add';
    $referer = $_SERVER['HTTP_REFERER'] ?? 'product_details.php?id=' . htmlspecialchars($productId);

    if ($action == 'add') {
        // Check if bookmark already exists
        $stmt = $pdo->prepare("SELECT * FROM bookmark WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $bookmark_exists = $stmt->fetch();

        if (!$bookmark_exists) {
            // Add to bookmark
            $stmt = $pdo->prepare("INSERT INTO bookmark (customer_id, product_id) VALUES (?, ?)");
            $stmt->execute([$userId, $productId]);
        }
    } elseif ($action == 'remove') {
        // Remove from bookmark
        $stmt = $pdo->prepare("DELETE FROM bookmark WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
    }

    // Redirect back to the referring page
    header("Location: $referer");
    exit();
}

$itemsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Count total bookmarks
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookmark WHERE customer_id = ?");
    $stmt->execute([$userId]);
    $totalItems = $stmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    die("Error counting bookmarks: " . htmlspecialchars($e->getMessage()));
}

// Fetch bookmarked products
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.image, p.packaging, p.quantity
        FROM bookmark b
        JOIN product p ON b.product_id = p.id
        WHERE b.customer_id = ?
        ORDER BY b.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching bookmarks: " . htmlspecialchars($e->getMessage()));
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Bookmarks</title>
    <style>
        .container { padding: 30px; max-width: 900px; margin: 0 auto; background: #f9f9f9; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { font-size: 28px; color: #2d6f2d; margin: 15px 0; font-family: 'Arial', sans-serif; text-align: center; }
        .bookmark-list { display: flex; flex-direction: column; gap: 20px; }
        .bookmark-item { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; display: flex; align-items: center; gap: 20px; }
        .bookmark-item img { width: 150px; height: 150px; object-fit: cover; border-radius: 5px; }
        .bookmark-item .content { flex: 1; }
        .bookmark-item h3 { font-size: 18px; color: #333; margin: 5px 0; }
        .bookmark-item p { font-size: 14px; color: #444; margin: 5px 0; }
        .remove-button { position: absolute; top: 10px; right: 10px; background: #ff6f61; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; font-size: 14px; line-height: 25px; text-align: center; }
        .remove-button:hover { background: #e55a50; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; padding: 8px 12px; margin: 0 5px; background: #ddd; color: #333; text-decoration: none; border-radius: 5px; }
        .pagination a:hover { background: #ccc; }
        .pagination .active { background: #3a8f3a; color: white; }
        .pagination .disabled { background: #eee; color: #999; pointer-events: none; }
        a.back-link { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #ddd; color: #333; text-decoration: none; border-radius: 5px; }
        a.back-link:hover { background: #ccc; }
        .error { color: red; text-align: center; margin: 10px 0; }
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: #fff;
            border-bottom: 1px solid #ddd;
        }
        nav .logo img {
            height: 40px;
        }
        nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        nav ul li {
            margin-left: 20px;
        }
        nav ul li a {
            text-decoration: none;
            color: #2d6f2d;
            font-size: 16px;
            font-family: 'Arial', sans-serif;
        }
        nav ul li a:hover {
            color: #1a5c1a;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <h2>My Bookmarks</h2>
        <?php if (empty($bookmarks)): ?>
            <p style="text-align: center; color: #444;">No bookmarked items found.</p>
        <?php else: ?>
            <div class="bookmark-list">
                <?php foreach ($bookmarks as $bookmark): ?>
                    <?php $unit = $packagingToUnit[$bookmark['packaging']] ?? 'piece'; ?>
                    <div class="bookmark-item">
                        <form method="POST" action="bookmark.php" style="display: inline;">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($bookmark['id']); ?>">
                            <input type="hidden" name="action" value="remove">
                            <button type="submit" class="remove-button" title="Remove from Bookmarks">x</button>
                        </form>
                        <?php if ($bookmark['image']): ?>
                            <a href="product_details.php?id=<?php echo htmlspecialchars($bookmark['id']); ?>">
                                <img src="../assets/<?php echo htmlspecialchars($bookmark['image']); ?>" alt="<?php echo htmlspecialchars($bookmark['name']); ?>">
                            </a>
                        <?php endif; ?>
                        <div class="content">
                            <h3><a href="product_details.php?id=<?php echo htmlspecialchars($bookmark['id']); ?>" style="text-decoration: none; color: #2d6f2d;"><?php echo htmlspecialchars($bookmark['name']); ?></a></h3>
                            <p><strong>Price:</strong> RM<?php echo htmlspecialchars($bookmark['price']); ?>/<?php echo $unit; ?></p>
                            <p><strong>Stock:</strong> <?php echo htmlspecialchars($bookmark['quantity']); ?> <?php echo $unit; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                <?php else: ?>
                    <a class="disabled">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next</a>
                <?php else: ?>
                    <a class="disabled">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <a href="product.php" class="back-link">Back to Product Listing</a>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>