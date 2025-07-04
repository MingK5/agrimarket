<?php
session_start();
require '../includes/db.php';

// Log errors to a file
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " [rating.php] $message\n", 3, '../logs/error.log');
}

// Check user session and type
if (!isset($_SESSION['user']['userType_Id']) || $_SESSION['user']['userType_Id'] != 4) {
    logError("Unauthorized access attempt: userType_Id not 4 or session not set");
    header("Location: ../../index.php");
    exit();
}

// Handle rating form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    // Validate input
    $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
    $salesOrderId = filter_var($_POST['sales_order_id'] ?? '', FILTER_VALIDATE_INT);
    $rating = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5]
    ]);
    $comment = filter_var($_POST['comment'] ?? '', FILTER_SANITIZE_STRING);

    if (!$productId || !$salesOrderId || !$rating || !$comment) {
        logError("Invalid form data: product_id=$productId, sales_order_id=$salesOrderId, rating=$rating, comment=$comment");
        header("Location: ../product/customer_orders.php?error=invalid_data");
        exit();
    }

    try {
        // Check if rating already exists to prevent duplicates
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM rating WHERE product_id = ? AND sales_order_id = ?");
        $checkStmt->execute([$productId, $salesOrderId]);
        if ($checkStmt->fetchColumn() > 0) {
            logError("Duplicate rating attempt for product_id=$productId, sales_order_id=$salesOrderId");
            header("Location: ../product/customer_orders.php?error=duplicate_rating");
            exit();
        }

        // Insert rating
        $stmt = $pdo->prepare("INSERT INTO rating (product_id, sales_order_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$productId, $salesOrderId, $rating, $comment]);
        logError("Rating submitted successfully: product_id=$productId, sales_order_id=$salesOrderId, rating=$rating");
        header("Location: ../product/customer_orders.php?rated=true");
        exit();
    } catch (PDOException $e) {
        logError("Database error: " . $e->getMessage());
        header("Location: ../product/customer_orders.php?error=database");
        exit();
    }
} else {
    logError("Invalid access to rating.php: Method=" . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . ", submit_rating=" . (isset($_POST['submit_rating']) ? 'set' : 'not set'));
    header("Location: ../product/customer_orders.php");
    exit();
}
?>