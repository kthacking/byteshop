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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .container {
            /* max-width: 1400px;
            margin: 0 auto; */
            flex: 1; padding: 30px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .user-info {
            font-size: 14px;
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
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filter-section h2 {
            margin-bottom: 20px;
            color: #667eea;
            font-size: 20px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #555;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #777;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .stat-card .currency {
            color: #28a745;
        }

        /* Download Section */
        .download-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .download-section h2 {
            margin-bottom: 20px;
            color: #667eea;
            font-size: 20px;
        }

        .download-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-download {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-download:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Data Tables */
        .data-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .data-section h2 {
            margin-bottom: 20px;
            color: #667eea;
            font-size: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-placed { background: #ffc107; color: #000; }
        .badge-packed { background: #17a2b8; color: white; }
        .badge-shipped { background: #007bff; color: white; }
        .badge-delivered { background: #28a745; color: white; }
        .badge-cancelled { background: #dc3545; color: white; }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
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