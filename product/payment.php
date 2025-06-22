<?php
session_start();
require '../includes/db.php';

if ($_SESSION['user']['userType_Id'] != 4 || !isset($_SESSION['cart'])) {
    header("Location: ../index.php");
    exit();
}

$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $paymentMethod = $_POST['payment_method'];
    $orderData = [
        'customer_id' => $_SESSION['user']['id'],
        'item_description' => json_encode(array_keys($_SESSION['cart'])),
        'amount' => $total,
        'delivery_address' => $_SESSION['user']['address'],
        'payment_method' => $paymentMethod,
        'created_date' => date('Y-m-d H:i:s')
    ];
    $stmt = $pdo->prepare("INSERT INTO sales_order (customer_id, item_description, amount, delivery_address, payment_method, created_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(array_values($orderData));
    unset($_SESSION['cart']);
    header("Location: order_history.php")
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
    <style>
        .payment { padding: 20px; max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="payment">
        <h2>Payment</h2>
        <p>Total: RM<?php echo $total; ?></p>
        <form method="POST">
            <label>Payment Method:</label>
            <select name="payment_method" required>
                <option value="credit_card">Credit/Debit Card</option>
                <option value="mobile_payment">Mobile Payment</option>
                <option value="bank_transfer">Bank Transfer</option>
            </select>
            <button type="submit">Pay Now</button>
        </form>
        <a href="shopping_cart.php">Back to Cart</a>
    </div>
</body>
</html>