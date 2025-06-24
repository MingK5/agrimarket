<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $productId = $_POST['product_id'];
    $newQuantity = max(1, intval($_POST['quantity'])); // Avoid zero or negative

    if (isset($_SESSION['cart'][$productId])) {
        $cartItem = &$_SESSION['cart'][$productId]; // Reference to modify directly
        $currentQuantity = $cartItem['cart_quantity'];
        $stockAvailable = $cartItem['quantity'];

        // Only allow update if within stock
        if ($newQuantity <= $stockAvailable + $currentQuantity) {
            // Adjust stock in session (not DB here)
            $cartItem['cart_quantity'] = $newQuantity;
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

echo json_encode(['success' => false]);
exit;
?>
