<?php
/*
 * Reports page for the Mini Inventory System.
 *
 * Generates aggregated data visualisations using Chart.js.  Requires
 * authentication; if the user is not logged in, they are redirected
 * to the login page.  Charts include inventory levels by product,
 * orders by status, purchases vs orders over the last 7 days, and
 * additional time‑series analyses such as purchases per day (30 days)
 * and purchases/orders per month (12 months).  These examples
 * illustrate how realistic reporting can be built on top of a CRUD
 * system, enabling tracking and analysis of warehouse activity.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'sqldb';
$user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'root';
$pass = getenv('DB_PASSWORD');
if ($pass === false) {
    $pass = getenv('MYSQL_PASSWORD');
    if ($pass === false) {
        $pass = getenv('MYSQL_ROOT_PASSWORD') ?: '';
    }
}
$connection = mysqli_connect($host, $user, $pass, $db);
if (!$connection) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Inventory levels by product
$inventoryData = [];
$res = mysqli_query($connection, "SELECT name, quantity FROM products ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $inventoryData[] = $row;
}

// Orders by status
$statusCounts = [];
$res = mysqli_query($connection, "SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
while ($row = mysqli_fetch_assoc($res)) {
    $statusCounts[$row['status']] = (int)$row['count'];
}

// Purchases vs orders over last 7 days
$dates7 = [];
$purchases7 = [];
$orders7 = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates7[] = $date;
    // Sum quantities for this date
    $resP = mysqli_query($connection, "SELECT COALESCE(SUM(quantity),0) AS qty FROM purchases WHERE purchase_date = '$date'");
    $purchases7[] = (int)mysqli_fetch_assoc($resP)['qty'];
    $resO = mysqli_query($connection, "SELECT COALESCE(SUM(quantity),0) AS qty FROM orders WHERE order_date = '$date'");
    $orders7[] = (int)mysqli_fetch_assoc($resO)['qty'];
}

// Purchases per day for last 30 days
$dates30 = [];
$purchases30 = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates30[] = $date;
    $resP = mysqli_query($connection, "SELECT COALESCE(SUM(quantity),0) AS qty FROM purchases WHERE purchase_date = '$date'");
    $purchases30[] = (int)mysqli_fetch_assoc($resP)['qty'];
}

// Purchases per month for last 12 months
$months12 = [];
$purchasesPerMonth = [];
$ordersPerMonth = [];
for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $label = date('M Y', strtotime($monthStart));
    $months12[] = $label;
    // Sum purchases for month
    $resP = mysqli_query($connection, "SELECT COALESCE(SUM(quantity),0) AS qty FROM purchases WHERE purchase_date >= '$monthStart' AND purchase_date < DATE_ADD('$monthStart', INTERVAL 1 MONTH)");
    $purchasesPerMonth[] = (int)mysqli_fetch_assoc($resP)['qty'];
    // Sum orders for month
    $resO = mysqli_query($connection, "SELECT COALESCE(SUM(quantity),0) AS qty FROM orders WHERE order_date >= '$monthStart' AND order_date < DATE_ADD('$monthStart', INTERVAL 1 MONTH)");
    $ordersPerMonth[] = (int)mysqli_fetch_assoc($resO)['qty'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reports - Mini Inventory System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.2/dist/chart.umd.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Inventory System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php?page=products">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php?page=suppliers">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php?page=purchases">Purchases</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php?page=orders">Orders</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="reports.php">Reports</a></li>
            </ul>
            <span class="navbar-text me-3">Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a class="btn btn-outline-light" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <h2 class="mb-4">Reports</h2>
    <div class="row">
        <div class="col-md-6 mb-4">
            <h5>Inventory Levels by Product</h5>
            <canvas id="inventoryChart"></canvas>
        </div>
        <div class="col-md-6 mb-4">
            <h5>Orders by Status</h5>
            <canvas id="statusChart"></canvas>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-4">
            <h5>Purchases vs Orders (Last 7 Days)</h5>
            <canvas id="weekChart"></canvas>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-4">
            <h5>Purchases per Day (Last 30 Days)</h5>
            <canvas id="dayChart"></canvas>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <h5>Purchases vs Orders per Month (Last 12 Months)</h5>
            <canvas id="monthChart"></canvas>
        </div>
    </div>
</div>

<script>
// Data from PHP
const inventoryData = <?php echo json_encode($inventoryData); ?>;
const statusData = <?php echo json_encode($statusCounts); ?>;
const dates7 = <?php echo json_encode($dates7); ?>;
const purchases7 = <?php echo json_encode($purchases7); ?>;
const orders7 = <?php echo json_encode($orders7); ?>;
const dates30 = <?php echo json_encode($dates30); ?>;
const purchases30 = <?php echo json_encode($purchases30); ?>;
const months12 = <?php echo json_encode($months12); ?>;
const purchasesMonth = <?php echo json_encode($purchasesPerMonth); ?>;
const ordersMonth = <?php echo json_encode($ordersPerMonth); ?>;

// Inventory chart
new Chart(document.getElementById('inventoryChart'), {
    type: 'bar',
    data: {
        labels: inventoryData.map(row => row.name),
        datasets: [{
            label: 'Quantity',
            data: inventoryData.map(row => parseInt(row.quantity)),
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

// Orders status chart
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(statusData),
        datasets: [{
            data: Object.values(statusData),
            backgroundColor: ['rgba(255,99,132,0.7)','rgba(75,192,192,0.7)','rgba(255,205,86,0.7)','rgba(153,102,255,0.7)']
        }]
    }
});

// Weekly purchases vs orders chart
new Chart(document.getElementById('weekChart'), {
    type: 'line',
    data: {
        labels: dates7,
        datasets: [
            {
                label: 'Purchases',
                data: purchases7,
                borderColor: 'rgba(54,162,235,1)',
                backgroundColor: 'rgba(54,162,235,0.2)',
                fill: true
            },
            {
                label: 'Orders',
                data: orders7,
                borderColor: 'rgba(255,99,132,1)',
                backgroundColor: 'rgba(255,99,132,0.2)',
                fill: true
            }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

// Purchases per day (30 days) chart
new Chart(document.getElementById('dayChart'), {
    type: 'line',
    data: {
        labels: dates30,
        datasets: [{
            label: 'Purchases',
            data: purchases30,
            borderColor: 'rgba(75,192,192,1)',
            backgroundColor: 'rgba(75,192,192,0.2)',
            fill: true
        }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});

// Purchases vs orders per month (12 months) chart
new Chart(document.getElementById('monthChart'), {
    type: 'bar',
    data: {
        labels: months12,
        datasets: [
            {
                label: 'Purchases',
                data: purchasesMonth,
                backgroundColor: 'rgba(54, 162, 235, 0.7)'
            },
            {
                label: 'Orders',
                data: ordersMonth,
                backgroundColor: 'rgba(255, 159, 64, 0.7)'
            }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>