<?php
/**
 * ByteShop - Admin Dashboard
 * 
 * Displays system overview with stats, charts, and analytics
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';


// Require admin access
require_admin();

// Fetch dashboard statistics
try {
    // Total Users by Role
    $stmt = $pdo->query("
        SELECT 
            role,
            COUNT(*) as count
        FROM users
        GROUP BY role
    ");
    $user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_users = array_sum($user_stats);
    $total_customers = $user_stats['customer'] ?? 0;
    $total_shop_owners = $user_stats['shop_owner'] ?? 0;
    
    // Total Markets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM markets WHERE status = 'active'");
    $total_markets = $stmt->fetch()['count'];
    
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
    $total_products = $stmt->fetch()['count'];
    
    // Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $total_orders = $stmt->fetch()['count'];
    
    // Revenue Statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value
        FROM orders
    ");
    $revenue_stats = $stmt->fetch();
    $total_revenue = $revenue_stats['total_revenue'] ?? 0;
    $avg_order_value = $revenue_stats['avg_order_value'] ?? 0;
    
    // Today's Revenue
    $stmt = $pdo->query("
        SELECT SUM(total_amount) as today_revenue
        FROM orders
        WHERE DATE(order_date) = CURDATE()
    ");
    $today_revenue = $stmt->fetch()['today_revenue'] ?? 0;
    
    // This Month's Revenue
    $stmt = $pdo->query("
        SELECT SUM(total_amount) as month_revenue
        FROM orders
        WHERE MONTH(order_date) = MONTH(CURDATE())
        AND YEAR(order_date) = YEAR(CURDATE())
    ");
    $month_revenue = $stmt->fetch()['month_revenue'] ?? 0;
    
    // Order Status Distribution
    $stmt = $pdo->query("
        SELECT 
            order_status,
            COUNT(*) as count
        FROM orders
        GROUP BY order_status
    ");
    $order_status_data = $stmt->fetchAll();
    
    // Recent Orders
    $stmt = $pdo->query("
        SELECT 
            o.order_id,
            o.total_amount,
            o.order_status,
            o.order_date,
            u.name as customer_name
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $recent_orders = $stmt->fetchAll();
    
    // Top Selling Products
    $stmt = $pdo->query("
        SELECT 
            p.product_name,
            m.market_name,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN markets m ON oi.market_id = m.market_id
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $top_products = $stmt->fetchAll();
    
    // Top Markets by Revenue
    $stmt = $pdo->query("
        SELECT 
            m.market_name,
            m.location,
            COUNT(DISTINCT oi.order_id) as total_orders,
            SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN markets m ON oi.market_id = m.market_id
        GROUP BY m.market_id
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $top_markets = $stmt->fetchAll();
    
    // Monthly Revenue Chart Data (Last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') as month,
            SUM(total_amount) as revenue
        FROM orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_revenue = $stmt->fetchAll();
    
    // Category-wise Product Distribution
    $stmt = $pdo->query("
        SELECT 
            category,
            COUNT(*) as count
        FROM products
        WHERE status = 'active'
        GROUP BY category
        ORDER BY count DESC
        LIMIT 10
    ");
    $category_distribution = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die("Error fetching dashboard data: " . $e->getMessage());
}

// Fetch data for Total Revenue Chart (Last 12 months)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(order_date, '%b') as month_name,
        MONTH(order_date) as month_num,
        SUM(total_amount) as revenue
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY YEAR(order_date), MONTH(order_date)
    ORDER BY order_date ASC
");
$total_revenue_chart = $stmt->fetchAll();

// Create arrays for chart (fill missing months with 0)
$total_revenue_months = [];
$total_revenue_values = [];
$month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Fill all 12 months
for ($i = 0; $i < 12; $i++) {
    $total_revenue_months[] = $month_names[$i];
    $total_revenue_values[] = 0;
}

// Update with actual data
foreach ($total_revenue_chart as $row) {
    $month_index = (int)$row['month_num'] - 1;
    $total_revenue_values[$month_index] = (float)$row['revenue'];
}

// Fetch data for Today's Revenue Chart (Hourly breakdown)
$stmt = $pdo->query("
    SELECT 
        HOUR(order_date) as hour,
        SUM(total_amount) as revenue
    FROM orders
    WHERE DATE(order_date) = CURDATE()
    GROUP BY HOUR(order_date)
    ORDER BY hour ASC
");
$today_revenue_chart = $stmt->fetchAll();

// Create hourly data - cumulative throughout the day
$today_hours = ['6AM', '8AM', '10AM', '12PM', '2PM', '4PM', '6PM', '8PM'];
$today_revenue_values = [0, 0, 0, 0, 0, 0, 0, 0];

// If there's data for today, accumulate it
if (!empty($today_revenue_chart)) {
    $cumulative = 0;
    $hour_data = [];
    
    // First, collect all hourly data
    foreach ($today_revenue_chart as $row) {
        $hour_data[(int)$row['hour']] = (float)$row['revenue'];
    }
    
    // Then create cumulative values for display hours
    $display_hours = [6, 8, 10, 12, 14, 16, 18, 20];
    foreach ($display_hours as $index => $hour) {
        // Add all revenue from hours up to and including this hour
        for ($h = 0; $h <= $hour; $h++) {
            if (isset($hour_data[$h])) {
                $cumulative += $hour_data[$h];
                unset($hour_data[$h]); // Remove to avoid counting twice
            }
        }
        $today_revenue_values[$index] = $cumulative;
    }
} else {
    // No orders today - show last 7 days hourly pattern instead
    $stmt = $pdo->query("
        SELECT 
            HOUR(order_date) as hour,
            AVG(total_amount) as revenue
        FROM orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY HOUR(order_date)
        ORDER BY hour ASC
    ");
    $week_hourly = $stmt->fetchAll();
    
    $hour_mapping = [6 => 0, 8 => 1, 10 => 2, 12 => 3, 14 => 4, 16 => 5, 18 => 6, 20 => 7];
    foreach ($week_hourly as $row) {
        $hour = (int)$row['hour'];
        if (isset($hour_mapping[$hour])) {
            $today_revenue_values[$hour_mapping[$hour]] = (float)$row['revenue'];
        }
    }
}

// Fetch data for This Month Chart (Weekly breakdown)
$stmt = $pdo->query("
    SELECT 
        WEEK(order_date, 1) - WEEK(DATE_SUB(order_date, INTERVAL DAYOFMONTH(order_date) - 1 DAY), 1) + 1 as week_num,
        SUM(total_amount) as revenue
    FROM orders
    WHERE MONTH(order_date) = MONTH(CURDATE())
    AND YEAR(order_date) = YEAR(CURDATE())
    GROUP BY week_num
    ORDER BY week_num ASC
");
$month_revenue_chart = $stmt->fetchAll();

// Create weekly data
$month_weeks = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
$month_revenue_values = [0, 0, 0, 0];

foreach ($month_revenue_chart as $row) {
    $week = (int)$row['week_num'] - 1;
    if ($week >= 0 && $week < 4) {
        $month_revenue_values[$week] = (float)$row['revenue'];
    }
}

// Fetch data for Average Order Value Chart (Last 7 days)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(order_date, '%a') as day_name,
        DAYOFWEEK(order_date) as day_num,
        AVG(total_amount) as avg_value
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(order_date)
    ORDER BY order_date ASC
");
$avg_order_chart = $stmt->fetchAll();

// Create daily data
$avg_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$avg_order_values = [0, 0, 0, 0, 0, 0, 0];

foreach ($avg_order_chart as $row) {
    // MySQL DAYOFWEEK returns 1 (Sunday) to 7 (Saturday)
    // Convert to 0 (Monday) to 6 (Sunday)
    $day_index = (int)$row['day_num'] - 2;
    if ($day_index < 0) $day_index = 6; // Sunday
    
    if ($day_index >= 0 && $day_index < 7) {
        $avg_order_values[$day_index] = (float)$row['avg_value'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ByteShop</title>
<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fa7f0cff;
            color: #e0e0e0;
        }

        .navbar {
            background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            border-bottom: 1px solid #2a2a2a;
        }

        .navbar h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar .user-info span {
            color: #b0b0b0;
            font-weight: 500;
        }

        .navbar .logout-btn {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 600;
        }

        .navbar .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.4);
        }

        .container {
            flex: 1;
            padding: 30px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Navigation Links */
        .nav-links {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 2.5rem;
            padding: 1.2rem;
            background: #161616;
            border-radius: 16px;
            flex-wrap: wrap;
            border: 1px solid #2a2a2a;
        }

        .nav-links a {
            padding: 0.8rem 1.5rem;
            background: #1f1f1f;
            color: #b0b0b0;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
            border: 1px solid #2a2a2a;
        }

        .nav-links a:hover {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 107, 53, 0.3);
            border-color: transparent;
        }

        .nav-links a.active {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border-color: transparent;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            transition: all 0.3s;
            border: 1px solid #2a2a2a;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(255, 107, 53, 0.2);
            border-color: #ff6b35;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card p {
            color: #909090;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .stat-card small {
            color: #707070;
            font-size: 0.85rem;
        }
       /* Revenue Cards - Add to existing styles */
