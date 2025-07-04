<?php
session_start();
require '../includes/db.php';

// Start execution time tracking for debugging
$startTime = microtime(true);

if ($_SESSION['user']['userType_Id'] != 4) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['user']['id'];

// Fetch all orders
$stmt = $pdo->prepare("SELECT * FROM sales_order WHERE customer_id = ? ORDER BY created_date DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products in one query
$productStmt = $pdo->query("SELECT id, name, image, price FROM product");
$products = [];
while ($row = $productStmt->fetch(PDO::FETCH_ASSOC)) {
    $products[$row['name']] = $row;
}

// Fetch all ratings for the user's orders
$ratingStmt = $pdo->prepare("SELECT product_id, sales_order_id, rating, comment FROM rating WHERE sales_order_id IN (SELECT id FROM sales_order WHERE customer_id = ?)");
$ratingStmt->execute([$userId]);
$ratings = [];
while ($row = $ratingStmt->fetch(PDO::FETCH_ASSOC)) {
    $ratings[$row['sales_order_id'] . '-' . $row['product_id']] = $row;
}

$statusSteps = ['Confirmed', 'Packed', 'Shipped', 'Delivered'];

// Log errors to a file
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " [customer_orders.php] $message\n", 3, '../logs/error.log');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <style>
        .order-container { max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-link { padding: 10px 20px; cursor: pointer; background: #f1f1f1; border-radius: 5px; transition: background 0.3s; }
        .tab-link:hover { background: #ddd; }
        .tab-link.active { background: #007bff; color: #fff; }
        .tab-content { display: none; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .tab-content.active { display: block; }

        .order { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px; padding: 15px; overflow: auto; }
        .order-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #ccc; }
        .order-item img { width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 5px; }
        .order-item-details { flex-grow: 1; }
        .order-item-details p { margin: 2px 0; color: #666; }

        .action-buttons { display: flex; flex-direction: column; gap: 8px; }
        .action-btn { padding: 5px 10px; border: 1px solid #ccc; border-radius: 5px; cursor: pointer; width: 80px; text-align: center; }
        .action-btn.rate-btn { background: #ff6200; color: #fff; }
        .action-btn.rate-btn:hover { background: #e55a00; }
        .action-btn.view-rating { background: #ccc; color: #333; }
        .action-btn.view-rating:hover { background: #bbb; }

        .debug { color: red; font-size: 12px; display: none; }

        /* Rating Popup Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: #fff; max-width: 400px; margin: 100px auto; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .modal-content h3 { margin-top: 0; color: #333; }
        .close-btn { float: right; font-size: 20px; cursor: pointer; color: #666; }
        .rating-form { display: flex; flex-direction: column; gap: 15px; align-items: center; }
        .star-rating { display: inline-block; margin-left: 5px;}
        .star-rating input { display: none; }
        .star-rating label { font-size: 24px; cursor: pointer; color: #ccc; transition: color 0.2s; }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label { color: #f5c518; }
        .rating-form textarea { width: 80%; height: 100px; border: 1px solid #ccc; border-radius: 5px; padding: 10px; resize: vertical; margin: 0 auto; }
        .rating-form button { padding: 10px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; width: 80%; }
        .rating-form button:hover { background: #0056b3; }
        .view-rating-content h3 {color: #0066cc;}

        /* Confirmation and View Rating Popups */
        .confirmation-modal, .view-rating-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .confirmation-content, .view-rating-content { background: #fff; max-width: 300px; margin: 150px auto; padding: 20px; border-radius: 10px; text-align: left; margin-top: 10px; margin-bottom: 10px;;;}
        .confirmation-content p, .view-rating-content p { margin: 0 0 15px; color: #333; }
        .confirmation-content button, .view-rating-content button { padding: 8px 15px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        .confirmation-content button:hover, .view-rating-content button:hover { background: #0056b3; }

    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="order-container">
    <h2>My Orders</h2>
    <?php if (empty($orders)): ?>
        <p>No orders yet.</p>
    <?php else: ?>
        <!-- Tab Links -->
        <div class="tabs">
            <?php foreach ($statusSteps as $index => $status): ?>
                <div class="tab-link <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="tab-<?php echo strtolower($status); ?>">
                    <?php echo $status; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tab Content -->
        <?php foreach ($statusSteps as $index => $status): ?>
            <div class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo strtolower($status); ?>">
                <?php
                $filteredOrders = array_filter($orders, function($order) use ($status) {
                    return strtoupper(trim($order['status'])) === strtoupper($status);
                });
                ?>
                <?php if (empty($filteredOrders)): ?>
                    <p>No orders in <?php echo $status; ?> status.</p>
                <?php else: ?>
                    <?php foreach ($filteredOrders as $order): ?>
                        <div class="order">
                            <strong>Order #<?php echo $order['id']; ?></strong><br>
                            <p>Amount: RM<?php echo number_format($order['amount'], 2); ?></p>
                            <p>Placed on: <?php echo $order['created_date']; ?></p>
                            <p>Address: <?php echo htmlspecialchars($order['delivery_address']); ?></p>

                            <?php
                            $items = preg_split('/[;,]/', $order['item_description']);
                            $hasItems = false;

                            foreach ($items as $itemStr) {
                                $itemStr = trim($itemStr);
                                if (empty($itemStr)) continue;

                                $matches = [];
                                if (preg_match('/^(.+?)\s*(?:x|\*)\s*(\d+)$/i', $itemStr, $matches)) {
                                    $itemName = trim($matches[1]);
                                    $quantity = (int)$matches[2];

                                    $product = $products[$itemName] ?? null;
                                    $productId = $product['id'] ?? null;
                                    $image = $product && $product['image'] ? "../assets/" . htmlspecialchars($product['image']) : "../assets/default.jpg";
                                    $price = $product['price'] ?? 0;
                                    $itemTotal = $price * $quantity;
                                    $hasItems = true;

                                    // Check if rating exists
                                    $ratingKey = $order['id'] . '-' . $productId;
                                    $existingRating = isset($ratings[$ratingKey]) ? $ratings[$ratingKey] : null;
                                    $isRated = $existingRating !== null;
                                    ?>
                                    <div class="order-item">
                                        <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($itemName); ?>" onerror="this.src='../assets/default.jpg';">
                                        <div class="order-item-details">
                                            <p><?php echo htmlspecialchars($itemName); ?> * <?php echo $quantity; ?></p>
                                            <p>Amount: RM<?php echo number_format($itemTotal, 2); ?></p>
                                        </div>
                                        <?php if (strtolower($status) === 'delivered' && $productId): ?>
                                            <div class="action-buttons">
                                                <form action="product_details.php" method="get" style="margin:0;">
                                                    <input type="hidden" name="id" value="<?php echo $productId; ?>">
                                                    <button type="submit" class="action-btn">Buy Again</button>
                                                </form>
                                                <button class="action-btn <?php echo $isRated ? 'view-rating' : 'rate-btn'; ?>" 
                                                        data-product-id="<?php echo $productId; ?>" 
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($itemName); ?>"
                                                        <?php if ($isRated): ?>
                                                            data-rating="<?php echo $existingRating['rating']; ?>"
                                                            data-comment="<?php echo htmlspecialchars($existingRating['comment']); ?>"
                                                        <?php endif; ?>>
                                                    <?php echo $isRated ? 'View Rating' : 'Rate'; ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                } else {
                                    logError("Failed to parse item: '$itemStr' in order #{$order['id']}");
                                }
                            }

                            if (!$hasItems) {
                                logError("No valid items found in order #{$order['id']}");
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Rating Modal -->
<div class="modal" id="rating-modal">
    <div class="modal-content">
        <span class="close-btn">×</span>
        <h3>Rate <span id="product-name"></span></h3>
        <form class="rating-form" method="post" action="../rating/rating.php">
            <input type="hidden" name="product_id" id="product-id">
            <input type="hidden" name="sales_order_id" id="order-id">
            <div class="star-rating">
                <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
                <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                <input type="radio" name="rating" id="star1" value="1" required><label for="star1">★</label>
            </div>
            <textarea name="comment" placeholder="Your comments..." required></textarea>
            <button type="submit" name="submit_rating">Submit Rating</button>
        </form>
    </div>
</div>

<!-- View Rating Modal -->
<div class="view-rating-modal" id="view-rating-modal">
    <div class="view-rating-content">
        <span class="close-btn">×</span>
        <h3>My Rating for <span id="view-product-name"></span></h3>
        <p><strong>Rating:</strong><span class="star-rating" id="view-stars"></span></p>
        <p><strong>Review:</strong> <span id="view-comment"></span></p>
        <button onclick="closeViewRating()">Close</button>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="confirmation-modal" id="confirmation-modal">
    <div class="confirmation-content">
        <p>Thank you for your feedback!</p>
        <button onclick="closeConfirmation()">OK</button>
    </div>
</div>

<script>
    // Tab switching
    document.querySelectorAll('.tab-link').forEach(button => {
        button.addEventListener('click', () => {
            try {
                document.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                button.classList.add('active');
                const tabId = button.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            } catch (error) {
                console.error('Tab switch error:', error);
            }
        });
    });

    // Modal elements
    const ratingModal = document.getElementById('rating-modal');
    const viewRatingModal = document.getElementById('view-rating-modal');
    const confirmationModal = document.getElementById('confirmation-modal');
    const closeBtns = document.querySelectorAll('.close-btn');
    const productNameSpan = document.getElementById('product-name');
    const viewProductNameSpan = document.getElementById('view-product-name');
    const productIdInput = document.getElementById('product-id');
    const orderIdInput = document.getElementById('order-id');
    const viewStars = document.getElementById('view-stars');
    const viewComment = document.getElementById('view-comment');

    // Rate button click
    document.querySelectorAll('.rate-btn').forEach(button => {
        button.addEventListener('click', () => {
            try {
                const productId = button.getAttribute('data-product-id');
                const orderId = button.getAttribute('data-order-id');
                const productName = button.getAttribute('data-product-name');
                
                productNameSpan.textContent = productName;
                productIdInput.value = productId;
                orderIdInput.value = orderId;
                ratingModal.style.display = 'block';
            } catch (error) {
                console.error('Rate button error:', error);
            }
        });
    });

    // View rating button click
    document.querySelectorAll('.view-rating').forEach(button => {
        button.addEventListener('click', () => {
            try {
                const productName = button.getAttribute('data-product-name');
                const rating = button.getAttribute('data-rating');
                const comment = button.getAttribute('data-comment');
                
                viewProductNameSpan.textContent = productName;
                viewStars.innerHTML = '★'.repeat(rating) + '☆'.repeat(5 - rating);
                viewComment.textContent = comment;
                viewRatingModal.style.display = 'block';
            } catch (error) {
                console.error('View rating error:', error);
            }
        });
    });

    // Close modals
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            ratingModal.style.display = 'none';
            viewRatingModal.style.display = 'none';
            confirmationModal.style.display = 'none';
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target === ratingModal) ratingModal.style.display = 'none';
        if (event.target === viewRatingModal) viewRatingModal.style.display = 'none';
        if (event.target === confirmationModal) confirmationModal.style.display = 'none';
    });

    // Show confirmation if rated
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('rated') === 'true') {
        confirmationModal.style.display = 'block';
    }

    function closeConfirmation() {
        confirmationModal.style.display = 'none';
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    function closeViewRating() {
        viewRatingModal.style.display = 'none';
    }
</script>
</body>
</html>
<?php
// Log execution time
$executionTime = microtime(true) - $startTime;
logError("Page execution time: $executionTime seconds");
?>