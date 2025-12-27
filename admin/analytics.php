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

        .container {
            flex: 1; 
            padding: 27px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 27px;
            box-shadow: 0 8px 24px rgba(255, 107, 53, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            font-size: 25px;
            margin-bottom: 9px;
            font-weight: 700;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .user-info {
            font-size: 13px;
            opacity: 0.95;
        }

        /* Navigation Links */
        .nav-links {
            display: flex;
            gap: 0.45rem;
            margin-bottom: 1.8rem;
            padding: 0.9rem;
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            flex-wrap: wrap;
            border: 1px solid rgba(255, 107, 53, 0.1);
        }

        .nav-links a {
            padding: 0.63rem 1.08rem;
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            text-decoration: none;
            border-radius: 9px;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.81rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-links a:hover {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(255, 107, 53, 0.4);
            border-color: transparent;
        }

        .nav-links a.active {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 4px 14px rgba(255, 107, 53, 0.3);
        }

        /* Filter Section */
        .filter-section {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 23px;
            border-radius: 14px;
            margin-bottom: 27px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filter-section h2 {
            margin-bottom: 18px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 18px;
            font-weight: 700;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 12.6px;
            color: #b0b0b0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 9px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 9px;
            font-size: 12.6px;
            color: #e0e0e0;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff6b35;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-group select option {
            background: #1a1a1a;
            color: #e0e0e0;
        }

        .filter-actions {
            display: flex;
            gap: 9px;
        }

        .btn {
            padding: 9px 18px;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12.6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.5);
        }

        .btn-secondary {
            background: rgba(108, 117, 125, 0.2);
            color: #a0a0a0;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(108, 117, 125, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(108, 117, 125, 0.3);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(225px, 1fr));
            gap: 18px;
            margin-bottom: 27px;
        }

        .stat-card {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 23px;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border-left: 3.6px solid;
            border-color: #ff6b35;
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
            font-size: 12.6px;
            color: #a0a0a0;
            margin-bottom: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 29px;
            font-weight: 700;
            color: #ffffff;
        }

        .stat-card .currency {
            background: linear-gradient(135deg, #00d4aa 0%, #00b894 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Download Section */
        .download-section {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 23px;
            border-radius: 14px;
            margin-bottom: 27px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .download-section h2 {
            margin-bottom: 18px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 18px;
            font-weight: 700;
        }

        .download-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .btn-download {
            background: linear-gradient(135deg, #00d4aa 0%, #00b894 100%);
            color: white;
            padding: 11px 22px;
            text-decoration: none;
            border-radius: 9px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            font-size: 12.6px;
            box-shadow: 0 4px 14px rgba(0, 212, 170, 0.3);
            border: 1px solid rgba(0, 212, 170, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 212, 170, 0.5);
        }

        /* Data Tables */
        .data-section {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 23px;
            border-radius: 14px;
            margin-bottom: 27px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .data-section h2 {
            margin-bottom: 18px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 18px;
            font-weight: 700;
            border-bottom: 2px solid rgba(255, 107, 53, 0.3);
            padding-bottom: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 11px;
            text-align: left;
            font-weight: 600;
            color: #b0b0b0;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            font-size: 12.6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 11px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 12.6px;
            color: #e0e0e0;
        }

        table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .badge {
            padding: 5px 9px;
            border-radius: 18px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid;
            letter-spacing: 0.3px;
        }

        .badge-placed { 
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.3);
        }
        .badge-packed { 
            background: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
            border-color: rgba(23, 162, 184, 0.3);
        }
        .badge-shipped { 
            background: rgba(0, 123, 255, 0.15);
            color: #007bff;
            border-color: rgba(0, 123, 255, 0.3);
        }
        .badge-delivered { 
            background: rgba(0, 212, 170, 0.15);
            color: #00d4aa;
            border-color: rgba(0, 212, 170, 0.3);
        }
        .badge-cancelled { 
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.3);
        }

        .no-data {
            text-align: center;
            padding: 36px;
            color: #777;
            font-size: 13.5px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 11px;
            }

            .container {
                padding: 18px;
            }

            .header h1 {
                font-size: 20px;
            }

            .stat-card .value {
                font-size: 24px;
            }
        }
</style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Analytics & Reports</h1>
            <div class="header-info">
                <div class="user-info">
                    Welcome, <strong><?php echo get_user_name(); ?></strong> (Admin)
                </div>
                <div class="nav-links">
                  <a href="index.php">Dashboard</a>
                    <a href="users.php">Users</a>
                    <a href="markets.php">Markets</a>
                    <a href="products.php">Products</a>
                    <a href="orders.php">Orders</a>
                    <a href="analytics.php"  class="active">Analytics & Reports</a>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h2>🔍 Filter Data</h2>
            <form method="GET" action="" class="filter-form">
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
                
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                        <a href="analytics.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="value"><?php echo number_format($stats['total_customers'] ?? 0); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value currency">₹<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Avg Order Value</h3>
                <div class="value currency">₹<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- Download Section -->
        <div class="download-section">
            <h2>📥 Download Excel Reports</h2>
            <div class="download-buttons">
                <a href="download_report.php?type=customers&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download">
                    📋 Download Customer List
                </a>
                
                <a href="download_report.php?type=market_sales&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download">
                    🏪 Download Market-wise Sales
                </a>
                
                <a href="download_report.php?type=product_sales&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download">
                    📦 Download Product-wise Sales
                </a>
                
                <a href="download_report.php?type=order_history&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>&market_id=<?php echo $filter_market; ?>&category=<?php echo $filter_category; ?>" class="btn-download">
                    📜 Download Order History
                </a>
            </div>
        </div>

        <!-- Market-wise Sales -->
        <div class="data-section">
            <h2>🏪 Market-wise Sales Performance</h2>
            <?php if(count($market_sales) > 0): ?>
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
                                <td><?php echo htmlspecialchars($market['market_name']); ?></td>
                                <td><?php echo htmlspecialchars($market['location']); ?></td>
                                <td><?php echo number_format($market['total_orders'] ?? 0); ?></td>
                                <td><?php echo number_format($market['total_items_sold'] ?? 0); ?></td>
                                <td><strong>₹<?php echo number_format($market['total_revenue'] ?? 0, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>

        <!-- Product-wise Sales -->
        <div class="data-section">
            <h2>📦 Top 20 Products by Sales</h2>
            <?php if(count($product_sales) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Market</th>
                            <th>Quantity Sold</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($product_sales as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td><?php echo htmlspecialchars($product['market_name']); ?></td>
                                <td><?php echo number_format($product['total_quantity_sold'] ?? 0); ?></td>
                                <td><?php echo number_format($product['order_count'] ?? 0); ?></td>
                                <td><strong>₹<?php echo number_format($product['total_revenue'] ?? 0, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>

        <!-- Category-wise Sales -->
        <div class="data-section">
            <h2>📊 Category-wise Sales</h2>
            <?php if(count($category_sales) > 0): ?>
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
                                <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                                <td><?php echo number_format($category['total_products'] ?? 0); ?></td>
                                <td><?php echo number_format($category['total_quantity_sold'] ?? 0); ?></td>
                                <td><strong>₹<?php echo number_format($category['total_revenue'] ?? 0, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>

        <!-- Top Customers -->
        <div class="data-section">
            <h2>⭐ Top 10 Customers</h2>
            <?php if(count($top_customers) > 0): ?>
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
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo number_format($customer['total_orders']); ?></td>
                                <td><strong>₹<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No data available for selected filters</div>
            <?php endif; ?>
        </div>

        <!-- Recent Orders -->
        <div class="data-section">
            <h2>🕒 Recent Orders</h2>
            <?php if(count($recent_orders) > 0): ?>
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
                                <td>
                                    <span class="badge badge-<?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No orders found for selected filters</div>
            <?php endif; ?>
        </div>

    </div>
    <!-- At the end of body, before closing </body> tag -->
<script src="/byteshop/assets/js/main.js"></script>

</body>
</html>