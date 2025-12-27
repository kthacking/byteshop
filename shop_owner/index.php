<?php
/**
 * ByteShop - Shop Owner Dashboard
 * Market overview, product count, order stats
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require shop owner authentication
require_shop_owner();

$owner_id = get_user_id();
$owner_name = get_user_name();

// Get owner's market information
$stmt = $pdo->prepare("
    SELECT * FROM markets 
    WHERE owner_id = ? 
    LIMIT 1
");
$stmt->execute([$owner_id]);
$market = $stmt->fetch();

// Initialize stats
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'low_stock_products' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'total_revenue' => 0,
    'today_orders' => 0,
    'today_revenue' => 0
];

// Only fetch stats if market exists
if ($market) {
    $market_id = $market['market_id'];
    
    // Product Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN stock < 10 AND stock > 0 THEN 1 ELSE 0 END) as low_stock
        FROM products 
        WHERE market_id = ?
    ");
    $stmt->execute([$market_id]);
    $product_stats = $stmt->fetch();
    
    $stats['total_products'] = $product_stats['total'];
    $stats['active_products'] = $product_stats['active'];
    $stats['low_stock_products'] = $product_stats['low_stock'];
    
    // Order Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(CASE WHEN o.order_status IN ('placed', 'packed') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN o.order_status = 'delivered' THEN 1 ELSE 0 END) as completed,
            COALESCE(SUM(oi.subtotal), 0) as revenue
        FROM orders o
        INNER JOIN order_items oi ON o.order_id = oi.order_id
        WHERE oi.market_id = ?
    ");
    $stmt->execute([$market_id]);
    $order_stats = $stmt->fetch();
    
    $stats['total_orders'] = $order_stats['total_orders'];
    $stats['pending_orders'] = $order_stats['pending'];
    $stats['completed_orders'] = $order_stats['completed'];
    $stats['total_revenue'] = $order_stats['revenue'];
    
    // Today's Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as today_orders,
            COALESCE(SUM(oi.subtotal), 0) as today_revenue
        FROM orders o
        INNER JOIN order_items oi ON o.order_id = oi.order_id
        WHERE oi.market_id = ? 
        AND DATE(o.order_date) = CURDATE()
    ");
    $stmt->execute([$market_id]);
    $today_stats = $stmt->fetch();
    
    $stats['today_orders'] = $today_stats['today_orders'];
    $stats['today_revenue'] = $today_stats['today_revenue'];
    
    // Recent Orders (Last 5)
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            o.order_id,
            o.order_date,
            o.order_status,
            o.total_amount,
            u.name as customer_name,
            COUNT(oi.order_item_id) as items_count,
            SUM(oi.subtotal) as market_total
        FROM orders o
        INNER JOIN order_items oi ON o.order_id = oi.order_id
        INNER JOIN users u ON o.customer_id = u.user_id
        WHERE oi.market_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([$market_id]);
    $recent_orders = $stmt->fetchAll();
    
    // Low Stock Products
    $stmt = $pdo->prepare("
        SELECT product_id, product_name, stock, price 
        FROM products 
        WHERE market_id = ? AND stock < 10 AND stock > 0
        ORDER BY stock ASC
        LIMIT 5
    ");
    $stmt->execute([$market_id]);
    $low_stock_products = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Owner Dashboard - ByteShop</title>
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

        /* Header
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .nav a:hover {
            opacity: 0.8;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        } */

        /* Container */
        .container {
            max-width: 100%;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        /* Stats Grid */
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-card .subtext {
            color: #999;
            font-size: 0.85rem;
        }

        .stat-card.revenue {
            border-left-color: #10b981;
        }

        .stat-card.revenue .value {
            color: #10b981;
        }

        .stat-card.warning {
            border-left-color: #f59e0b;
        }

        .stat-card.warning .value {
            color: #f59e0b;
        }

        .stat-card.danger {
            border-left-color: #ef4444;
        }

        .stat-card.danger .value {
            color: #ef4444;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Table */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 0.75rem;
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        /* Status Badge */
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status.placed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status.packed {
            background: #fef3c7;
            color: #92400e;
        }

        .status.shipped {
            background: #e0e7ff;
            color: #4338ca;
        }

        .status.delivered {
            background: #d1fae5;
            color: #065f46;
        }

        /* Alert Box */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert.warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert.info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }

        /* Product List */
        .product-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item:hover {
            background: #f8f9fa;
        }

        .product-name {
            font-weight: 500;
        }

        .stock-low {
            color: #ef4444;
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state img {
            width: 100px;
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <!-- <div class="header">
        <div class="header-content">
            <div class="logo">ByteShop | Shop Owner</div>
            <nav class="nav">
                <a href="index.php">Dashboard</a>
                <a href="my_market.php">My Market</a>
                <a href="products.php">Products</a>
                <a href="orders.php">Orders</a>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($owner_name); ?></span>
                    <a href="../logout.php">Logout</a>
                </div>
            </nav>
        </div>
    </div> -->
    <?php include '../includes/shop_owner_header.php'; ?>

    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($owner_name); ?>!</h1>
            <p>Here's what's happening with your market today</p>
        </div>

        <?php if (!$market): ?>
            <!-- No Market Alert -->
            <div class="alert info">
                <strong>Get Started!</strong> You haven't created your market yet. 
                <a href="my_market.php" style="color: #1e40af; font-weight: 600;">Create your market now</a> to start selling products.
            </div>
        <?php else: ?>
            
            <!-- Market Info -->
            <div class="alert info">
                <strong><?php echo htmlspecialchars($market['market_name']); ?></strong> - 
                <?php echo htmlspecialchars($market['location']); ?> | 
                Rating: <?php echo $market['rating']; ?>⭐
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Products</h3>
                    <div class="value"><?php echo $stats['total_products']; ?></div>
                    <div class="subtext"><?php echo $stats['active_products']; ?> active</div>
                </div>

                <div class="stat-card revenue">
                    <h3>Total Revenue</h3>
                    <div class="value">₹<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="subtext">From <?php echo $stats['completed_orders']; ?> completed orders</div>
                </div>

                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $stats['total_orders']; ?></div>
                    <div class="subtext"><?php echo $stats['pending_orders']; ?> pending</div>
                </div>

                <div class="stat-card warning">
                    <h3>Today's Orders</h3>
                    <div class="value"><?php echo $stats['today_orders']; ?></div>
                    <div class="subtext">₹<?php echo number_format($stats['today_revenue'], 2); ?> revenue</div>
                </div>

                <?php if ($stats['low_stock_products'] > 0): ?>
                <div class="stat-card danger">
                    <h3>Low Stock Alert</h3>
                    <div class="value"><?php echo $stats['low_stock_products']; ?></div>
                    <div class="subtext">Products need restocking</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Orders -->
                <div class="card">
                    <h2>Recent Orders</h2>
                    <?php if (empty($recent_orders)): ?>
                        <div class="empty-state">
                            <p>No orders yet</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo $order['items_count']; ?></td>
                                    <td>₹<?php echo number_format($order['market_total'], 2); ?></td>
                                    <td>
                                        <span class="status <?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="btn-group">
                            <a href="orders.php" class="btn btn-primary">View All Orders</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Low Stock Products -->
                <div class="card">
                    <h2>Low Stock Alert</h2>
                    <?php if (empty($low_stock_products)): ?>
                        <div class="empty-state">
                            <p>All products are well stocked!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($low_stock_products as $product): ?>
                        <div class="product-item">
                            <div>
                                <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <small>₹<?php echo number_format($product['price'], 2); ?></small>
                            </div>
                            <div class="stock-low">
                                <?php echo $product['stock']; ?> left
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="btn-group">
                            <a href="products.php" class="btn btn-primary">Manage Products</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>