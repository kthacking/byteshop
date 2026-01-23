<?php
/**
 * ByteShop - Admin Markets Management
 * View and manage all markets in the system
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

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
            /* Grid Pattern */
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

        /* Header */
        .header {
            margin-bottom: 2rem;
        }
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

        /* Gradients */
        .bg-orange { background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); }
        .bg-purple { background: linear-gradient(135deg, #A855F7 0%, #FF6B35 100%); }
        .bg-blue { background: linear-gradient(135deg, #3B82F6 0%, #2DD4BF 100%); }
        .bg-dark { background: linear-gradient(135deg, #1F2937 0%, #111827 100%); }

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

        .form-group { flex: 1; min-width: 180px; }

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

        /* Market Cards Grid */
        .markets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .market-card {
            background: #fff;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .market-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: #d1d5db;
        }

        .market-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 2rem;
        }

        .market-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .market-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .market-info {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.2rem;
        }
        
        .market-info strong { color: var(--text-dark); }

        .badges-row {
            margin: 1rem 0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge.active { background: #ECFDF5; color: #059669; }
        .badge.inactive { background: #F3F4F6; color: #6B7280; }
        .badge.category { background: #E0F2FE; color: #0284C7; }
        .badge.location { background: #F3E8FF; color: #9333EA; }

        .market-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            background: #f9fafb;
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem 0;
            text-align: center;
        }

        .stat-item .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-gray);
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .stat-item .value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .market-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: auto;
        }

        .btn {
            padding: 0.6rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-warning { background: #FFF7ED; color: #C2410C; border: 1px solid #FFEDD5; }
        .btn-warning:hover { background: #FFEDD5; }

        .btn-success { background: #ECFDF5; color: #047857; border: 1px solid #D1FAE5; }
        .btn-success:hover { background: #D1FAE5; }

        .btn-danger { background: #FEF2F2; color: #B91C1C; border: 1px solid #FEE2E2; }
        .btn-danger:hover { background: #FEE2E2; }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        .no-result {
            text-align: center;
            padding: 4rem;
            background: #fff;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            color: var(--text-gray);
        }
        
        .no-result h2 { color: var(--text-dark); margin-bottom: 0.5rem; }

        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 1rem; }
            .markets-grid { grid-template-columns: 1fr; }
            .filters form { flex-direction: column; align-items: stretch; }
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
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="markets.php" class="active">Markets</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="analytics.php">Reports</a>
        </div>
        
        <div class="header">
            <h2>Markets Management</h2>
            <p>View, manage, and audit all marketplace vendors.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                ✅ &nbsp; <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                ❌ &nbsp; <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics (Vibrant Cards) -->
        <div class="stats-grid">
            <div class="stat-card bg-blue">
                <div class="icon-overlay">🏪</div>
                <h3>Total Markets</h3>
                <div class="number"><?php echo number_format($stats['total_markets']); ?></div>
            </div>
            
            <div class="stat-card bg-orange">
                <div class="icon-overlay">⚡</div>
                <h3>Active Markets</h3>
                <div class="number"><?php echo number_format($stats['active_markets']); ?></div>
            </div>
            
            <div class="stat-card bg-dark">
                <div class="icon-overlay">📦</div>
                <h3>Total Products</h3>
                <div class="number"><?php echo number_format($stats['total_products']); ?></div>
            </div>
            
            <div class="stat-card bg-purple">
                <div class="icon-overlay">⭐</div>
                <h3>Average Rating</h3>
                <div class="number"><?php echo $stats['avg_rating'] ?? '0.0'; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Market or Owner Name..." value="<?php echo htmlspecialchars($search); ?>">
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
                <div class="form-group" style="flex: 0;">
                    <button type="submit">Filter Results</button>
                </div>
            </form>
        </div>

        <!-- Markets Grid -->
        <?php if (count($markets) > 0): ?>
            <div class="markets-grid">
                <?php foreach ($markets as $market): ?>
                <div class="market-card">
                    <!-- Image -->
                    <?php if ($market['market_image']): ?>
                        <?php
                            $is_market_url = preg_match('/^https?:\/\//i', $market['market_image']);
                            $admin_market_image = $is_market_url ? htmlspecialchars($market['market_image']) : '../uploads/markets/' . htmlspecialchars($market['market_image']);
                        ?>
                        <img src="<?php echo $admin_market_image; ?>" alt="<?php echo htmlspecialchars($market['market_name']); ?>" class="market-image" onerror="this.src='../assets/images/default-market.jpg'">
                    <?php else: ?>
                        <div class="market-image">🏪</div>
                    <?php endif; ?>

                    <div class="market-content">
                        <div class="market-name"><?php echo htmlspecialchars($market['market_name']); ?></div>
                        
                        <div class="market-info">
                            <strong>Owner:</strong> <?php echo htmlspecialchars($market['owner_name']); ?>
                        </div>
                        <div class="market-info" style="font-size: 0.8rem;">
                            <?php echo htmlspecialchars($market['owner_email']); ?>
                        </div>

                        <!-- Badges -->
                        <div class="badges-row">
                            <span class="badge location">📍 <?php echo htmlspecialchars($market['location']); ?></span>
                            <span class="badge category"><?php echo htmlspecialchars($market['market_category']); ?></span>
                            <span class="badge <?php echo $market['status']; ?>"><?php echo ucfirst($market['status']); ?></span>
                            <span class="badge" style="background:#FEF3C7; color:#D97706;">
                                <?php 
                                    $rating = $market['rating'];
                                    echo "⭐ " . $rating;
                                ?>
                            </span>
                        </div>

                        <!-- Stats Box -->
                        <div class="market-stats">
                            <div class="stat-item">
                                <div class="value"><?php echo $market['product_count']; ?></div>
                                <div class="label">Products</div>
                            </div>
                            <div class="stat-item">
                                <div class="value"><?php echo $market['order_count']; ?></div>
                                <div class="label">Orders</div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="market-actions">
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="market_id" value="<?php echo $market['market_id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $market['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" class="btn <?php echo $market['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                    <?php echo $market['status'] === 'active' ? 'Pause' : 'Activate'; ?>
                                </button>
                            </form>
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('Delete this market? Products and orders will be removed.');">
                                <input type="hidden" name="action" value="delete_market">
                                <input type="hidden" name="market_id" value="<?php echo $market['market_id']; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-result">
                <h2>No Markets Found</h2>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>