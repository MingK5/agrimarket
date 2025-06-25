<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$roleMap = [
    1 => 'Admin',
    2 => 'Staff',
    3 => 'Vendor',
    4 => 'Customer'
];

$roleName = '';
if (isset($_SESSION['user'])) {
    $type = $_SESSION['user']['userType_Id'];
    $roleName = $roleMap[$type] ?? '';
}

// new: compute unread notification counts
  $unreadActivities = 0;
  $unreadPromotions = 0;
  if (isset($_SESSION['user'])) {
      include __DIR__ . '/notification_count.php';
  }

// figure out whether to show the Analytics link
$showAnalytics = false;
if (isset($_SESSION['user'])) {
    $userType_Id   = $_SESSION['user']['userType_Id'];

    // admins and staff always get it
    if ($userType_Id === 1 || $userType_Id === 2) {
        $showAnalytics = true;
    }
    // vendors only if premium
    elseif ($userType_Id === 3) {
        require_once __DIR__ . '/db.php';

        $stmt = $pdo->prepare("
            SELECT subscription_tier
              FROM users
             WHERE id = ?
        ");
        $stmt->execute([ $_SESSION['user']['id'] ]);
        $tier = $stmt->fetchColumn();

        if ($tier === 'premium') {
            $showAnalytics = true;
        }
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  fetch('/agrimarket/includes/notification_count.php?ajax=1')
    .then(res => res.json())
    .then(data => {
      if ((data.unreadActivities ?? 0) + (data.unreadPromotions ?? 0) > 0) {
        const notifLink = document.querySelector('a[href="/agrimarket/notification/notification.php"]');
        if (notifLink && !notifLink.querySelector('.red-dot')) {
          const dot = document.createElement('span');
          dot.className = 'red-dot';
          notifLink.appendChild(dot);
        }
      }
    });
});
</script>

<style>
    body {
        margin: 0;
        font-family: 'Segoe UI', sans-serif;
    }

    header {
        display: flex;
        align-items: center;
        padding: 10px 100px;
        background-color: #fdfdfd;
        border-bottom: 1px solid #ddd;
    }

    .header-left {
        display: flex;
        align-items: center;
        padding-left: 150px; 
    }

    .header-left img {
        height: 48px;
    }

    .header-center {
        flex-grow: 1;
        display: flex;
        justify-content: center;
        gap: 30px;
    }

    .header-center a {
        text-decoration: none;
        color: #1e1e1e;
        font-size: 16px;
        transition: color 0.3s;
    }

    .header-center a:hover {
        color: #3a8f3a;
    }

    .header-right {
        font-size: 14px;
        color: #444;
        white-space: nowrap;
    }

    .red-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: red;
        border-radius: 50%;
        margin-left: 4px;
        vertical-align: middle;
    }
</style>

<header>
    <!-- Left: Logo -->
    <div class="header-left">
        <a href="/agrimarket/index.php">
            <img src="/agrimarket/assets/logo.jpg" alt="AgriMarket Logo">
        </a>
    </div>

    <!-- Center: Nav -->
    <div class="header-center">
        <a href="/agrimarket/index.php">Home</a>
        <a href="/agrimarket/product/product.php">Products</a>

        <?php if ($showAnalytics): ?>
            <a href="/agrimarket/task/analytics.php">Analytics</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user'])): ?>


            <?php if ($_SESSION['user']['userType_Id'] === 1): ?>
                <a href="/agrimarket/task/task_assignment.php">Task Assignment</a>
                <a href="/agrimarket/profile/staff_management.php">Staff Management</a>
                <a href="/agrimarket/profile/edit_profile.php">Profile</a>
            <?php elseif ($_SESSION['user']['userType_Id'] === 2): ?>
                <a href="/agrimarket/profile/edit_profile.php">Profile</a>
            <?php elseif ($_SESSION['user']['userType_Id'] === 3): ?>
                <a href="/agrimarket/product/product_upload.php">Bulk Upload</a>
                <a href="/agrimarket/profile/edit_profile.php">Profile</a>
            <?php elseif ($_SESSION['user']['userType_Id'] === 4): ?>
                <a href="/agrimarket/product/customer_orders.php">Orders</a>
                <a href="/agrimarket/product/bookmark.php">Bookmarks</a>
                <a href="/agrimarket/profile/edit_profile.php">Profile</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="/agrimarket/auth/login_customer_vendor.php">Login/Register</a>
        <?php endif; ?>

        <!-- only show Notifications for vendor (3) or customer (4) -->
        <?php if (isset($_SESSION['user']) && in_array($_SESSION['user']['userType_Id'], [3,4], true)): ?>
          <a href="/agrimarket/notification/notification.php">
            Notifications
            <?php if ($unreadActivities + $unreadPromotions > 0): ?>
              <span class="red-dot"></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>
    </div>

    <!-- Right: User Info -->
    <div class="header-right">
    <?php 
      if (isset($_SESSION['user'])) {
          echo htmlspecialchars($_SESSION['user']['username'])
             . " (" . htmlspecialchars($roleName) . ")"
             . ' | <a href="/agrimarket/auth/logout.php" style="color:red; margin-left:10px;">Logout</a>';
      }
    ?>
    </div>
</header>
