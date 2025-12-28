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
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
        }

        /* Container */
        .container {
            max-width: 100%;
            margin: 1.8rem auto;
            padding: 0 1.8rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 1.8rem;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 1.8rem;
            border: 1px solid rgba(255, 107, 53, 0.15);
        }

        .welcome-section h1 {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.45rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .welcome-section p {
            color: #a0a0a0;
            font-size: 0.95rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(225px, 1fr));
            gap: 1.35rem;
            margin-bottom: 1.8rem;
        }

        .stat-card {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 1.35rem;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border-left: 3.6px solid #ff6b35;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 3.6px solid #ff6b35;
            transition: all 0.3s ease;
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
            background: linear-gradient(90deg, transparent, #ff6b35, transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(255, 107, 53, 0.3);
            border-color: rgba(255, 107, 53, 0.3);
            border-left-color: #ff6b35;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card h3 {
            color: #a0a0a0;
            font-size: 0.81rem;
            margin-bottom: 0.45rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.45rem;
        }

        .stat-card .subtext {
            color: #888;
            font-size: 0.77rem;
        }

        .stat-card.revenue {
            border-left-color: #00d4aa;
        }

        .stat-card.revenue .value {
            background: linear-gradient(135deg, #00d4aa 0%, #00b894 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.revenue::before {
            background: linear-gradient(90deg, transparent, #00d4aa, transparent);
        }

        .stat-card.warning {
            border-left-color: #f7931e;
        }

        .stat-card.warning .value {
            background: linear-gradient(135deg, #f7931e 0%, #ffa726 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, transparent, #f7931e, transparent);
        }

        .stat-card.danger {
            border-left-color: #ff4757;
        }

        .stat-card.danger .value {
            background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.danger::before {
            background: linear-gradient(90deg, transparent, #ff4757, transparent);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.8rem;
        }

        .card {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 1.35rem;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card h2 {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.35rem;
            padding-bottom: 0.45rem;
            border-bottom: 2px solid rgba(255, 107, 53, 0.2);
            font-size: 1.35rem;
            font-weight: 700;
        }

        /* Table */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 0.68rem;
            background: rgba(255, 255, 255, 0.05);
            color: #a0a0a0;
            font-weight: 600;
            font-size: 0.81rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .table td {
            padding: 0.68rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.85rem;
            color: #e0e0e0;
        }

        .table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Status Badge */
        .status {
            padding: 0.23rem 0.68rem;
            border-radius: 18px;
            font-size: 0.77rem;
            font-weight: 600;
            border: 1px solid;
            letter-spacing: 0.3px;
        }

        .status.placed {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .status.packed {
            background: rgba(247, 147, 30, 0.15);
            color: #f7931e;
            border-color: rgba(247, 147, 30, 0.3);
        }

        .status.shipped {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            border-color: rgba(102, 126, 234, 0.3);
        }

        .status.delivered {
            background: rgba(0, 212, 170, 0.15);
            color: #00d4aa;
            border-color: rgba(0, 212, 170, 0.3);
        }

        /* Alert Box */
        .alert {
            padding: 0.9rem;
            border-radius: 12px;
            margin-bottom: 1.35rem;
            border: 1px solid;
            font-size: 0.9rem;
        }

        .alert.warning {
            background: rgba(247, 147, 30, 0.15);
            border-color: rgba(247, 147, 30, 0.3);
            color: #f7931e;
        }

        .alert.info {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }

        .alert strong {
            color: #ffffff;
        }

        .alert a {
            color: #ffffff;
            font-weight: 600;
            text-decoration: underline;
        }

        /* Product List */
        .product-item {
            display: flex;
            justify-content: space-between;
            padding: 0.68rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .product-name {
            font-weight: 600;
            color: #ffffff;
            font-size: 0.9rem;
        }

        .product-item small {
            color: #a0a0a0;
            font-size: 0.77rem;
        }

        .stock-low {
            background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn {
            padding: 0.68rem 1.35rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(255, 107, 53, 0.3);
            border: 1px solid rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.5);
        }

        .btn-group {
            display: flex;
            gap: 0.9rem;
            margin-top: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 2.7rem;
            color: #777;
            font-size: 0.95rem;
        }

        .empty-state img {
            width: 90px;
            opacity: 0.3;
            margin-bottom: 0.9rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 0 1.35rem;
                margin: 1.35rem auto;
            }

            .welcome-section h1 {
                font-size: 1.5rem;
            }

            .stat-card .value {
                font-size: 1.5rem;
            }

            .card h2 {
                font-size: 1.15rem;
            }
        }
    </style>
</head>
<body>
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
                <a href="my_market.php">Create your market now</a> to start selling products.
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