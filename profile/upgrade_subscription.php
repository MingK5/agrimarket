<?php
session_start();
require '../includes/db.php';

// 1) Make sure the user is logged in AND is a vendor (userType_Id = 3)
if (
    !isset($_SESSION['user'])
    || $_SESSION['user']['userType_Id'] != 3
) {
    header("Location: ../index.php");
    exit();
}

$id      = $_SESSION['user']['id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2) Upgrade in the users table, not vendors
    $stmt = $pdo->prepare("
        UPDATE users
           SET subscription_tier = 'premium'
         WHERE id = ?
    ");
    $stmt->execute([$id]);

    // 3) Keep the session in sync
    $_SESSION['user']['subscription_tier'] = 'premium';

    $success = "Your subscription has been upgraded to Premium – enjoy unlimited Analytics access!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Upgrade Subscription</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 50px auto; max-width: 750px; background: #f9f9f9; }
        .box { padding: 25px; border: 1px solid #ccc; border-radius: 10px; background: white; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 14px; text-align: center; }
        th { background-color: #2e7d32; color: white; }
        .highlight { font-weight: bold; color: #2e7d32; }
        .success { color: green; font-weight: bold; margin-bottom: 20px; }
        .button-group { display: flex; gap: 15px; }
        button {
            background: #2e7d32; color: white; border: none;
            padding: 10px 20px; cursor: pointer; border-radius: 6px; font-weight: bold;
        }
        button:hover { background: #256428; }
        .cancel-btn { background: #999; }
        .cancel-btn:hover { background: #777; }
        a { color: #2e7d32; text-decoration: none; }
    </style>
</head>
<body>
  <div class="box">
    <h2>Upgrade to Premium</h2>

    <table>
      <thead>
        <tr>
          <th>Feature</th>
          <th>Free Tier</th>
          <th>Premium Tier</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Monthly Fee</td>
          <td><strong>RM0</strong></td>
          <td><strong>RM100</strong></td>
        </tr>
        <tr>
          <td>Max Active Products</td>
          <td>10</td>
          <td><span class="highlight">Unlimited</span></td>
        </tr>
        <tr>
          <td>Service Listings</td>
          <td>Yes</td>
          <td>Yes</td>
        </tr>
        <tr>
          <td>Eligibility for Promotion</td>
          <td>No</td>
          <td><span class="highlight">Yes</span></td>
        </tr>
        <tr>
          <td>Analytics Access</td>
          <td>No</td>
          <td><span class="highlight">Unlimited</span></td>
        </tr>
      </tbody>
    </table>

    <?php if ($success): ?>
      <p class="success"><?= htmlspecialchars($success) ?></p>
      <p><a href="/agrimarket/task/analytics.php">Go to Analytics &raquo;</a></p>
      <p><a href="edit_profile.php">Return to Profile &raquo;</a></p>
    <?php else: ?>
      <form method="POST">
        <div class="button-group">
          <button type="submit">Proceed with Payment</button>
          <button type="button" class="cancel-btn"
                  onclick="window.location.href='edit_profile.php'">
            Cancel
          </button>
        </div>
      </form>
    <?php endif; ?>

  </div>
</body>
</html>
