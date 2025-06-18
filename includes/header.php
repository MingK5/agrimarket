<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
            <a href="/agrimarket/analytics/analytics.php">Analytics</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user'])): ?>


            <?php if ($_SESSION['user']['userType_Id'] === 1): ?>
                <a href="/agrimarket/dashboard/admin_home.php">Dashboard</a>
                <a href="/agrimarket/profile/edit_profile.php">Admin Panel</a>
            <?php elseif ($_SESSION['user']['userType_Id'] === 2): ?>
                <a href="/agrimarket/dashboard/staff_home.php">Dashboard</a>
                <a href="/agrimarket/profile/edit_profile.php">Staff Panel</a>
            <?php elseif ($_SESSION['user']['userType_Id'] === 3): ?>
                <a href="/agrimarket/dashboard/vendor_home.php">Dashboard</a>
                <a href="/agrimarket/profile/edit_profile.php">My Profile</a>
            <?php elseif ($_SESSION['user']['userType_Id'] === 4): ?>
                <a href="/agrimarket/dashboard/customer_home.php">Dashboard</a>
                <a href="/agrimarket/profile/edit_profile.php">My Profile</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="/agrimarket/auth/login_customer_vendor.php">Login/Register</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user'])): ?>
            <a href="/agrimarket/notification/notification.php">Notifications</a>
        <?php endif; ?>
    </div>

    <!-- Right: User Info -->
    <div class="header-right">
        <?php 
        if (isset($_SESSION['user'])) {
            echo htmlspecialchars($_SESSION['user']['username']) . " (" . $_SESSION['user']['userType_Id'] . ")";
            echo ' | <a href="/agrimarket/auth/logout.php" style="color:red; margin-left:10px;">Logout</a>';
        }
        ?>
    </div>
</header>