.revenue-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.revenue-card {
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(255, 107, 53, 0.3);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    min-height: 140px;
}

.revenue-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 107, 53, 0.5);
}

.revenue-card h4 {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
    font-weight: 500;
    transition: opacity 0.3s;
}

.revenue-card h2 {
    font-size: 2rem;
    font-weight: 700;
    transition: opacity 0.3s;
}

.revenue-card .chart-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 1.5rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
}

.revenue-card:hover .chart-container {
    opacity: 1;
    pointer-events: auto;
}

.revenue-card:hover h4,
.revenue-card:hover h2 {
    opacity: 0;
}

.revenue-card canvas {
    max-height: 100px;
}

        
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .chart-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            border: 1px solid #2a2a2a;
        }

        .chart-card h3 {
            margin-bottom: 1.5rem;
            color: #e0e0e0;
            border-bottom: 2px solid #ff6b35;
            padding-bottom: 0.8rem;
            font-weight: 600;
        }

        .table-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            margin-bottom: 2.5rem;
            border: 1px solid #2a2a2a;
        }

        .table-section h3 {
            margin-bottom: 1.5rem;
            color: #e0e0e0;
            border-bottom: 2px solid #ff6b35;
            padding-bottom: 0.8rem;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #2a2a2a;
        }

        th {
            background: #0f0f0f;
            font-weight: 600;
            color: #ff6b35;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        td {
            color: #b0b0b0;
        }

        tr:hover {
            background: #1f1f1f;
        }

        .badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-placed { 
            background: rgba(33, 150, 243, 0.15); 
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        .badge-packed { 
            background: rgba(255, 152, 0, 0.15); 
            color: #ff9800;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }
        .badge-shipped { 
            background: rgba(156, 39, 176, 0.15); 
            color: #9c27b0;
            border: 1px solid rgba(156, 39, 176, 0.3);
        }
        .badge-delivered { 
            background: rgba(76, 175, 80, 0.15); 
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .badge-cancelled { 
            background: rgba(244, 67, 54, 0.15); 
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        canvas {
            max-height: 300px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .charts-section {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 20px;
            }
        }
</style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
</head>
<body>
    <div class="navbar">
        <h1>🛒 ByteShop Admin Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars(get_user_name()); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
         <div class="nav-links">
        <a href="index.php" class="active">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="markets.php">Markets</a>
        <a href="products.php">Products</a>
        <a href="orders.php">Orders</a>
        <a href="analytics.php">Analytics & Reports</a>
    </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <h3><?php echo number_format($total_users); ?></h3>
                <p>Total Users</p>
                <small>Customers: <?php echo $total_customers; ?> | Owners: <?php echo $total_shop_owners; ?></small>
            </div>

            <div class="stat-card">
                <div class="icon">🏪</div>
                <h3><?php echo number_format($total_markets); ?></h3>
                <p>Active Markets</p>
            </div>

            <div class="stat-card">
                <div class="icon">📦</div>
                <h3><?php echo number_format($total_products); ?></h3>
                <p>Total Products</p>
            </div>

            <div class="stat-card">
                <div class="icon">🛍️</div>
                <h3><?php echo number_format($total_orders); ?></h3>
                <p>Total Orders</p>
            </div>
        </div>

        <!-- Revenue Stats -->
        <div class="revenue-stats">
    <div class="revenue-card">
        <h4>Total Revenue</h4>
        <h2>₹<?php echo number_format($total_revenue, 2); ?></h2>
        <div class="chart-container">
            <canvas id="totalRevenueChart"></canvas>
        </div>
    </div>

    <div class="revenue-card">
        <h4>Today's Revenue</h4>
        <h2>₹<?php echo number_format($today_revenue, 2); ?></h2>
        <div class="chart-container">
            <canvas id="todayRevenueChart"></canvas>
        </div>
    </div>

    <div class="revenue-card">
        <h4>This Month</h4>
        <h2>₹<?php echo number_format($month_revenue, 2); ?></h2>
        <div class="chart-container">
            <canvas id="monthRevenueChart"></canvas>
        </div>
    </div>

    <div class="revenue-card">
        <h4>Average Order Value</h4>
        <h2>₹<?php echo number_format($avg_order_value, 2); ?></h2>
        <div class="chart-container">
            <canvas id="avgOrderChart"></canvas>
        </div>
    </div>
</div>

<!-- Add this JavaScript before the closing </script> tag -->
<script>
// Revenue Card Charts - Initialize after page load
document.addEventListener('DOMContentLoaded', function() {
    // Common options for mini charts
    const miniChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                enabled: true,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                borderColor: 'rgba(255, 255, 255, 0.3)',
                borderWidth: 1,
                padding: 8,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return '₹' + context.parsed.y.toLocaleString('en-IN', {maximumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            x: {
                display: false,
                grid: { display: false }
            },
            y: {
                display: false,
                grid: { display: false }
            }
        },
        elements: {
            point: { radius: 0, hitRadius: 10, hoverRadius: 3 },
            line: { tension: 0.4, borderWidth: 2 }
        }
    };

    // Total Revenue Chart - Last 12 months from database
    new Chart(document.getElementById('totalRevenueChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($total_revenue_months); ?>,
            datasets: [{
                data: <?php echo json_encode($total_revenue_values); ?>,
                borderColor: 'rgba(255, 255, 255, 0.9)',
                backgroundColor: 'rgba(255, 255, 255, 0.2)',
                fill: true
            }]
        },
        options: miniChartOptions
    });

    // Today's Revenue Chart - Hourly from database
    new Chart(document.getElementById('todayRevenueChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($today_hours); ?>,
            datasets: [{
                data: <?php echo json_encode($today_revenue_values); ?>,
                borderColor: 'rgba(255, 255, 255, 0.9)',
                backgroundColor: 'rgba(255, 255, 255, 0.2)',
                fill: true
            }]
        },
        options: miniChartOptions
    });

    // This Month Chart - Weekly from database
    new Chart(document.getElementById('monthRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($month_weeks); ?>,
            datasets: [{
                data: <?php echo json_encode($month_revenue_values); ?>,
                backgroundColor: 'rgba(255, 255, 255, 0.3)',
                borderColor: 'rgba(255, 255, 255, 0.9)',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            ...miniChartOptions,
            scales: {
                ...miniChartOptions.scales,
                x: { ...miniChartOptions.scales.x, grid: { display: false } }
            }
        }
    });

    // Average Order Value Chart - Last 7 days from database
    new Chart(document.getElementById('avgOrderChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($avg_days); ?>,
            datasets: [{
                data: <?php echo json_encode($avg_order_values); ?>,
                borderColor: 'rgba(255, 255, 255, 0.9)',
                backgroundColor: 'rgba(255, 255, 255, 0.2)',
                fill: true
            }]
        },
        options: miniChartOptions
    });
});
</script>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <h3>📈 Monthly Revenue (Last 6 Months)</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>📊 Order Status Distribution</h3>
                <canvas id="orderStatusChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>🏷️ Product Categories</h3>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="table-section">
            <h3>🛍️ Recent Orders</h3>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $order['order_status']; ?>"><?php echo ucfirst($order['order_status']); ?></span></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Products -->
        <div class="table-section">
            <h3>🔥 Top Selling Products</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Market</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['market_name']); ?></td>
                        <td><?php echo number_format($product['total_sold']); ?></td>
                        <td>₹<?php echo number_format($product['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Markets -->
        <div class="table-section">
            <h3>🏪 Top Markets by Revenue</h3>
            <table>
                <thead>
                    <tr>
                        <th>Market</th>
                        <th>Location</th>
                        <th>Total Orders</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_markets as $market): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($market['market_name']); ?></td>
                        <td><?php echo htmlspecialchars($market['location']); ?></td>
                        <td><?php echo number_format($market['total_orders']); ?></td>
                        <td>₹<?php echo number_format($market['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Chart.js defaults for dark theme
        Chart.defaults.color = '#b0b0b0';
        Chart.defaults.borderColor = '#2a2a2a';

        // Monthly Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>,
                    borderColor: '#ff6b35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        grid: {
                            color: '#2a2a2a'
                        }
                    },
                    x: {
                        grid: {
                            color: '#2a2a2a'
                        }
                    }
                }
            }
        });

        // Order Status Chart
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        new Chart(orderStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($order_status_data, 'order_status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($order_status_data, 'count')); ?>,
                    backgroundColor: ['#ff6b35', '#f7931e', '#2196f3', '#9c27b0', '#4caf50']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($category_distribution, 'category')); ?>,
                datasets: [{
                    label: 'Products',
                    data: <?php echo json_encode(array_column($category_distribution, 'count')); ?>,
                    backgroundColor: '#ff6b35'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        grid: {
                            color: '#2a2a2a'
                        }
                    },
                    x: {
                        grid: {
                            color: '#2a2a2a'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
