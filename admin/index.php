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
        /* CSS reset and fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary: #FF4B2B;
            --primary-dark: #cc3a20;
            --bg-light: #F9FAFB;
            --text-dark: #1F2937;
            --text-gray: #6B7280;
            --border-color: #e5e7eb;
            --card-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            /* Grid Pattern */
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar h1 span {
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .logout-btn {
            background: #fff;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #FFF5F5;
        }

        /* Layout */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Navigation Pills */
        .nav-links {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 0.5rem;
            background: #fff;
            border-radius: 50px;
            border: 1px solid var(--border-color);
            display: inline-flex;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 0.5rem 1.2rem;
            color: var(--text-gray);
            text-decoration: none;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .nav-links a:hover {
            color: var(--text-dark);
            background: #f3f4f6;
        }

        .nav-links a.active {
            background: #000;
            color: #fff;
        }

        /* Section Headings */
        h2.section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Overview Stats (Users, Markets, etc) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.05);
            border-color: #cbd5e1;
        }

        .stat-card .icon {
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            background: #f3f4f6;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #111;
            margin-bottom: 0.2rem;
            letter-spacing: -1px;
        }

        .stat-card p {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card small {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-gray);
            background: #f9fafb;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* REVENUE CARDS (Gradient Style) */
        .revenue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .revenue-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 120px; /* Small size as requested */
            box-shadow: 0 10px 25px rgba(255, 75, 43, 0.2);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Vibrant Orange Gradient */
        .bg-gradient-orange {
            background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%);
        }

        /* Purple/Pink Gradient */
        .bg-gradient-purple {
            background: linear-gradient(135deg, #A855F7 0%, #FF6B35 100%);
        }

        .revenue-card h4 {
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            z-index: 2;
        }

        .revenue-card h2 {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            z-index: 2;
        }

        .revenue-card .chart-container {
            position: absolute;
            top: 0; 
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s ease;
            display: flex;
            align-items: flex-end;
            padding-bottom: 10px;
        }

        /* Hover Reveal Effect */
        .revenue-card:hover .chart-container,
        .revenue-card.auto-hover .chart-container {
            opacity: 1;
        }

        .revenue-card:hover h2, .revenue-card:hover h4,
        .revenue-card.auto-hover h2, .revenue-card.auto-hover h4 {
            opacity: 0.1; /* Dim text instead of hide completely for better feel */
        }
        
        .revenue-card canvas {
            max-height: 80px; /* Small charts */
            width: 100%;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .chart-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            height: 320px; /* Constrain height "small" */
        }

        .chart-card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #111;
        }
        
        .chart-card canvas {
            max-height: 250px;
        }

        /* Tables */
        .table-section {
            background: #fff;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            overflow-x: auto;
        }

        .table-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            text-align: left;
            padding: 1rem;
            background: #f9fafb;
            color: var(--text-gray);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
            font-weight: 500;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f9fafb;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-placed { background: #E0F2FE; color: #0284C7; }
        .badge-packed { background: #FFF7ED; color: #EA580C; }
        .badge-shipped { background: #F3E8FF; color: #9333EA; }
        .badge-delivered { background: #ECFDF5; color: #059669; }
        .badge-cancelled { background: #FEF2F2; color: #DC2626; }

        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 1rem; }
            .stats-grid, .charts-section { grid-template-columns: 1fr; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar">
        <h1>🛒 Market<span>X</span> Admin</h1>
        <div class="user-info">
            <span>👋 <?php echo htmlspecialchars(get_user_name()); ?></span>
            <a href="../logout.php" class="logout-btn">Log Output</a>
        </div>
    </nav>

    <div class="container">
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php" class="active">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="markets.php">Markets</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="analytics.php">Reports</a>
        </div>

        <!-- System Overview -->
        <h2 class="section-title">📊 System Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <h3><?php echo number_format($total_users); ?></h3>
                <p>Total Users</p>
                <small>Cust: <?php echo $total_customers; ?> • Own: <?php echo $total_shop_owners; ?></small>
            </div>
            <div class="stat-card">
                <div class="icon">🏪</div>
                <h3><?php echo number_format($total_markets); ?></h3>
                <p>Active Markets</p>
            </div>
            <div class="stat-card">
                <div class="icon">📦</div>
                <h3><?php echo number_format($total_products); ?></h3>
                <p>Products Listed</p>
            </div>
            <div class="stat-card">
                <div class="icon">🛍️</div>
                <h3><?php echo number_format($total_orders); ?></h3>
                <p>Total Orders</p>
            </div>
        </div>

        <!-- Revenue Stats with Hover/Loop Charts -->
        <h2 class="section-title">💰 Financial Performance</h2>
        <div class="revenue-stats">
            <div class="revenue-card bg-gradient-orange">
                <h4>Total Revenue</h4>
                <h2>₹<?php echo number_format($total_revenue, 2); ?></h2>
                <div class="chart-container">
                    <canvas id="totalRevenueChart"></canvas>
                </div>
            </div>

            <div class="revenue-card bg-gradient-orange">
                <h4>Today's Revenue</h4>
                <h2>₹<?php echo number_format($today_revenue, 2); ?></h2>
                <div class="chart-container">
                    <canvas id="todayRevenueChart"></canvas>
                </div>
            </div>

            <div class="revenue-card bg-gradient-purple">
                <h4>This Month</h4>
                <h2>₹<?php echo number_format($month_revenue, 2); ?></h2>
                <div class="chart-container">
                    <canvas id="monthRevenueChart"></canvas>
                </div>
            </div>

            <div class="revenue-card bg-gradient-purple">
                <h4>Avg Order Value</h4>
                <h2>₹<?php echo number_format($avg_order_value, 2); ?></h2>
                <div class="chart-container">
                    <canvas id="avgOrderChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Charts -->
        <div class="charts-section">
            <div class="chart-card">
                <h3>📈 Monthly Growth</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>📊 Order Status</h3>
                <canvas id="orderStatusChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>🏷️ Categories</h3>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- Data Tables -->
        <div class="table-section">
            <h3>🛍️ Recent Orders</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
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
                        <td><?php echo date('d M, h:i A', strtotime($order['order_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-section">
            <h3>🔥 Top Top Products</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Market</th>
                        <th>Sold</th>
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
        
        <div class="table-section">
            <h3>🏪 Top Markets</h3>
            <table>
                <thead>
                    <tr>
                        <th>Market</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_markets as $market): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($market['market_name']); ?></td>
                        <td><?php echo number_format($market['total_orders']); ?></td>
                        <td>₹<?php echo number_format($market['total_revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Initialization Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- 1. Small Revenue Charts Configuration ---
        const miniChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false } // Hide tooltip for cleaner loop effect
            },
            scales: {
                x: { display: false },
                y: { display: false }
            },
            elements: {
                point: { radius: 0 },
                line: { borderWidth: 2, tension: 0.4 },
                bar: { borderRadius: 4 }
            },
            layout: { padding: 0 }
        };

        // Total Revenue (Line)
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

        // Today Revenue (Line)
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

        // Monthly Revenue (Bar)
        new Chart(document.getElementById('monthRevenueChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($month_weeks); ?>,
                datasets: [{
                    data: <?php echo json_encode($month_revenue_values); ?>,
                    backgroundColor: 'rgba(255, 255, 255, 0.5)',
                    borderColor: 'rgba(255, 255, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: miniChartOptions
        });

        // Avg Order (Line)
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

        // --- 2. Hover Loop Logic ---
        const revenueCards = document.querySelectorAll('.revenue-card');
        let currentIndex = 0;

        function autoHoverLoop() {
            // Remove active state from all
            revenueCards.forEach(card => card.classList.remove('auto-hover'));
            
            // Add to current
            if(revenueCards.length > 0) {
                revenueCards[currentIndex].classList.add('auto-hover');
                // Move to next
                currentIndex = (currentIndex + 1) % revenueCards.length;
            }
        }

        // Start loop
        const loopInterval = setInterval(autoHoverLoop, 2000); // 2 seconds per card

        // Pause on manual hover
        revenueCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                revenueCards.forEach(c => c.classList.remove('auto-hover'));
                // Optional: clearInterval(loopInterval) to stop permanently
            });
        });

        // --- 3. Main Dashboard Charts ---
        Chart.defaults.color = '#6B7280';
        Chart.defaults.borderColor = '#E5E7EB';
        Chart.defaults.font.family = "'Inter', sans-serif";

        // Monthly Growth
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>,
                    borderColor: '#FF4B2B',
                    backgroundColor: 'rgba(255, 75, 43, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Order Status (Doughnut - cleaner than Radar for dashboard)
        new Chart(document.getElementById('orderStatusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($order_status_data, 'order_status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($order_status_data, 'count')); ?>,
                    backgroundColor: [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });

        // Category (Bar)
        new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($category_distribution, 'category')); ?>,
                datasets: [{
                    label: 'Items',
                    data: <?php echo json_encode(array_column($category_distribution, 'count')); ?>,
                    backgroundColor: '#FF4B2B',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } }
                }
            }
        });
    });
    </script>
</body>
</html>
