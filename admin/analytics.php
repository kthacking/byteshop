<?php
/**
 * ByteShop - Admin Analytics & Reports
 * 
 * Features:
 * - Filter by date, market, category
 * - View analytics dashboard
 * - Download Excel reports
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Require admin access
require_admin();

// Get filter parameters
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_market = isset($_GET['market_id']) ? $_GET['market_id'] : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Fetch all markets for filter dropdown
$markets_query = "SELECT market_id, market_name FROM markets WHERE status = 'active' ORDER BY market_name";
$markets_stmt = $pdo->query($markets_query);
$all_markets = $markets_stmt->fetchAll();

// Fetch all product categories for filter
$categories_query = "SELECT DISTINCT category FROM products WHERE status = 'active' ORDER BY category";
$categories_stmt = $pdo->query($categories_query);
$all_categories = $categories_stmt->fetchAll();

// Build WHERE clause for filters
$where_conditions = ["o.order_date BETWEEN :start_date AND :end_date"];
$params = [
    'start_date' => $filter_start_date . ' 00:00:00',
    'end_date' => $filter_end_date . ' 23:59:59'
];

if (!empty($filter_market)) {
    $where_conditions[] = "oi.market_id = :market_id";
    $params['market_id'] = $filter_market;
}

if (!empty($filter_category)) {
    $where_conditions[] = "p.category = :category";
    $params['category'] = $filter_category;
}

$where_clause = implode(' AND ', $where_conditions);

// 1. TOTAL STATISTICS
$stats_query = "
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        COUNT(DISTINCT o.customer_id) as total_customers,
        SUM(o.total_amount) as total_revenue,
        AVG(o.total_amount) as avg_order_value
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE $where_clause
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// 2. MARKET-WISE SALES
$market_sales_query = "
    SELECT 
        m.market_id,
        m.market_name,
        m.location,
        COUNT(DISTINCT oi.order_id) as total_orders,
        SUM(oi.quantity) as total_items_sold,
        SUM(oi.subtotal) as total_revenue
    FROM markets m
    LEFT JOIN order_items oi ON m.market_id = oi.market_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE $where_clause
    GROUP BY m.market_id
    ORDER BY total_revenue DESC
";
$market_sales_stmt = $pdo->prepare($market_sales_query);
$market_sales_stmt->execute($params);
$market_sales = $market_sales_stmt->fetchAll();

// 3. PRODUCT-WISE SALES
$product_sales_query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.category,
        m.market_name,
        SUM(oi.quantity) as total_quantity_sold,
        SUM(oi.subtotal) as total_revenue,
        COUNT(DISTINCT oi.order_id) as order_count
    FROM products p
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN markets m ON p.market_id = m.market_id
    WHERE $where_clause
    GROUP BY p.product_id
    ORDER BY total_revenue DESC
    LIMIT 20
";
$product_sales_stmt = $pdo->prepare($product_sales_query);
$product_sales_stmt->execute($params);
$product_sales = $product_sales_stmt->fetchAll();

// 4. CATEGORY-WISE SALES
$category_sales_query = "
    SELECT 
        p.category,
        COUNT(DISTINCT p.product_id) as total_products,
        SUM(oi.quantity) as total_quantity_sold,
        SUM(oi.subtotal) as total_revenue
    FROM products p
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE $where_clause
    GROUP BY p.category
    ORDER BY total_revenue DESC
";
$category_sales_stmt = $pdo->prepare($category_sales_query);
$category_sales_stmt->execute($params);
$category_sales = $category_sales_stmt->fetchAll();

// 5. RECENT ORDERS
$recent_orders_query = "
    SELECT 
        o.order_id,
        u.name as customer_name,
        o.total_amount,
        o.order_status,
        o.order_date
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE $where_clause
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
    LIMIT 10
";
$recent_orders_stmt = $pdo->prepare($recent_orders_query);
$recent_orders_stmt->execute($params);
$recent_orders = $recent_orders_stmt->fetchAll();

// 6. TOP CUSTOMERS
$top_customers_query = "
    SELECT 
        u.user_id,
        u.name,
        u.email,
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(o.total_amount) as total_spent
    FROM users u
    JOIN orders o ON u.user_id = o.customer_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE u.role = 'customer' AND $where_clause
    GROUP BY u.user_id
    ORDER BY total_spent DESC
    LIMIT 10
";
$top_customers_stmt = $pdo->prepare($top_customers_query);
$top_customers_stmt->execute($params);
$top_customers = $top_customers_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - ByteShop Admin</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        
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
        .stat-card h3 { font-size: 0.85rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; position: relative; z-index: 2; }
        .stat-card .value { font-size: 2.2rem; font-weight: 800; position: relative; z-index: 2; letter-spacing: -1px; }
        .stat-card .icon-overlay { position: absolute; right: -10px; bottom: -10px; font-size: 8rem; opacity: 0.1; transform: rotate(-15deg); }

        .bg-gradient-orange { background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); }
        .bg-gradient-blue { background: linear-gradient(135deg, #2563EB 0%, #60A5FA 100%); }
        .bg-gradient-green { background: linear-gradient(135deg, #059669 0%, #34D399 100%); }
        .bg-gradient-purple { background: linear-gradient(135deg, #7C3AED 0%, #A78BFA 100%); }

        /* Cards & Section */
        .card { background: #fff; border-radius: var(--card-radius); padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .card h2 { font-size: 1.25rem; font-weight: 700; color: #111; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
        
        /* Filters */
        .filters form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .filters .form-group { flex: 1; min-width: 180px; }
        .filters label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); }
        .filters input, .filters select { width: 100%; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; background: #f9fafb; transition: all 0.2s; }
        .filters input:focus, .filters select:focus { border-color: var(--primary); outline: none; background: #fff; box-shadow: 0 0 0 3px rgba(255, 75, 43, 0.1); }
        
        .btn { padding: 0.6rem 1.5rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #000; color: #fff; }
        .btn-primary:hover { background: var(--primary); transform: translateY(-1px); }
        .btn-secondary { background: #f3f4f6; color: #111; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #e5e7eb; }

        /* Download Buttons */
        .download-buttons { display: flex; flex-wrap: wrap; gap: 1rem; }
        .btn-download { padding: 0.8rem 1.2rem; border-radius: 12px; font-weight: 600; color: #fff; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        
        .dl-customers { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); }
        .dl-market { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .dl-product { background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%); }
        .dl-history { background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; background: #F9FAFB; color: var(--text-gray); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #E5E7EB; }
        td { padding: 1rem; border-bottom: 1px solid #E5E7EB; font-size: 0.9rem; color: #1F2937; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #F9FAFB; }
        
        .no-data { text-align: center; padding: 3rem; color: var(--text-gray); font-style: italic; }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge.placed { background: #DBEAFE; color: #1E40AF; }
        .badge.packed { background: #FFEDD5; color: #9A3412; }
        .badge.shipped { background: #F3E8FF; color: #6B21A8; }
        .badge.delivered { background: #ECFDF5; color: #065F46; }
        .badge.cancelled { background: #FEF2F2; color: #991B1B; }

        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filters form { flex-direction: column; }
            .filters .form-group { width: 100%; }
        }
    </style>
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
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="markets.php">Markets</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="analytics.php" class="active">Reports</a>
        </div>

        <div class="header">
            <h2>Analytics & Reports</h2>
            <p>Comprehensive overview of system performance, sales, and activity.</p>
        </div>

        <!-- Filter Section -->
        <div class="card filters">
            <h2>🔍 Filter Data</h2>
            <form method="GET" action="">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $filter_start_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $filter_end_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Market</label>
                    <select name="market_id">
                        <option value="">All Markets</option>
                        <?php foreach($all_markets as $market): ?>
                            <option value="<?php echo $market['market_id']; ?>" 
                                <?php echo ($filter_market == $market['market_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($market['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach($all_categories as $cat): ?>
                            <option value="<?php echo $cat['category']; ?>" 
                                <?php echo ($filter_category == $cat['category']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 0;">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="analytics.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Reset</a>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card bg-gradient-orange">
                <div class="icon-overlay">📦</div>
                <h3>Total Orders</h3>
                <div class="value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card bg-gradient-blue">
                <div class="icon-overlay">👥</div>
                <h3>Total Customers</h3>
                <div class="value"><?php echo number_format($stats['total_customers'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card bg-gradient-green">
                <div class="icon-overlay">💰</div>
                <h3>Total Revenue</h3>
                <div class="value">₹<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
            
            <div class="stat-card bg-gradient-purple">
                <div class="icon-overlay">📈</div>
                <h3>Avg Order Value</h3>
                <div class="value">₹<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- Download Section -->
        <div class="card">
            <h2>📥 Download Excel Reports</h2>
            <div class="download-buttons">
                <a href="download_report.php?type=customers&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download dl-customers">
                    📋 Customer List
                </a>
                
                <a href="download_report.php?type=market_sales&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download dl-market">
                    🏪 Market-wise Sales
                </a>
                
                <a href="download_report.php?type=product_sales&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download dl-product">
                    📦 Product-wise Sales
                </a>
                
                <a href="download_report.php?type=order_history&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download dl-history">
                    📜 Order History
                </a>
            </div>
        </div>

        <!-- Market Sales -->
        <div class="card">
            <h2>🏪 Market Performance</h2>
            <?php if(count($market_sales) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Market Name</th>
                                <th>Location</th>
                                <th>Total Orders</th>
                                <th>Items Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($market_sales as $market): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($market['market_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($market['location']); ?></td>
                                    <td><?php echo number_format($market['total_orders'] ?? 0); ?></td>
                                    <td><?php echo number_format($market['total_items_sold'] ?? 0); ?></td>
                                    <td style="color:var(--primary); font-weight:700;">₹<?php echo number_format($market['total_revenue'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>

        <!-- Product Sales -->
        <div class="card">
            <h2>📦 Top 20 Products</h2>
            <?php if(count($product_sales) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Market</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($product_sales as $product): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                    <td><span class="badge" style="background:#E0F2FE; color:#0284C7;"><?php echo htmlspecialchars($product['category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($product['market_name']); ?></td>
                                    <td><?php echo number_format($product['total_quantity_sold'] ?? 0); ?></td>
                                    <td style="color:var(--primary); font-weight:700;">₹<?php echo number_format($product['total_revenue'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>
        
        <!-- Category Sales -->
         <div class="card">
            <h2>📊 Category Sales</h2>
            <?php if(count($category_sales) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Products</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($category_sales as $category): ?>
                                <tr>
                                    <td><span class="badge" style="background:#E0F2FE; color:#0284C7;"><?php echo htmlspecialchars($category['category']); ?></span></td>
                                    <td><?php echo number_format($category['total_products'] ?? 0); ?></td>
                                    <td><?php echo number_format($category['total_quantity_sold'] ?? 0); ?></td>
                                    <td style="color:var(--primary); font-weight:700;">₹<?php echo number_format($category['total_revenue'] ?? 0, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <h2>🕒 Recent Orders</h2>
             <?php if(count($recent_orders) > 0): ?>
                <div style="overflow-x:auto;">
                     <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="badge <?php echo strtolower($order['order_status']); ?>"><?php echo strtoupper($order['order_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
             <?php else: ?>
                  <div class="no-data">No recent orders found</div>
             <?php endif; ?>
        </div>

         <!-- Top Customers -->
        <div class="card">
            <h2>🏆 Top Customers</h2>
             <?php if(count($top_customers) > 0): ?>
                <div style="overflow-x:auto;">
                     <table>
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Total Orders</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_customers as $customer): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo number_format($customer['total_orders']); ?></td>
                                    <td style="color:var(--primary); font-weight:700;">₹<?php echo number_format($customer['total_spent'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
             <?php else: ?>
                  <div class="no-data">No customer data found</div>
             <?php endif; ?>
        </div>

    </div>
</body>
</html>