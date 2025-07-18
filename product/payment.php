<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 4 || empty($_SESSION['cart'])) {
    header("Location: ../index.php");
    exit();
}

$cart = $_SESSION['cart'];
$customerId = $_SESSION['user']['id'];
$total = 0;
$itemNames = [];

foreach ($cart as $item) {
    $total += $item['price'] * $item['cart_quantity'];
    $itemNames[] = $item['name'] . " x" . $item['cart_quantity'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $address = $_SESSION['user']['address'] ?? 'N/A';

    // Insert into sales_order
    $stmt = $pdo->prepare("INSERT INTO sales_order (customer_id, item_description, status, amount, delivery_address, payment_method, created_date) VALUES (?, ?, 'Confirmed', ?, ?, ?, NOW())");
    $stmt->execute([
        $customerId,
        implode("; ", $itemNames),
        $total,
        $address,
        $paymentMethod
    ]);

    foreach ($cart as $item) {
        $updateOrder = $pdo->prepare("UPDATE product SET order_count = order_count + ? WHERE id = ?");
        $updateOrder->execute([$item['cart_quantity'], $item['product_id']]);
    }

    // ✅ Clear cart from database
    $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
    $stmt->execute([$customerId]);

    unset($_SESSION['cart']);
    header("Location: customer_orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
    <style>
        .payment-container { max-width: 500px; margin: 40px auto; background: #fff; padding: 20px; border-radius: 10px; }
        .btn { padding: 10px 15px; background: #2d6f2d; color: white; border: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="payment-container">
    <h2>Payment</h2>
    <p>Total Amount: RM<?= number_format($total, 2) ?></p>
    <form method="POST">
        <label>Choose Payment Method:</label>
        <select name="payment_method" required>
            <option value="Credit Card">Credit Card</option>
            <option value="Mobile Payment">Mobile Payment</option>
            <option value="Bank Transfer">Bank Transfer</option>
        </select><br>
        <button type="submit" class="btn">Pay Now</button>
    </form>
</div>
</body>
</html>
