<?php
// includes/notification_count.php
// computes $unreadActivities and $unreadPromotions

if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/db.php';

$userId      = $_SESSION['user']['id'];
$userType_Id = $_SESSION['user']['userType_Id'];

if (!isset($_SESSION['notif_read']) || !is_array($_SESSION['notif_read'])) {
    $_SESSION['notif_read'] = [];
}

$activityKeys = [];
$promotionKeys = [];

// Delivered Orders
if ($userType_Id === 4) {
    $stmt = $pdo->prepare("SELECT id FROM sales_order WHERE customer_id = ? AND delivered_date IS NOT NULL");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->query("SELECT id FROM sales_order WHERE delivered_date IS NOT NULL");
}
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $activityKeys[] = "order_{$row['id']}";
}

// Low Stock for Vendor
if ($userType_Id === 3) {
    $stmt = $pdo->prepare("SELECT id FROM product WHERE vendor_id = ? AND quantity < reorder_level");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activityKeys[] = "stock_{$row['id']}";
    }
}

// Promotion
if ($userType_Id === 4) {
    $promotionKeys[] = 'promo_0707';
}

// Count unread
$unreadActivities = 0;
foreach ($activityKeys as $key) {
    if (!in_array($key, $_SESSION['notif_read'], true)) {
        $unreadActivities++;
    }
}
$unreadPromotions = 0;
foreach ($promotionKeys as $key) {
    if (!in_array($key, $_SESSION['notif_read'], true)) {
        $unreadPromotions++;
    }
}

// Only respond if accessed directly via AJAX (not via include)
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'unreadActivities' => $unreadActivities,
        'unreadPromotions' => $unreadPromotions
    ]);
    exit;
}