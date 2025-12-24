<?php
/**
 * ByteShop - Admin Markets Management
 * View and manage all markets in the system
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require admin access
require_admin();

// Handle market actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $market_id = clean_input($_POST['market_id']);
                $new_status = clean_input($_POST['status']);
                
                $stmt = $pdo->prepare("UPDATE markets SET status = ? WHERE market_id = ?");
                $stmt->execute([$new_status, $market_id]);
                
                $_SESSION['success'] = "Market status updated successfully!";
                header('Location: markets.php');
                exit;
                break;
                
            case 'delete_market':
                $market_id = clean_input($_POST['market_id']);
                
                // Delete market and cascade will handle products and orders
                $stmt = $pdo->prepare("DELETE FROM markets WHERE market_id = ?");
                $stmt->execute([$market_id]);
                
                $_SESSION['success'] = "Market deleted successfully!";
                header('Location: markets.php');
                exit;
                break;
        }
    }
}

// Get filter parameters
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get unique locations and categories for filters
$locations = $pdo->query("SELECT DISTINCT location FROM markets ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$categories = $pdo->query("SELECT DISTINCT market_category FROM markets ORDER BY market_category")->fetchAll(PDO::FETCH_COLUMN);

// Build query
$query = "SELECT m.*, u.name as owner_name, u.email as owner_email,
          (SELECT COUNT(*) FROM products WHERE market_id = m.market_id) as product_count,
          (SELECT COUNT(*) FROM order_items WHERE market_id = m.market_id) as order_count
          FROM markets m
          LEFT JOIN users u ON m.owner_id = u.user_id
          WHERE 1=1";
$params = [];

if ($location_filter) {
    $query .= " AND m.location = ?";
    $params[] = $location_filter;
}

if ($category_filter) {
    $query .= " AND m.market_category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (m.market_name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$markets = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_markets,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_markets,
    (SELECT COUNT(*) FROM products) as total_products,
    ROUND(AVG(rating), 1) as avg_rating
FROM markets";
$stats = $pdo->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markets Management - ByteShop Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; }
        .sidebar h2 { margin-bottom: 30px; color: #3498db; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin: 15px 0; }
        .sidebar ul li a { color: white; text-decoration: none; display: block; padding: 10px; border-radius: 5px; transition: 0.3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #34495e; }
        
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
        .stat-card.orange .number { color: #e67e22; }
        .stat-card.purple .number { color: #9b59b6; }
        
        /* Filters */
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filters form { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .filters .form-group { flex: 1; min-width: 180px; }
        .filters label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        .filters select, .filters input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .filters button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .filters button:hover { background: #2980b9; }
        
        /* Markets Grid */
        .markets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        
        .market-card { background: white; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden; transition: 0.3s; }
        .market-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        
        .market-image { width: 100%; height: 200px; object-fit: cover; background: #ecf0f1; }
        
        .market-content { padding: 20px; }
        .market-name { font-size: 20px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .market-info { font-size: 14px; color: #7f8c8d; margin: 5px 0; }
        .market-info strong { color: #2c3e50; }
        
        .rating { color: #f39c12; margin: 10px 0; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; margin: 5px 5px 5px 0; }
        .badge.active { background: #27ae60; color: white; }
        .badge.inactive { background: #95a5a6; color: white; }
        .badge.category { background: #3498db; color: white; }
        .badge.location { background: #9b59b6; color: white; }
        
        .market-stats { display: flex; justify-content: space-around; margin: 15px 0; padding: 15px 0; border-top: 1px solid #ecf0f1; border-bottom: 1px solid #ecf0f1; }
        .stat-item { text-align: center; }
        .stat-item .label { font-size: 12px; color: #7f8c8d; }
        .stat-item .value { font-size: 20px; font-weight: bold; color: #2c3e50; }
        
        .market-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; flex: 1; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { opacity: 0.8; }
        
        /* Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .no-markets { text-align: center; padding: 60px 20px; color: #95a5a6; font-size: 18px; background: white; border-radius: 10px; }
    </style>
    <link rel="stylesheet" href="/byteshop/assets/css/style.css">
    
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>ByteShop Admin</h2>
            <ul>
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="users.php">👥 Users</a></li>
                <li><a href="markets.php" class="active">🏪 Markets</a></li>
                <li><a href="products.php">📦 Products</a></li>
                <li><a href="orders.php">🛒 Orders</a></li>
                <li><a href="analytics.php">📈 Analytics</a></li>
                <li><a href="../logout.php">🚪 Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Markets Management</h1>
                <p>View and manage all markets in the system</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <h3>Total Markets</h3>
                    <div class="number"><?php echo $stats['total_markets']; ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Active Markets</h3>
                    <div class="number"><?php echo $stats['active_markets']; ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>Total Products</h3>
                    <div class="number"><?php echo $stats['total_products']; ?></div>
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
                        <input type="text" name="search" placeholder="Market or Owner name" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
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

            <!-- Markets Grid -->
            <?php if (count($markets) > 0): ?>
                <div class="markets-grid">
                    <?php foreach ($markets as $market): ?>
                        <div class="market-card">
                            <?php if ($market['market_image']): ?>
                                <?php
// Detect if image is URL or local file
$is_market_url = preg_match('/^https?:\/\//i', $market['market_image']);
$admin_market_image = $is_market_url ? htmlspecialchars($market['market_image']) : '../uploads/markets/' . htmlspecialchars($market['market_image']);
?>
<img src="<?php echo $admin_market_image; ?>" 
     alt="<?php echo htmlspecialchars($market['market_name']); ?>" 
     class="market-image"
     onerror="this.src='../assets/images/default-market.jpg'">
                                <div class="market-image" style="display: flex; align-items: center; justify-content: center; font-size: 60px;">🏪</div>
                            <?php endif; ?>
                            
                            <div class="market-content">
                                <div class="market-name"><?php echo htmlspecialchars($market['market_name']); ?></div>
                                
                                <div class="market-info">
                                    <strong>Owner:</strong> <?php echo htmlspecialchars($market['owner_name']); ?>
                                </div>
                                <div class="market-info">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($market['owner_email']); ?>
                                </div>
                                
                                <div style="margin: 10px 0;">
                                    <span class="badge location">📍 <?php echo htmlspecialchars($market['location']); ?></span>
                                    <span class="badge category"><?php echo htmlspecialchars($market['market_category']); ?></span>
                                    <span class="badge <?php echo $market['status']; ?>"><?php echo ucfirst($market['status']); ?></span>
                                </div>
                                
                                <div class="rating">
                                    <?php 
                                    $rating = $market['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '⭐' : '☆';
                                    }
                                    echo " ({$rating})";
                                    ?>
                                </div>
                                
                                <div class="market-stats">
                                    <div class="stat-item">
                                        <div class="label">Products</div>
                                        <div class="value"><?php echo $market['product_count']; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="label">Orders</div>
                                        <div class="value"><?php echo $market['order_count']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="market-actions">
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="market_id" value="<?php echo $market['market_id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $market['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn <?php echo $market['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                            <?php echo $market['status'] === 'active' ? '⏸ Deactivate' : '▶ Activate'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this market? All products and orders will be removed.');">
                                        <input type="hidden" name="action" value="delete_market">
                                        <input type="hidden" name="market_id" value="<?php echo $market['market_id']; ?>">
                                        <button type="submit" class="btn btn-danger">🗑 Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-markets">
                    <h2>No Markets Found</h2>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Market Card Example -->
<div class="market-card">
    <img src="image.jpg" class="market-card-image">
    <div class="market-card-content">
        <h3 class="market-card-title">Market Name</h3>
        <div class="market-card-rating">
            <div class="stars">★★★★★</div>
        </div>
    </div>
</div>
</body>
</html>