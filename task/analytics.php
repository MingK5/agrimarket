<?php
session_start();
require '../includes/db.php';

if ((($_SESSION['user']['userType_Id'] == 3 && $_SESSION['user']['subscription_tier'] == 'free') || $_SESSION['user']['userType_Id'] == 4)) {
    header("Location: ../index.php");
    exit();
}


$userId = $_SESSION['user']['id'];
$statusSteps = ['Confirmed', 'Packed', 'Shipped', 'Delivered'];

// Fetch count of orders for each status
$orderCounts = [];
foreach ($statusSteps as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales_order WHERE status = ?");
    $stmt->execute([$status]);
    $orderCounts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="from-blue-100 to-gray-100 min-h-screen flex flex-col">
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto p-6 flex-grow" style="display:flex; flex-direction: column; align-items:center; justify-content:center">
        <h2 class="text-4xl font-extrabold text-center text-gray-800 mb-8">Order Status Overview</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php
            $colors = [
                'Confirmed' => 'bg-blue-500',
                'Packed' => 'bg-yellow-500',
                'Shipped' => 'bg-orange-500',
                'Delivered' => 'bg-green-500'
            ];
            foreach ($statusSteps as $status):
            ?>
                <div class="relative <?php echo $colors[$status]; ?> text-white rounded-xl shadow-lg p-6 transform hover:scale-105 transition-transform duration-300">
                    <div class="absolute top-0 right-0 w-16 h-16 opacity-10">
                        <svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($status); ?></h3>
                    <p class="text-3xl font-bold mt-2"><?php echo $orderCounts[$status]; ?> Orders</p>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>