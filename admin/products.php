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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
         .container {
            /* max-width: 1400px;
            margin: 0 auto; */
            flex: 1; padding: 30px;
        }
        
        /* Sidebar */
        /* .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar h2 { margin-bottom: 30px; color: #3498db; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin: 15px 0; }
        .sidebar ul li a { color: white; text-decoration: none; display: block; padding: 10px; border-radius: 5px; transition: 0.3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #34495e; } */
        
        /* Main Content */
        .main-content { flex: 1; padding: 30px; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { color: #2c3e50; margin-bottom: 10px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #7f8c8d; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #2c3e50; }
        .stat-card.blue .number { color: #3498db; }
        .stat-card.green .number { color: #27ae60; }
        .stat-card.red .number { color: #e74c3c; }
        .stat-card.orange .number { color: #e67e22; }
        .stat-card.purple .number { color: #9b59b6; }
        
        /* Filters */
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filters form { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
        .filters .form-group { flex: 1; min-width: 160px; }
        .filters label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; font-size: 14px; }
        .filters select, .filters input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filters button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .filters button:hover { background: #2980b9; }
        
        /* Products Grid */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        
        .product-card { background: white; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden; transition: 0.3s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        
        .product-image { width: 100%; height: 180px; object-fit: cover; background: #ecf0f1; }
        
        .product-content { padding: 15px; }
        .product-name { font-size: 16px; font-weight: bold; color: #2c3e50; margin-bottom: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .product-market { font-size: 13px; color: #7f8c8d; margin-bottom: 8px; }
        .product-price { font-size: 22px; font-weight: bold; color: #27ae60; margin: 10px 0; }
        
        .product-info { display: flex; justify-content: space-between; margin: 10px 0; font-size: 13px; }
        .info-item { text-align: center; }
        .info-label { color: #7f8c8d; display: block; }
        .info-value { font-weight: bold; color: #2c3e50; }
        
        .stock-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; margin: 5px 0; }
        .stock-badge.high { background: #27ae60; color: white; }
        .stock-badge.low { background: #f39c12; color: white; }
        .stock-badge.out { background: #e74c3c; color: white; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; margin: 5px 5px 5px 0; }
        .badge.active { background: #27ae60; color: white; }
        .badge.inactive { background: #95a5a6; color: white; }
        .badge.category { background: #3498db; color: white; }
        
        .rating { color: #f39c12; font-size: 14px; margin: 8px 0; }
        
        .product-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .btn { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-align: center; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-info { background: #3498db; color: white; }
        .btn:hover { opacity: 0.8; }
        
        /* Stock Update Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 400px; width: 90%; }
        .modal-content h3 { margin-bottom: 20px; color: #2c3e50; }
        .modal-content input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .modal-actions button { flex: 1; }
        
        /* Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .no-products { text-align: center; padding: 60px 20px; color: #95a5a6; font-size: 18px; background: white; border-radius: 10px; }
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
       
        <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="markets.php" >Markets</a>
        <a href="products.php" class="active">Products</a>
        <a href="orders.php">Orders</a>
        <a href="analytics.php">Analytics & Reports</a>
    </div>


        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Products Management</h1>
                <p>View and manage all products across all markets</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <h3>Total Products</h3>
                    <div class="number"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Active Products</h3>
                    <div class="number"><?php echo $stats['active_products']; ?></div>
                </div>
                <div class="stat-card red">
                    <h3>Out of Stock</h3>
                    <div class="number"><?php echo $stats['out_of_stock']; ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>Low Stock</h3>
                    <div class="number"><?php echo $stats['low_stock']; ?></div>
                </div>
                <div class="stat-card purple">
                    <h3>Average Rating</h3>
                    <div class="number"><?php echo $stats['avg_rating'] ?? '0.0'; ?>⭐</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Product name" value="<?php echo htmlspecialchars($search); ?>">
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
                    <div class="form-group">
                        <label>&nbsp;</label>
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
                                // Detect if image is URL or local file
                                $is_admin_url = preg_match('/^https?:\/\//i', $product['product_image']);
                                $admin_product_image = $is_admin_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']);
                                ?>
                                <img src="<?php echo $admin_product_image; ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     class="product-image"
                                     onerror="this.src='../assets/images/default-product.jpg'">
                            <?php else: ?>
                                <img src="../assets/images/default-product.jpg" alt="No Image" class="product-image">
                            <?php endif; ?>
                            
                            <div class="product-content">
                                <div class="product-name" title="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </div>
                                
                                <div class="product-market">
                                    🏪 <?php echo htmlspecialchars($product['market_name']); ?>
                                </div>
                                
                                <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                                
                                <div>
                                    <span class="badge category"><?php echo htmlspecialchars($product['category']); ?></span>
                                    <span class="badge <?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span>
                                </div>
                                
                                <?php 
                                $stock = $product['stock'];
                                $stock_class = $stock == 0 ? 'out' : ($stock <= 10 ? 'low' : 'high');
                                $stock_text = $stock == 0 ? 'Out of Stock' : ($stock <= 10 ? "Low Stock: $stock" : "In Stock: $stock");
                                ?>
                                <span class="stock-badge <?php echo $stock_class; ?>">
                                    <?php echo $stock_text; ?>
                                </span>
                                
                                <div class="rating">
                                    <?php 
                                    $rating = $product['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '⭐' : '☆';
                                    }
                                    ?>
                                </div>
                                
                                <div class="product-actions">
                                    <button class="btn btn-info" onclick="openStockModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>', <?php echo $product['stock']; ?>)">
                                        📊 Stock
                                    </button>
                                    
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $product['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn <?php echo $product['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                            <?php echo $product['status'] === 'active' ? '⏸ Pause' : '▶ Active'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="margin: 0; grid-column: 1 / -1;" onsubmit="return confirm('Delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="width: 100%;">🗑 Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-products">
                    <h2>No Products Found</h2>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stock Update Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <h3>Update Stock</h3>
            <p id="modalProductName" style="color: #7f8c8d; margin-bottom: 15px;"></p>
            <form method="POST">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="product_id" id="modalProductId">
                <label>New Stock Quantity:</label>
                <input type="number" name="stock" id="modalStock" min="0" required>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" onclick="closeStockModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Update</button>
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