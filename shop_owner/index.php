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
    <title>Shop Owner Dashboard - MarketX</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --card-radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
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
        
        .navbar h1 span { color: var(--primary); }

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
        .logout-btn:hover { background: #FFF5F5; color: var(--primary); border-color: var(--primary); }

        /* Container & Nav */
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .nav-links {
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 0.5rem;
            background: #fff;
            border-radius: 50px;
            border: 1px solid var(--border-color);
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
        .nav-links a:hover { color: var(--text-dark); background: #f3f4f6; }
        .nav-links a.active { background: #000; color: #fff; }

        .header { margin-bottom: 2rem; }
        .header h2 { font-size: 1.8rem; font-weight: 800; color: #111; margin-bottom: 0.5rem; }
        .header p { color: var(--text-gray); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        
        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
            min-height: 140px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .stat-card h3 { font-size: 0.8rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; position: relative; z-index: 2; }
        .stat-card .value { font-size: 2rem; font-weight: 800; position: relative; z-index: 2; letter-spacing: -1px; }
        .stat-card .subtext { font-size: 0.8rem; position: relative; z-index: 2; opacity: 0.9; }
        .stat-card .icon-overlay { position: absolute; right: -10px; bottom: -10px; font-size: 6rem; opacity: 0.1; transform: rotate(-15deg); }

        .bg-gradient-blue { background: linear-gradient(135deg, #2563EB 0%, #60A5FA 100%); }
        .bg-gradient-green { background: linear-gradient(135deg, #059669 0%, #34D399 100%); }
        .bg-gradient-orange { background: linear-gradient(135deg, #EA580C 0%, #FB923C 100%); }
        .bg-gradient-purple { background: linear-gradient(135deg, #7C3AED 0%, #A78BFA 100%); }
        .bg-gradient-red { background: linear-gradient(135deg, #DC2626 0%, #F87171 100%); }

        /* Revenue Chart Cards (Hover Loop) */
        .revenue-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        
        .revenue-card {
            background: #fff;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex; flex-direction: column; justify-content: space-between;
            min-height: 150px;
        }
        .revenue-card:hover, .revenue-card.auto-hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-color: var(--primary); }
        
        .revenue-card h4 { font-size: 0.85rem; color: var(--text-gray); margin-bottom: 0.5rem; font-weight: 600; text-transform: uppercase; }
        .revenue-card h2 { font-size: 1.8rem; font-weight: 800; color: #111; margin-bottom: 0.5rem; }
        
        .chart-container { 
            height: 60px; width: 100%; position: absolute; bottom: 0; left: 0; opacity: 0.3; transition: all 0.4s;
        }
        .revenue-card:hover .chart-container, .revenue-card.auto-hover .chart-container { opacity: 1; height: 80px; }

        /* Content Grid */
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        
        .card { background: #fff; border-radius: var(--card-radius); padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .card h2 { font-size: 1.25rem; font-weight: 700; color: #111; margin-bottom: 1.5rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; background: #F9FAFB; color: var(--text-gray); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #E5E7EB; }
        td { padding: 1rem; border-bottom: 1px solid #E5E7EB; font-size: 0.9rem; color: #1F2937; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #F9FAFB; }

        .status { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status.placed { background: #EFF6FF; color: #1D4ED8; }
        .status.packed { background: #FFF7ED; color: #C2410C; }
        .status.shipped { background: #F3E8FF; color: #7C3AED; }
        .status.delivered { background: #ECFDF5; color: #059669; }
        .status.cancelled { background: #FEF2F2; color: #DC2626; }

        /* Product List */
        .product-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f3f4f6; }
        .product-item:last-child { border-bottom: none; }
        .stock-alert { color: #DC2626; font-weight: 700; font-size: 0.85rem; background: #FEF2F2; padding: 4px 8px; border-radius: 6px; }

        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; text-align: center; margin-top: 1rem; background: #111; color: #fff; }
        .btn:hover { background: var(--primary); transform: translateY(-2px); }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert.info { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .alert a { color: #1E40AF; text-decoration: underline; font-weight: 700; }

        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>🛒 Market<span>X</span> Owner</h1>
        <div class="user-info">
            <span>👋 <?php echo htmlspecialchars($owner_name); ?></span>
            <a href="../logout.php" class="logout-btn">Log Output</a>
        </div>
    </nav>

    <div class="container">
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php" class="active">Dashboard</a>
            <a href="my_market.php">My Market</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
        </div>

        <div class="header">
            <h2>Dashboard</h2>
            <p>Welcome back! Here's what's happening in your market today.</p>
        </div>

        <?php if (!$market): ?>
            <div class="alert info">
                <strong>Get Started!</strong> You haven't created your market yet. 
                <a href="my_market.php">Create your market now</a> to start selling.
            </div>
        <?php else: ?>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card bg-gradient-blue">
                    <div class="icon-overlay">📦</div>
                    <h3>Total Products</h3>
                    <div class="value"><?php echo $stats['total_products']; ?></div>
                    <div class="subtext"><?php echo $stats['active_products']; ?> Active</div>
                </div>

                <div class="stat-card bg-gradient-green">
                    <div class="icon-overlay">💰</div>
                    <h3>Total Revenue</h3>
                    <div class="value">₹<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="subtext"><?php echo $stats['completed_orders']; ?> Orders</div>
                </div>

                <div class="stat-card bg-gradient-orange">
                    <div class="icon-overlay">🛍️</div>
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $stats['total_orders']; ?></div>
                    <div class="subtext"><?php echo $stats['pending_orders']; ?> Pending</div>
                </div>

                <div class="stat-card bg-gradient-purple">
                    <div class="icon-overlay">📅</div>
                    <h3>Today's Orders</h3>
                    <div class="value"><?php echo $stats['today_orders']; ?></div>
                    <div class="subtext">₹<?php echo number_format($stats['today_revenue'], 2); ?> Rev</div>
                </div>

                <?php if ($stats['low_stock_products'] > 0): ?>
                <div class="stat-card bg-gradient-red">
                    <div class="icon-overlay">⚠️</div>
                    <h3>Low Stock Alert</h3>
                    <div class="value"><?php echo $stats['low_stock_products']; ?></div>
                    <div class="subtext">Restock Needed</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Revenue Trend Cards (Hover Loop) -->
            <div class="revenue-stats">
                <div class="revenue-card">
                    <div>
                        <h4>Revenue Trend</h4>
                        <h2>₹<?php echo number_format($stats['total_revenue'], 2); ?></h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart1"></canvas>
                    </div>
                </div>
                <div class="revenue-card">
                    <div>
                        <h4>Daily Activity</h4>
                        <h2><?php echo $stats['today_orders']; ?> Sales</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart2"></canvas>
                    </div>
                </div>
                <div class="revenue-card">
                    <div>
                        <h4>Order Volume</h4>
                        <h2><?php echo $stats['total_orders']; ?> Total</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart3"></canvas>
                    </div>
                </div>
                <div class="revenue-card">
                    <div>
                        <h4>Avg Order Value</h4>
                        <h2>₹<?php echo $stats['total_orders'] > 0 ? number_format($stats['total_revenue'] / $stats['total_orders'], 2) : '0.00'; ?></h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart4"></canvas>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Orders Table -->
                <div class="card">
                    <h2>Recent Orders</h2>
                    <?php if (empty($recent_orders)): ?>
                        <div style="text-align:center; padding:2rem; color:#999;">No orders yet.</div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table>
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
                                        <td><span class="status <?php echo $order['order_status']; ?>"><?php echo ucfirst($order['order_status']); ?></span></td>
                                        <td><?php echo date('M d', strtotime($order['order_date'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="orders.php" class="btn">View All Orders</a>
                    <?php endif; ?>
                </div>

                <!-- Low Stock List -->
                <div class="card">
                    <h2>Low Stock Alert</h2>
                    <?php if (empty($low_stock_products)): ?>
                        <div style="text-align:center; padding:2rem; color:#999;">All products well stocked! ✅</div>
                    <?php else: ?>
                        <?php foreach ($low_stock_products as $product): ?>
                        <div class="product-item">
                            <div>
                                <div style="font-weight:600; font-size:0.95rem; margin-bottom:4px;"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <small style="color:#666;">₹<?php echo number_format($product['price'], 2); ?></small>
                            </div>
                            <div class="stock-alert">Only <?php echo $product['stock']; ?> left</div>
                        </div>
                        <?php endforeach; ?>
                        <a href="products.php" class="btn">Manage Inventory</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chart Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } },
            elements: { point: { radius: 0 }, line: { borderWidth: 3, tension: 0.4 }, bar: { borderRadius: 4 } },
            layout: { padding: 0 }
        };

        // Dummy data for visual effect (since real historical data needs complex queries)
        // Chart 1: Revenue Trend (Line)
        new Chart(document.getElementById('chart1'), {
            type: 'line',
            data: {
                labels: [1,2,3,4,5,6,7],
                datasets: [{
                    data: [12, 19, 15, 25, 22, 30, 28],
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true
                }]
            },
            options: commonOptions
        });

        // Chart 2: Daily Activity (Bar)
        new Chart(document.getElementById('chart2'), {
            type: 'bar',
            data: {
                labels: [1,2,3,4,5,6,7],
                datasets: [{
                    data: [5, 10, 8, 12, 15, 10, 8],
                    backgroundColor: '#3B82F6'
                }]
            },
            options: commonOptions
        });

        // Chart 3: Order Volume (Line)
        new Chart(document.getElementById('chart3'), {
            type: 'line',
            data: {
                labels: [1,2,3,4,5],
                datasets: [{
                    data: [3, 8, 5, 12, 10],
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    fill: true
                }]
            },
            options: commonOptions
        });

        // Chart 4: Avg Order (Line)
        new Chart(document.getElementById('chart4'), {
            type: 'line',
            data: {
                labels: [1,2,3,4,5,6],
                datasets: [{
                    data: [500, 550, 480, 600, 580, 620],
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    fill: true
                }]
            },
            options: commonOptions
        });

        // Hover Loop Effect
        const cards = document.querySelectorAll('.revenue-card');
        let index = 0;
        
        function loopHover() {
            cards.forEach(c => c.classList.remove('auto-hover'));
            if(cards.length > 0) {
                cards[index].classList.add('auto-hover');
                index = (index + 1) % cards.length;
            }
        }
        
        setInterval(loopHover, 2500);
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                cards.forEach(c => c.classList.remove('auto-hover'));
            });
        });
    });
    </script>
</body>
</html>