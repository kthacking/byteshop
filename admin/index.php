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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar h1 {
            font-size: 1.5rem;
        }

        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }

        .navbar .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            /* max-width: 1400px;
            margin: 0 auto; */
            flex: 1; padding: 30px;
        }

    /* Navigation Links (Top of Container) */
    .nav-links {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 12px;
        flex-wrap: wrap;
    }

    .nav-links a {
        padding: 0.7rem 1.2rem;
        background: white;
        color: #333;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.9rem;
        border: 2px solid transparent;
    }

    .nav-links a:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .nav-links a.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }

        .revenue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .revenue-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .revenue-card h4 {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .revenue-card h2 {
            font-size: 1.8rem;
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .chart-card h3 {
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }

        .table-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .table-section h3 {
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #667eea;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-placed { background: #e3f2fd; color: #1976d2; }
        .badge-packed { background: #fff3e0; color: #f57c00; }
        .badge-shipped { background: #e8f5e9; color: #388e3c; }
        .badge-delivered { background: #c8e6c9; color: #2e7d32; }
        .badge-cancelled { background: #ffebee; color: #c62828; }

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
            </div>

            <div class="revenue-card">
                <h4>Today's Revenue</h4>
                <h2>₹<?php echo number_format($today_revenue, 2); ?></h2>
            </div>

            <div class="revenue-card">
                <h4>This Month</h4>
                <h2>₹<?php echo number_format($month_revenue, 2); ?></h2>
            </div>

            <div class="revenue-card">
                <h4>Average Order Value</h4>
                <h2>₹<?php echo number_format($avg_order_value, 2); ?></h2>
            </div>
        </div>

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
        // Monthly Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
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
                    backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b']
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
                    backgroundColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
    <?php include '../includes/admin_footer.php'; ?>
