<?php
// includes/notification_count.php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/db.php';

$userId      = $_SESSION['user']['id']        ?? null;
$userType_Id = $_SESSION['user']['userType_Id'] ?? null;

if (!isset($_SESSION['notif_read']) || !is_array($_SESSION['notif_read'])) {
    $_SESSION['notif_read'] = [];
}

/* ------------------------------------------------------------------
 * 0. Early-exit for roles that never see notifications (admin, staff)
 * -----------------------------------------------------------------*/
if (!in_array($userType_Id, [3, 4], true)) {
    // Provide zeros if the script is being fetched via AJAX
    if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['unreadActivities' => 0, 'unreadPromotions' => 0]);
        exit;
    }
    // If included by header.php just return silently
    return;
}

/* ---------------------------------------------------------
 * 1. Build notification keys for customers (4) & vendors (3)
 * --------------------------------------------------------*/
$activityKeys   = [];
$promotionKeys  = [];

/* Delivered orders */
if ($userType_Id === 4) {
    // Customer
    $stmt = $pdo->prepare(
        "SELECT id FROM sales_order 
         WHERE customer_id = ? AND delivered_date IS NOT NULL"
    );
    $stmt->execute([$userId]);
} else { // $userType_Id === 3 (vendor)
    $stmt = $pdo->prepare(
        "SELECT id FROM sales_order 
         WHERE vendor_id = ? AND delivered_date IS NOT NULL"
    );
    $stmt->execute([$userId]);
}

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $activityKeys[] = "order_{$row['id']}";
}

/* Low-stock alerts (vendor only) */
if ($userType_Id === 3) {
    $stmt = $pdo->prepare(
        "SELECT id FROM product 
         WHERE vendor_id = ? AND quantity <= reorder_level"
    );
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activityKeys[] = "stock_{$row['id']}";
    }
}

/* Promotions (customer only) */
if ($userType_Id === 4) {
    $promotionKeys[] = 'promo_0707';
}

/* --------------------------------------
 * 2. Count unread based on session state
 * -------------------------------------*/
$unreadActivities = 0;
foreach ($activityKeys as $k) {
    if (!in_array($k, $_SESSION['notif_read'], true)) $unreadActivities++;
}

$unreadPromotions = 0;
foreach ($promotionKeys as $k) {
    if (!in_array($k, $_SESSION['notif_read'], true)) $unreadPromotions++;
}

/* --------------------------------------------------
 * 3. If fetched directly (AJAX) return JSON response
 * -------------------------------------------------*/
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'unreadActivities' => $unreadActivities,
        'unreadPromotions' => $unreadPromotions
    ]);
    exit;
}
