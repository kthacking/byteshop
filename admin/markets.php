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
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #0a0a0a;
        color: #e0e0e0;
        font-size: 14.4px; /* 90% of 16px */
    }

     /* Navigation Links (Top of Container) */
    .nav-links {
        display: flex;
        gap: 0.72rem; /* 90% of 0.8rem */
        margin-bottom: 2.25rem; /* 90% of 2.5rem */
        padding: 1.08rem; /* 90% of 1.2rem */
        background: #161616;
        border-radius: 14.4px; /* 90% of 16px */
        flex-wrap: wrap;
        border: 1px solid #2a2a2a;
    }

    .nav-links a {
        padding: 0.72rem 1.35rem; /* 90% of 0.8rem 1.5rem */
        background: #1f1f1f;
        color: #b0b0b0;
        text-decoration: none;
        border-radius: 9px; /* 90% of 10px */
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.81rem; /* 90% of 0.9rem */
        border: 1px solid #2a2a2a;
    }

    .nav-links a:hover {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 5.4px 14.4px rgba(255, 107, 53, 0.3); /* 90% scale */
        border-color: transparent;
    }

    .nav-links a.active {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        border-color: transparent;
    }
     .container {
        flex: 1; 
        padding: 27px; /* 90% of 30px */
        max-width: 100%; /* 90% of 1600px */
        margin: 0 auto;
    }

    /* Main Content */
    .main-content {
        flex: 1;
        padding: 27px; /* 90% of 30px */
    }

    .header {
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        padding: 1.8rem; /* 90% of 2rem */
        border-radius: 14.4px; /* 90% of 16px */
        margin-bottom: 27px; /* 90% of 30px */
        box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
        border: 1px solid #2a2a2a;
        position: relative;
        overflow: hidden;
    }

    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2.7px; /* 90% of 3px */
        background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
    }

    .header h1 {
        color: #e0e0e0;
        margin-bottom: 9px; /* 90% of 10px */
        font-weight: 700;
        font-size: 1.8rem; /* 90% of 2rem */
    }

    .header p {
        color: #909090;
        font-size: 0.9rem; /* 90% of 1rem */
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* 90% of 200px */
        gap: 18px; /* 90% of 20px */
        margin-bottom: 27px; /* 90% of 30px */
    }

    .stat-card {
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        padding: 1.8rem; /* 90% of 2rem */
        border-radius: 14.4px; /* 90% of 16px */
        box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
        border: 1px solid #2a2a2a;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2.7px; /* 90% of 3px */
        opacity: 0;
        transition: opacity 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-4.5px); /* 90% of -5px */
        box-shadow: 0 7.2px 21.6px rgba(255, 107, 53, 0.2); /* 90% scale */
    }

    .stat-card h3 {
        color: #909090;
        font-size: 0.765rem; /* 90% of 0.85rem */
        margin-bottom: 9px; /* 90% of 10px */
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.45px; /* 90% of 0.5px */
    }

    .stat-card .number {
        font-size: 2.25rem; /* 90% of 2.5rem */
        font-weight: 700;
    }

    .stat-card.blue .number {
        background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .stat-card.blue::before { background: linear-gradient(90deg, #2196f3 0%, #1976d2 100%); }
    .stat-card.blue:hover::before { opacity: 1; }

    .stat-card.green .number {
        background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .stat-card.green::before { background: linear-gradient(90deg, #4caf50 0%, #388e3c 100%); }
    .stat-card.green:hover::before { opacity: 1; }

    .stat-card.orange .number {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .stat-card.orange::before { background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%); }
    .stat-card.orange:hover::before { opacity: 1; }

    .stat-card.purple .number {
        background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .stat-card.purple::before { background: linear-gradient(90deg, #9c27b0 0%, #7b1fa2 100%); }
    .stat-card.purple:hover::before { opacity: 1; }

    /* Filters */
    .filters {
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        padding: 1.8rem; /* 90% of 2rem */
        border-radius: 14.4px; /* 90% of 16px */
        margin-bottom: 18px; /* 90% of 20px */
        box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
        border: 1px solid #2a2a2a;
    }

    .filters form {
        display: flex;
        gap: 13.5px; /* 90% of 15px */
        flex-wrap: wrap;
        align-items: end;
    }

    .filters .form-group {
        flex: 1;
        min-width: 162px; /* 90% of 180px */
    }

    .filters label {
        display: block;
        margin-bottom: 4.5px; /* 90% of 5px */
        color: #909090;
        font-weight: 600;
        font-size: 0.765rem; /* 90% of 0.85rem */
        text-transform: uppercase;
        letter-spacing: 0.45px; /* 90% of 0.5px */
    }

    .filters select,
    .filters input {
        width: 100%;
        padding: 9px; /* 90% of 10px */
        border: 1px solid #2a2a2a;
        border-radius: 7.2px; /* 90% of 8px */
        background: #0f0f0f;
        color: #e0e0e0;
        transition: all 0.3s;
        font-size: 0.9rem;
    }

    .filters select:focus,
    .filters input:focus {
        outline: none;
        border-color: #ff6b35;
        box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.1); /* 90% of 3px */
    }

    .filters button {
        padding: 9px 18px; /* 90% of 10px 20px */
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        border: none;
        border-radius: 7.2px; /* 90% of 8px */
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.9rem;
    }

    .filters button:hover {
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 5.4px 18px rgba(255, 107, 53, 0.4); /* 90% scale */
    }

    /* Markets Grid */
    .markets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(315px, 1fr)); /* 90% of 350px */
        gap: 18px; /* 90% of 20px */
    }

    .market-card {
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        border-radius: 14.4px; /* 90% of 16px */
        box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
        overflow: hidden;
        transition: all 0.3s;
        border: 1px solid #2a2a2a;
    }

    .market-card:hover {
        transform: translateY(-4.5px); /* 90% of -5px */
        box-shadow: 0 7.2px 21.6px rgba(255, 107, 53, 0.2); /* 90% scale */
    }

    .market-image {
        width: 100%;
        height: 180px; /* 90% of 200px */
        object-fit: cover;
        background: #0f0f0f;
    }

    .market-content {
        padding: 18px; /* 90% of 20px */
    }

    .market-name {
        font-size: 1.62rem; /* 90% of 1.8rem */
        font-weight: bold;
        color: #e0e0e0;
        margin-bottom: 9px; /* 90% of 10px */
    }

    .market-info {
        font-size: 0.765rem; /* 90% of 0.85rem */
        color: #909090;
        margin: 4.5px 0; /* 90% of 5px */
    }

    .market-info strong {
        color: #b0b0b0;
    }

    .rating {
        color: #f39c12;
        margin: 9px 0; /* 90% of 10px */
        font-size: 0.9rem;
    }

    .badge {
        padding: 0.36rem 0.9rem; /* 90% of 0.4rem 1rem */
        border-radius: 18px; /* 90% of 20px */
        font-size: 0.675rem; /* 90% of 0.75rem */
        font-weight: 600;
        display: inline-block;
        margin: 4.5px 4.5px 4.5px 0; /* 90% of 5px */
        text-transform: uppercase;
        letter-spacing: 0.45px; /* 90% of 0.5px */
    }

    .badge.active {
        background: rgba(76, 175, 80, 0.15);
        color: #4caf50;
        border: 1px solid rgba(76, 175, 80, 0.3);
    }

    .badge.inactive {
        background: rgba(158, 158, 158, 0.15);
        color: #9e9e9e;
        border: 1px solid rgba(158, 158, 158, 0.3);
    }

    .badge.category {
        background: rgba(33, 150, 243, 0.15);
        color: #2196f3;
        border: 1px solid rgba(33, 150, 243, 0.3);
    }

    .badge.location {
        background: rgba(156, 39, 176, 0.15);
        color: #9c27b0;
        border: 1px solid rgba(156, 39, 176, 0.3);
    }

    .market-stats {
        display: flex;
        justify-content: space-around;
        margin: 13.5px 0; /* 90% of 15px */
        padding: 13.5px 0; /* 90% of 15px */
        border-top: 1px solid #2a2a2a;
        border-bottom: 1px solid #2a2a2a;
    }

    .stat-item {
        text-align: center;
    }

    .stat-item .label {
        font-size: 0.675rem; /* 90% of 0.75rem */
        color: #909090;
        text-transform: uppercase;
        letter-spacing: 0.45px; /* 90% of 0.5px */
    }

    .stat-item .value {
        font-size: 1.62rem; /* 90% of 1.8rem */
        font-weight: bold;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .market-actions {
        display: flex;
        gap: 9px; /* 90% of 10px */
        margin-top: 13.5px; /* 90% of 15px */
    }

    .btn {
        padding: 7.2px 13.5px; /* 90% of 8px 15px */
        border: none;
        border-radius: 7.2px; /* 90% of 8px */
        cursor: pointer;
        font-size: 0.765rem; /* 90% of 0.85rem */
        flex: 1;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-success {
        background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
        color: white;
    }

    .btn-success:hover {
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 3.6px 10.8px rgba(76, 175, 80, 0.3); /* 90% scale */
    }

    .btn-warning {
        background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        color: white;
    }

    .btn-warning:hover {
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 3.6px 10.8px rgba(255, 152, 0, 0.3); /* 90% scale */
    }

    .btn-danger {
        background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        color: white;
    }

    .btn-danger:hover {
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 3.6px 10.8px rgba(244, 67, 54, 0.3); /* 90% scale */
    }

    /* Alerts */
    .alert {
        padding: 13.5px; /* 90% of 15px */
        margin-bottom: 18px; /* 90% of 20px */
        border-radius: 10.8px; /* 90% of 12px */
        font-weight: 500;
        font-size: 0.9rem;
    }

    .alert-success {
        background: rgba(76, 175, 80, 0.15);
        color: #4caf50;
        border: 1px solid rgba(76, 175, 80, 0.3);
    }

    .alert-error {
        background: rgba(244, 67, 54, 0.15);
        color: #f44336;
        border: 1px solid rgba(244, 67, 54, 0.3);
    }

    .no-markets {
        text-align: center;
        padding: 54px 18px; /* 90% of 60px 20px */
        color: #707070;
        font-size: 1.44rem; /* 90% of 1.6rem */
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        border-radius: 14.4px; /* 90% of 16px */
        border: 1px solid #2a2a2a;
    }

    .no-markets h2 {
        color: #909090;
        margin-bottom: 9px; /* 90% of 10px */
        font-size: 1.62rem; /* 90% of 1.8rem */
    }

    .no-markets p {
        color: #707070;
        font-size: 0.9rem;
    }
</style>
<link rel="stylesheet" href="/byteshop/assets/css/style.css">

</head>

<body>
    <div class="container">
         <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="markets.php" class="active">Markets</a>
        <a href="products.php">Products</a>
        <a href="orders.php">Orders</a>
        <a href="analytics.php">Analytics & Reports</a>
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
                        <input type="text" name="search" placeholder="Market or Owner name"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>"
                                <?php echo $location_filter === $location ? 'selected' : ''; ?>>
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
                            <option value="<?php echo htmlspecialchars($category); ?>"
                                <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active
                            </option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                                Inactive</option>
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
                        alt="<?php echo htmlspecialchars($market['market_name']); ?>" class="market-image"
                        onerror="this.src='../assets/images/default-market.jpg'">
                    <?php else: ?>
                    <div class="market-image"
                        style="display: flex; align-items: center; justify-content: center; font-size: 54px;">🏪</div> <!-- 90% of 60px -->
                    <?php endif; ?>

                    <div class="market-content">
                        <div class="market-name"><?php echo htmlspecialchars($market['market_name']); ?></div>

                        <div class="market-info">
                            <strong>Owner:</strong> <?php echo htmlspecialchars($market['owner_name']); ?>
                        </div>
                        <div class="market-info">
                            <strong>Email:</strong> <?php echo htmlspecialchars($market['owner_email']); ?>
                        </div>

                        <div style="margin: 9px 0;"> <!-- 90% of 10px -->
                            <span class="badge location">📍 <?php echo htmlspecialchars($market['location']); ?></span>
                            <span
                                class="badge category"><?php echo htmlspecialchars($market['market_category']); ?></span>
                            <span
                                class="badge <?php echo $market['status']; ?>"><?php echo ucfirst($market['status']); ?></span>
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
                                <input type="hidden" name="status"
                                    value="<?php echo $market['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit"
                                    class="btn <?php echo $market['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                    <?php echo $market['status'] === 'active' ? '⏸ Deactivate' : '▶ Activate'; ?>
                                </button>
                            </form>
                            <form method="POST" style="flex: 1;"
                                onsubmit="return confirm('Are you sure you want to delete this market? All products and orders will be removed.');">
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
    
</body>

</html>