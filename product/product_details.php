<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require '../includes/db.php';

// Validate session
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['userType_Id'])) {
    header("Location: ../index.php");
    exit();
}

$userType = $_SESSION['user']['userType_Id'];
$vendorId = $userType == 3 ? ($_SESSION['user']['id'] ?? null) : null;

if ($userType == 0) {
    header("Location: ../index.php");
    exit();
}

// Validate product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: product.php");
    exit();
}
$productId = (int)$_GET['id'];

try {
// Handle review deletion by admin (userType == 1)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_review_id']) && $userType == 1) {
        $reviewId = (int)$_POST['remove_review_id'];

        // Ensure the review belongs to the same product before deleting
        $checkStmt = $pdo->prepare("SELECT * FROM rating WHERE id = ? AND product_id = ?");
        $checkStmt->execute([$reviewId, $productId]);
        $existingReview = $checkStmt->fetch();

        if ($existingReview) {
            $deleteStmt = $pdo->prepare("DELETE FROM rating WHERE id = ?");
            $deleteStmt->execute([$reviewId]);
            header("Location: product_details.php?id=$productId&msg=review_deleted");
            exit();
        }
    }

    // Fetch product
    $stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: product.php");
        exit();
    }

    // Handle vendor reply (userType == 3) - moved after $product is defined
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_reply_review_id']) && $userType == 3 && $product['vendor_id'] == $vendorId
    ) {
        $reviewId = (int)$_POST['vendor_reply_review_id'];
        $vendorReply = trim($_POST['vendor_reply_text'] ?? '');
        if ($vendorReply !== '') {
            // Ensure the review belongs to the same product before updating
            $checkStmt = $pdo->prepare("SELECT * FROM rating WHERE id = ? AND product_id = ?");
            $checkStmt->execute([$reviewId, $productId]);
            $existingReview = $checkStmt->fetch();
            if ($existingReview) {
                $updateStmt = $pdo->prepare("UPDATE rating SET vendor_reply = ? WHERE id = ?");
                $updateStmt->execute([$vendorReply, $reviewId]);
                header("Location: product_details.php?id=$productId&msg=reply_added");
                exit();
            }
        }
    }

    // Count customer visits
    if ($userType == 4) {
        $pdo->prepare("UPDATE product SET visit_count = visit_count + 1 WHERE id = ?")->execute([$productId]);
    }

    // Check if bookmarked
    $isBookmarked = false;
    if ($userType == 4) {
        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId) {
            $stmt = $pdo->prepare("SELECT * FROM bookmark WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $isBookmarked = $stmt->fetch() !== false;
        }
    }

    // Fetch rating data
    $avgStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM rating WHERE product_id = ?");
    $avgStmt->execute([$productId]);
    $reviewStats = $avgStmt->fetch();
    $avgRating = round($reviewStats['avg_rating'] ?? 0, 1);
    $totalReviews = $reviewStats['total_reviews'] ?? 0;

    // Fetch rating counts
    $ratingCounts = [];
    for ($i = 1; $i <= 5; $i++) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rating WHERE product_id = ? AND rating = ?");
        $stmt->execute([$productId, $i]);
        $ratingCounts[$i] = $stmt->fetch()['count'];
    }

    // Fetch top 5 ratings
    $sort = $_GET['sort'] ?? 'newest';
    $sortOptions = ['newest' => 'so.created_date DESC', 'highest' => 'r.rating DESC', 'lowest' => 'r.rating ASC'];
    $orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];
    $stmt = $pdo->prepare("
        SELECT r.*, u.username as user_name, so.created_date 
        FROM rating r 
        JOIN sales_order so ON r.sales_order_id = so.id 
        JOIN users u ON so.customer_id = u.id 
        WHERE r.product_id = ? 
        ORDER BY $orderBy 
        LIMIT 5
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();

    // Map packaging to units
    $packagingToUnit = [
        'Bag' => 'kg', 'Box' => 'piece', 'Piece' => 'piece', 'Tray' => 'dozen',
        'Jar' => 'kg', 'Cup' => 'liter', 'Pack' => 'kg', 'Wheel' => 'kg',
        'Bottle' => 'liter', 'Root' => 'kg', 'Punnet' => 'kg', 'Head' => 'piece',
        'Bunch' => 'piece', 'Whole' => 'piece', 'Cage' => 'piece', 'Pen' => 'piece'
    ];
    $unit = $packagingToUnit[$product['packaging']] ?? 'piece';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
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
        .reviews-section { margin-top: 30px; padding: 20px; background: #fff; border-radius: 5px; }
        .reviews-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .avg-rating { font-size: 24px; font-weight: bold; color: #2d6f2d; }
        .rating-counts { text-align: right; }
        .rating-counts p { margin: 5px 0; font-size: 14px; }
        .sort-buttons button { margin: 0 5px; background: #ddd; color: #333; }
        .sort-buttons button.active { background: #3a8f3a; color: white; }
        .review { border-bottom: 1px solid #eee; padding: 10px 0; }
        .review p { margin: 5px 0; }
        .review .stars { color: #ffa500; }
        .review .commenter { color: #777; font-size: 14px; }
        .view-more { display: block; text-align: center; margin-top: 20px; }
        .star-bars { margin-bottom: 20px; }
        .star-bar-row { display: flex; align-items: center; margin: 6px 0; }
        .star-label { width: 60px; color: #3a8f3a; font-weight: 500; }
        .bar-bg { flex: 1; height: 12px; background: #eee; border-radius: 6px; margin: 0 10px; }
        .bar-fill { height: 100%; background: #ffc107; border-radius: 6px; }
        .star-count { width: 30px; text-align: right; color: #888; }
        .remove-btn { background: #e74c3c; color: #fff; border: none; border-radius: 4px; padding: 5px 12px; margin-top: 8px; cursor: pointer; }
        .remove-btn:hover { background: #c0392b; }
        .report-btn { background: #007bff; color: #fff; border: none; border-radius: 4px; padding: 5px 12px; margin-top: 8px; cursor: pointer; }
        .report-btn:hover { background: #0056b3; }
        .reply-btn { background: #2d6f2d; color: #fff; border: none; border-radius: 4px; padding: 5px 12px; margin-top: 8px; cursor: pointer; }
        .reply-btn:hover { background: #2d6f2d; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="details">
    <?php if (!empty($product['image'])): ?>
        <img src="../assets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
    <?php endif; ?>
    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
    <p><strong>Description:</strong> <?php echo htmlspecialchars($product['description'] ?? 'No description available.'); ?></p>
    <p><strong>Price:</strong> RM<?php echo htmlspecialchars($product['price']); ?>/<?php echo $unit; ?></p>
    <p><strong>Stock:</strong> <?php echo htmlspecialchars($product['quantity']); ?> <?php echo $unit; ?></p>

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
            <button type="submit" style="background-color: #ffa500">
                <?php echo $isBookmarked ? 'Remove Bookmark' : 'Add Bookmark'; ?>
            </button>
        </form>
    <?php endif; ?>

    <div class="reviews-section">
        <h1 style="font-size: 2em; color: #2d6f2d; margin-bottom: 8px;">Ratings and Review</h1>
        <h3 class="avg-rating" style="margin-bottom: 18px; font-size: 1.2em; font-weight: bold; color: #2d6f2d;">
            Average Rating: <?php echo $avgRating ? $avgRating . ' / 5' : 'No reviews yet'; ?><?php if ($avgRating) { echo '⭐️'; } ?>
        </h3>
        <div class="sort-buttons">
            <button onclick="window.location.href='?id=<?php echo $productId; ?>&sort=newest'" class="<?php echo $sort == 'newest' ? 'active' : ''; ?>">Newest</button>
            <button onclick="window.location.href='?id=<?php echo $productId; ?>&sort=highest'" class="<?php echo $sort == 'highest' ? 'active' : ''; ?>">Highest</button>
            <button onclick="window.location.href='?id=<?php echo $productId; ?>&sort=lowest'" class="<?php echo $sort == 'lowest' ? 'active' : ''; ?>">Lowest</button>
        </div>
        <br>
        <div class="star-bars">
            <?php for ($i = 5; $i >= 1; $i--): 
                $percent = $totalReviews ? ($ratingCounts[$i] / $totalReviews) * 100 : 0;
            ?>
                <div class="star-bar-row">
                    <span class="star-label"><?php echo $i; ?> star</span>
                    <div class="bar-bg">
                        <div class="bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                    </div>
                    <span class="star-count"><?php echo $ratingCounts[$i]; ?></span>
                </div>
            <?php endfor; ?>
        </div>
        <?php foreach ($reviews as $review): ?>
            <div class="review">
                <p class="stars"><?php echo str_repeat('★', $review['rating']); ?><?php echo str_repeat('☆', 5 - $review['rating']); ?></p>
                <p><?php echo htmlspecialchars($review['comment']); ?></p>
                <p class="commenter">By <?php echo htmlspecialchars($review['user_name']); ?> on <?php echo date('M d, Y', strtotime($review['created_date'])); ?></p>
                <?php if (!empty($review['vendor_reply'])): ?>
                    <div style="margin-top:8px; padding:8px; background:#e0e0e0; border-left:4px solid #888;">
                        <strong>Vendor Reply:</strong> <?php echo htmlspecialchars($review['vendor_reply']); ?>
                    </div>
                <?php endif; ?>
                <?php if ($userType == 1): ?>
                    <form method="POST" action="product_details.php?id=<?php echo $productId; ?>" style="display:inline;" onsubmit="return confirmDelete(this);">
                        <input type="hidden" name="remove_review_id" value="<?php echo $review['id']; ?>">
                        <button type="submit" class="remove-btn">Remove</button>
                    </form>
                <?php elseif (in_array($userType, [2,4])): ?>
                    <button class="report-btn" onclick="alert('Thank you for reporting this review. Our team will review it shortly.');">Report</button>
                <?php endif; ?>
                <?php if ($userType == 3 && $product['vendor_id'] == $vendorId && empty($review['vendor_reply'])): ?>
                    <button type="button" class="reply-btn" onclick="showReplyForm(<?php echo $review['id']; ?>)">Reply</button>
                    <form method="POST" action="product_details.php?id=<?php echo $productId; ?>" class="vendor-reply-form" id="reply-form-<?php echo $review['id']; ?>" style="display:none; margin-top:8px;">
                        <input type="hidden" name="vendor_reply_review_id" value="<?php echo $review['id']; ?>">
                        <textarea name="vendor_reply_text" rows="2" style="width:100%; border-radius:4px; border:1px solid #ccc; padding:6px;" placeholder="Write your reply..."></textarea>
                        <button type="submit" style="margin-top:6px; background:#2d6f2d; color:#fff;">Submit Reply</button>
                        <button type="button" onclick="hideReplyForm(<?php echo $review['id']; ?>)" style="margin-top:6px; margin-left:8px; background:#ccc; color:#333;">Cancel</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($totalReviews > 5): ?>
            <a href="all_reviews.php?product_id=<?php echo $productId; ?>" class="view-more">View More Reviews</a>
        <?php endif; ?>
    </div>

    <a href="product.php">Back to Products</a>
    <?php if ($userType == 3 && $product['vendor_id'] == $vendorId): ?>
        <a href="edit_product.php?id=<?php echo $product['id']; ?>" style="background-color: #ffa500; color: #fff;">Edit</a>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
function confirmDelete(form) {
    return confirm("Are you sure you want to delete this review?");
}

function showReplyForm(reviewId) {
    document.getElementById('reply-form-' + reviewId).style.display = 'block';
}
function hideReplyForm(reviewId) {
    document.getElementById('reply-form-' + reviewId).style.display = 'none';
}

window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');

    if (msg === 'review_deleted') {
    alert("Review has been successfully deleted.");
    // Remove the msg parameter from the URL
    window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([?&])msg=review_deleted(&)?/, '$1').replace(/[\?&]$/, ''));
    }
    if (msg === 'reply_added') {
        alert("Reply has been added.");
        window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([?&])msg=reply_added(&)?/, '$1').replace(/[\?&]$/, ''));
    }
});
</script>


</body>
</html>