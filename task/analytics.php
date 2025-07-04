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

// Fetch top products by rating
$stmt = $pdo->prepare("
    SELECT p.id, p.name, AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM product p
    LEFT JOIN rating r ON p.id = r.product_id
    GROUP BY p.id, p.name
    ORDER BY avg_rating DESC
    LIMIT 5
");
$stmt->execute();
$topRatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch most visited products
$stmt = $pdo->prepare("
    SELECT id, name, visit_count
    FROM product
    ORDER BY visit_count DESC
    LIMIT 5
");
$stmt->execute();
$mostVisitedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch most ordered products
$stmt = $pdo->prepare("
    SELECT id, name, order_count
    FROM product
    ORDER BY order_count DESC
    LIMIT 5
");
$stmt->execute();
$mostOrderedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch product comments if product_id is provided
$productComments = [];
if (isset($_GET['product_id'])) {
    $stmt = $pdo->prepare("
        SELECT r.comment, r.rating, r.created_at
        FROM rating r
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_GET['product_id']]);
    $productComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// For each period, fetch total sales and total orders by date
function getSalesLineDataForPeriod($pdo, $period) {
    $today = new DateTime();
    if ($period === 'quarterly') {
        // Get current quarter start and end
        $month = (int)$today->format('n');
        $year = (int)$today->format('Y');
        $quarter = intdiv($month - 1, 3) + 1;
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;
        $start = sprintf('%04d-%02d-01', $year, $startMonth);
        $end = (new DateTime(sprintf('%04d-%02d-01', $year, $endMonth)))->modify('last day of this month')->format('Y-m-d');
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(so.created_date, '%Y-%m') as month, SUM(so.amount) as total_sales, COUNT(*) as total_orders FROM sales_order so WHERE so.created_date >= ? AND so.created_date <= ? AND so.status = 'Delivered' GROUP BY month ORDER BY month ASC");
        $stmt->execute([$start, $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'labels' => array_column($rows, 'month'),
            'sales' => array_map('floatval', array_column($rows, 'total_sales')),
            'orders' => array_map('intval', array_column($rows, 'total_orders')),
        ];
    } elseif ($period === 'annually') {
        $year = $today->format('Y');
        $start = "$year-01-01";
        $end = $today->format('Y-m-t');
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(so.created_date, '%Y-%m') as month, SUM(so.amount) as total_sales, COUNT(*) as total_orders FROM sales_order so WHERE so.created_date >= ? AND so.created_date <= ? AND so.status = 'Delivered' GROUP BY month ORDER BY month ASC");
        $stmt->execute([$start, $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'labels' => array_column($rows, 'month'),
            'sales' => array_map('floatval', array_column($rows, 'total_sales')),
            'orders' => array_map('intval', array_column($rows, 'total_orders')),
        ];
    } else {
        $interval = [
            'weekly' => '7 DAY',
            'monthly' => '1 MONTH',
        ][$period];
        $stmt = $pdo->prepare("SELECT DATE(so.created_date) as date, SUM(so.amount) as total_sales, COUNT(*) as total_orders FROM sales_order so WHERE so.created_date >= DATE_SUB(CURDATE(), INTERVAL $interval) AND so.status = 'Delivered' GROUP BY DATE(so.created_date) ORDER BY date ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'labels' => array_column($rows, 'date'),
            'sales' => array_map('floatval', array_column($rows, 'total_sales')),
            'orders' => array_map('intval', array_column($rows, 'total_orders')),
        ];
    }
}

$salesPeriods = ['weekly', 'monthly', 'quarterly', 'annually'];
$salesData = [];
foreach ($salesPeriods as $period) {
    $salesData[$period] = getSalesLineDataForPeriod($pdo, $period);
}

// Handle AJAX for period switch
if (isset($_GET['sales_period']) && in_array($_GET['sales_period'], $salesPeriods)) {
    header('Content-Type: application/json');
    echo json_encode($salesData[$_GET['sales_period']]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        .card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
        .nav-button.active { background-color: #2563eb; color: white; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto p-6">
        <h1 class="text-4xl font-bold text-gray-800 mb-8 text-center">Analytics Dashboard</h1>

        <!-- Order Status Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <?php
            $colors = [
                'Confirmed' => 'bg-blue-600',
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

        <!-- Product Analytics Section -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">Product Insights</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Top Rated Products Chart -->
                <div class="card bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Top Rated Products</h3>
                    <canvas id="topRatedChart" height="200"></canvas>
                    <script>
                        const topRatedCtx = document.getElementById('topRatedChart').getContext('2d');
                        new Chart(topRatedCtx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_column($topRatedProducts, 'name')); ?>,
                                datasets: [{
                                    label: 'Average Rating',
                                    data: <?php echo json_encode(array_column($topRatedProducts, 'avg_rating')); ?>,
                                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                    borderColor: 'rgba(59, 130, 246, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: { 
                                        beginAtZero: true, 
                                        max: 5,
                                        ticks: { precision: 0 },
                                        grid: { display: false }
                                    },
                                    x: { grid: { display: false } }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    </script>
                    <div class="mt-4">
                        <?php foreach ($topRatedProducts as $product): ?>
                            <a href="../product/product_details.php?id=<?php echo $product['id']; ?>" class="block text-blue-600 hover:underline"><?php echo htmlspecialchars($product['name']); ?> (<?php echo number_format($product['avg_rating'], 1); ?>/5)</a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Most Visited Products Chart -->
                <div class="card bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Most Visited Products</h3>
                    <canvas id="mostVisitedChart" height="200"></canvas>
                    <script>
                        const mostVisitedCtx = document.getElementById('mostVisitedChart').getContext('2d');
                        new Chart(mostVisitedCtx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_column($mostVisitedProducts, 'name')); ?>,
                                datasets: [{
                                    label: 'Visits',
                                    data: <?php echo json_encode(array_column($mostVisitedProducts, 'visit_count')); ?>,
                                    backgroundColor: 'rgba(245, 158, 11, 0.6)',
                                    borderColor: 'rgba(245, 158, 11, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: { 
                                        beginAtZero: true,
                                        ticks: { precision: 0 },
                                        grid: { display: false }
                                    },
                                    x: { grid: { display: false } }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    </script>
                    <div class="mt-4">
                        <?php foreach ($mostVisitedProducts as $product): ?>
                            <a href="../product/product_details.php?id=<?php echo $product['id']; ?>" class="block text-blue-600 hover:underline"><?php echo htmlspecialchars($product['name']); ?> (<?php echo $product['visit_count']; ?> visits)</a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Most Ordered Products Chart -->
                <div class="card bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Most Ordered Products</h3>
                    <canvas id="mostOrderedChart" height="200"></canvas>
                    <script>
                        const mostOrderedCtx = document.getElementById('mostOrderedChart').getContext('2d');
                        new Chart(mostOrderedCtx, {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode(array_column($mostOrderedProducts, 'name')); ?>,
                                datasets: [{
                                    label: 'Orders',
                                    data: <?php echo json_encode(array_column($mostOrderedProducts, 'order_count')); ?>,
                                    backgroundColor: 'rgba(34, 197, 94, 0.6)',
                                    borderColor: 'rgba(34, 197, 94, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: { 
                                        beginAtZero: true,
                                        ticks: { precision: 0 },
                                        grid: { display: false }
                                    },
                                    x: { grid: { display: false } }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    </script>
                    <div class="mt-4">
                        <?php foreach ($mostOrderedProducts as $product): ?>
                            <a href="../product/product_details.php?id=<?php echo $product['id']; ?>" class="block text-blue-600 hover:underline"><?php echo htmlspecialchars($product['name']); ?> (<?php echo $product['order_count']; ?> orders)</a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Report Section -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">Sales Performance</h2>
            <div class="card bg-white p-6 rounded-xl shadow-lg">
                <div class="flex flex-row items-center space-x-4 mb-4">
                    <button class="period-btn px-4 py-2 rounded bg-blue-600 text-white" data-period="weekly">Weekly</button>
                    <button class="period-btn px-4 py-2 rounded bg-gray-200 text-gray-700" data-period="monthly">Monthly</button>
                    <button class="period-btn px-4 py-2 rounded bg-gray-200 text-gray-700" data-period="quarterly">Quarterly</button>
                    <button class="period-btn px-4 py-2 rounded bg-gray-200 text-gray-700" data-period="annually">Annually</button>
                </div>
                <canvas id="salesChart" height="200"></canvas>
                <script>
                    let salesData = <?php echo json_encode($salesData); ?>;
                    let currentPeriod = 'weekly';
                    let salesChart;
                    function renderSalesChart(period) {
                        let chartData = salesData[period];
                        if (salesChart) salesChart.destroy();
                        const ctx = document.getElementById('salesChart').getContext('2d');
                        let chartType = (period === 'quarterly' || period === 'annually') ? 'bar' : 'line';
                        let chartOptions = {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: { display: true, text: 'Total Sales / Orders' },
                                    grid: { display: false },
                                    stacked: (period === 'quarterly' || period === 'annually')
                                },
                                x: { grid: { display: false }, stacked: (period === 'quarterly' || period === 'annually') }
                            },
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { display: true } }
                        };
                        let datasets = [
                            {
                                label: 'Total Sales ($)',
                                data: chartData.sales,
                                backgroundColor: (period === 'quarterly' || period === 'annually') ? 'rgba(59, 130, 246, 0.7)' : 'rgba(59, 130, 246, 0.1)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                fill: (period !== 'quarterly' && period !== 'annually'),
                                tension: 0.3,
                                yAxisID: 'y',
                                type: chartType
                            },
                            {
                                label: 'Total Orders',
                                data: chartData.orders,
                                backgroundColor: (period === 'quarterly' || period === 'annually') ? 'rgba(16, 185, 129, 0.7)' : 'rgba(16, 185, 129, 0.1)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                fill: (period !== 'quarterly' && period !== 'annually'),
                                tension: 0.3,
                                yAxisID: 'y',
                                type: chartType
                            }
                        ];
                        salesChart = new Chart(ctx, {
                            type: chartType,
                            data: {
                                labels: chartData.labels,
                                datasets: datasets
                            },
                            options: chartOptions
                        });
                    }
                    document.addEventListener('DOMContentLoaded', function() {
                        renderSalesChart('weekly');
                        document.querySelectorAll('.period-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('bg-blue-600', 'text-white', 'bg-gray-200', 'text-gray-700'));
                                this.classList.add('bg-blue-600', 'text-white');
                                currentPeriod = this.dataset.period;
                                if (!salesData[currentPeriod]) {
                                    fetch(`analytics.php?sales_period=${currentPeriod}`)
                                        .then(res => res.json())
                                        .then(data => {
                                            salesData[currentPeriod] = data;
                                            renderSalesChart(currentPeriod);
                                        });
                                } else {
                                    renderSalesChart(currentPeriod);
                                }
                            });
                        });
                    });
                </script>
            </div>
        </div>

        <!-- Product Comments Section -->
        <?php if (!empty($productComments)): ?>
            <div class="mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Product Reviews</h2>
                <div class="card bg-white p-6 rounded-xl shadow-lg">
                    <?php foreach ($productComments as $comment): ?>
                        <div class="mb-4 border-b pb-4">
                            <div class="flex items-center mb-2">
                                <span class="text-yellow-400">★<?php echo $comment['rating']; ?>/5</span>
                                <span class="text-gray-500 text-sm ml-4"><?php echo $comment['created_at']; ?></span>
                            </div>
                            <p class="text-gray-700"><?php echo htmlspecialchars($comment['comment']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
