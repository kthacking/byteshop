<?php
/**
 * ByteShop - Admin Products Management
 * View and manage all products across all markets
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Require admin access
require_admin();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $product_id = clean_input($_POST['product_id']);
                $new_status = clean_input($_POST['status']);
                
                $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE product_id = ?");
                $stmt->execute([$new_status, $product_id]);
                
                $_SESSION['success'] = "Product status updated successfully!";
                header('Location: products.php');
                exit;
                break;
                
            case 'delete_product':
                $product_id = clean_input($_POST['product_id']);
                
                $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                $_SESSION['success'] = "Product deleted successfully!";
                header('Location: products.php');
                exit;
                break;
                
            case 'update_stock':
                $product_id = clean_input($_POST['product_id']);
                $new_stock = clean_input($_POST['stock']);
                
                $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
                $stmt->execute([$new_stock, $product_id]);
                
                $_SESSION['success'] = "Stock updated successfully!";
                header('Location: products.php');
                exit;
                break;
        }
    }
}

// Get filter parameters
$market_filter = isset($_GET['market']) ? $_GET['market'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get unique markets and categories for filters
$markets = $pdo->query("SELECT market_id, market_name FROM markets ORDER BY market_name")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Build query
$query = "SELECT p.*, m.market_name, m.location
          FROM products p
          LEFT JOIN markets m ON p.market_id = m.market_id
          WHERE 1=1";
$params = [];

if ($market_filter) {
    $query .= " AND p.market_id = ?";
    $params[] = $market_filter;
}

if ($category_filter) {
    $query .= " AND p.category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($stock_filter === 'out') {
    $query .= " AND p.stock = 0";
} elseif ($stock_filter === 'low') {
    $query .= " AND p.stock > 0 AND p.stock <= 10";
} elseif ($stock_filter === 'available') {
    $query .= " AND p.stock > 10";
}

if ($search) {
    $query .= " AND (p.product_name LIKE ? OR m.market_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
    SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock,
    ROUND(AVG(rating), 1) as avg_rating
FROM products";
$stats = $pdo->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - ByteShop Admin</title>
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

        .logout-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #FFF5F5;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Nav Pills */
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

        .nav-links a:hover {
            color: var(--text-dark);
            background: #f3f4f6;
        }

        .nav-links a.active {
            background: #000;
            color: #fff;
        }

        .header { margin-bottom: 2rem; }
        .header h2 { font-size: 1.5rem; font-weight: 800; color: #111; margin-bottom: 0.5rem; }
        .header p { color: var(--text-gray); font-size: 0.95rem; }

        /* Stats Grid - Vibrant Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 120px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .bg-orange { background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); }
        .bg-purple { background: linear-gradient(135deg, #A855F7 0%, #FF6B35 100%); }
        .bg-blue { background: linear-gradient(135deg, #3B82F6 0%, #2DD4BF 100%); }
        .bg-red { background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%); }
        .bg-yellow { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); }

        .stat-card h3 {
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            z-index: 2;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -1px;
            z-index: 2;
        }

        .stat-card .icon-overlay {
            position: absolute;
            right: -20px;
            bottom: -20px;
            font-size: 8rem;
            opacity: 0.1;
            transform: rotate(-15deg);
        }

        /* Filters */
        .filters {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .filters form {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group { flex: 1; min-width: 150px; }

        .filters label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .filters input, .filters select {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            background: #f9fafb;
            color: var(--text-dark);
            transition: all 0.2s;
        }

        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 75, 43, 0.1);
        }

        .filters button {
            padding: 0.6rem 1.5rem;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            height: 42px;
        }

        .filters button:hover {
            background: var(--primary);
            transform: translateY(-1px);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: #fff;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: #d1d5db;
        }

        .product-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #f3f4f6;
            border-bottom: 1px solid #f3f4f6;
        }

        .product-content {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #111;
            margin-bottom: 0.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-market {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.8rem;
        }

        .badges-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge.category { background: #E0F2FE; color: #0284C7; }
        .badge.active { background: #ECFDF5; color: #059669; }
        .badge.inactive { background: #F3F4F6; color: #6B7280; }
        
        .stock-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase; 
        }
        .stock-badge.high { background: #ECFDF5; color: #047857; }
        .stock-badge.low { background: #FFF7ED; color: #C2410C; }
        .stock-badge.out { background: #FEF2F2; color: #B91C1C; }

        .product-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: auto;
        }

        .btn {
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .btn-info { background: #EFF6FF; color: #1D4ED8; } 
        .btn-info:hover { background: #DBEAFE; }

        .btn-warning { background: #FFF7ED; color: #C2410C; }
        .btn-warning:hover { background: #FFEDD5; }

        .btn-danger { background: #FEF2F2; color: #B91C1C; }
        .btn-danger:hover { background: #FEE2E2; }
        
        .btn-success { background: #ECFDF5; color: #047857; }
        .btn-success:hover { background: #D1FAE5; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        
        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: 16px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }
        
        .modal-content h3 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #111; }
        .modal-content p { color: var(--text-gray); margin-bottom: 1.5rem; }
        .modal-content input { margin-top: 0.5rem; margin-bottom: 1.5rem; font-size: 1.2rem; font-weight: 700; width: 100%; }
        
        .modal-actions { display: flex; gap: 1rem; }
        .modal-actions button { width: 100%; padding: 0.8rem; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        
        .no-products { text-align: center; padding: 4rem; color: var(--text-gray); background: #fff; border-radius: 16px; border: 1px solid var(--border-color); }
        .no-products h2 { color: var(--text-dark); margin-bottom: 0.5rem; }
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
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="markets.php">Markets</a>
            <a href="products.php" class="active">Products</a>
            <a href="orders.php">Orders</a>
            <a href="analytics.php">Reports</a>
        </div>

        <div class="header">
            <h2>Products Management</h2>
            <p>View, manage and valid stock for all products.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                ✅ &nbsp; <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics (Vibrant Cards) -->
        <div class="stats-grid">
            <div class="stat-card bg-blue">
                <div class="icon-overlay">📦</div>
                <h3>Total Products</h3>
                <div class="number"><?php echo number_format($stats['total_products']); ?></div>
            </div>
            
            <div class="stat-card bg-orange">
                <div class="icon-overlay">⚡</div>
                <h3>Active Products</h3>
                <div class="number"><?php echo number_format($stats['active_products']); ?></div>
            </div>
            
            <div class="stat-card bg-red">
                <div class="icon-overlay">⚠️</div>
                <h3>Out of Stock</h3>
                <div class="number"><?php echo number_format($stats['out_of_stock']); ?></div>
            </div>
            
            <div class="stat-card bg-yellow">
                <div class="icon-overlay">📉</div>
                <h3>Low Stock</h3>
                <div class="number"><?php echo number_format($stats['low_stock']); ?></div>
            </div>
            
            <div class="stat-card bg-purple">
                <div class="icon-overlay">⭐</div>
                <h3>Avg Rating</h3>
                <div class="number"><?php echo $stats['avg_rating'] ?? '0.0'; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Product name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Market</label>
                    <select name="market">
                        <option value="">All Markets</option>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?php echo $market['market_id']; ?>" <?php echo $market_filter == $market['market_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($market['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stock</label>
                    <select name="stock">
                        <option value="">All Stock</option>
                        <option value="available" <?php echo $stock_filter === 'available' ? 'selected' : ''; ?>>Available (>10)</option>
                        <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock (≤10)</option>
                        <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 0;">
                    <button type="submit">Filter</button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <?php if ($product['product_image']): ?>
                            <?php
                            $is_admin_url = preg_match('/^https?:\/\//i', $product['product_image']);
                            $admin_product_image = $is_admin_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']);
                            ?>
                            <img src="<?php echo $admin_product_image; ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                 class="product-image"
                                 onerror="this.src='../assets/images/default-product.jpg'">
                        <?php else: ?>
                            <div class="product-image" style="display:flex;align-items:center;justify-content:center;font-size:3rem;color:#ccc;">📦</div>
                        <?php endif; ?>
                        
                        <div class="product-content">
                            <div class="product-name" title="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </div>
                            
                            <div class="product-market">
                                🏪 <?php echo htmlspecialchars($product['market_name']); ?>
                            </div>
                            
                            <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                            
                            <div class="badges-row">
                                <span class="badge category"><?php echo htmlspecialchars($product['category']); ?></span>
                                <span class="badge <?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span>
                                <span class="badge" style="background:#FEF3C7; color:#D97706;">⭐ <?php echo $product['rating']; ?></span>
                            </div>
                            
                            <?php 
                            $stock = $product['stock'];
                            $stock_class = $stock == 0 ? 'out' : ($stock <= 10 ? 'low' : 'high');
                            $stock_text = $stock == 0 ? 'Out of Stock' : ($stock <= 10 ? "Low Stock: $stock" : "In Stock: $stock");
                            ?>
                            <div style="margin-bottom: 1rem;">
                                <span class="stock-badge <?php echo $stock_class; ?>">
                                    <?php echo $stock_text; ?>
                                </span>
                            </div>
                            
                            <div class="product-actions">
                                <button class="btn btn-info" onclick="openStockModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>', <?php echo $product['stock']; ?>)">
                                    📊 Stock
                                </button>
                                
                                <form method="POST" style="margin: 0; display:contents;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $product['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" class="btn <?php echo $product['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                        <?php echo $product['status'] === 'active' ? 'Pause' : 'Activate'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="margin: 0; grid-column: 1 / -1;" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="width: 100%;">Delete Product</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products">
                <h2>No Products Found</h2>
                <p>Try adjusting your search or filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stock Update Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <h3>Update Stock</h3>
            <p id="modalProductName">Product Name</p>
            <form method="POST">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="product_id" id="modalProductId">
                <label>New Stock Quantity:</label>
                <input type="number" name="stock" id="modalStock" min="0" required>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" onclick="closeStockModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStockModal(productId, productName, currentStock) {
            document.getElementById('modalProductId').value = productId;
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('modalStock').value = currentStock;
            document.getElementById('stockModal').classList.add('show');
        }

        function closeStockModal() {
            document.getElementById('stockModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('stockModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStockModal();
            }
        });
    </script>
</body>
</html>