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
        body { 
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #0a0a0a;
            color: #e0e0e0;
            font-size: 14.4px; /* 90% of 16px */
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
        
        .stat-card.red .number { 
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.red::before { background: linear-gradient(90deg, #f44336 0%, #d32f2f 100%); }
        .stat-card.red:hover::before { opacity: 1; }
        
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
            gap: 10.8px; /* 90% of 12px */
            flex-wrap: wrap; 
            align-items: end; 
        }
        
        .filters .form-group { 
            flex: 1; 
            min-width: 144px; /* 90% of 160px */
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
            font-size: 0.765rem; /* 90% of 0.85rem */
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
        
        /* Products Grid */
        .products-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(252px, 1fr)); /* 90% of 280px */
            gap: 18px; /* 90% of 20px */
        }
        
        .product-card { 
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            border-radius: 14.4px; /* 90% of 16px */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            overflow: hidden; 
            transition: all 0.3s;
            border: 1px solid #2a2a2a;
        }
        
        .product-card:hover { 
            transform: translateY(-4.5px); /* 90% of -5px */
            box-shadow: 0 7.2px 21.6px rgba(255, 107, 53, 0.2); /* 90% scale */
        }
        
        .product-image { 
            width: 100%; 
            height: 162px; /* 90% of 180px */
            object-fit: cover; 
            background: #0f0f0f;
        }
        
        .product-content { 
            padding: 13.5px; /* 90% of 15px */
        }
        
        .product-name { 
            font-size: 1.35rem; /* 90% of 1.5rem */
            font-weight: bold; 
            color: #e0e0e0;
            margin-bottom: 7.2px; /* 90% of 8px */
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap; 
        }
        
        .product-market { 
            font-size: 0.72rem; /* 90% of 0.8rem */
            color: #909090;
            margin-bottom: 7.2px; /* 90% of 8px */
        }
        
        .product-price { 
            font-size: 1.8rem; /* 90% of 2rem */
            font-weight: bold; 
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 9px 0; /* 90% of 10px */
        }
        
        .product-info { 
            display: flex; 
            justify-content: space-between; 
            margin: 9px 0; /* 90% of 10px */
            font-size: 0.72rem; /* 90% of 0.8rem */
        }
        
        .info-item { 
            text-align: center; 
        }
        
        .info-label { 
            color: #909090;
            display: block; 
        }
        
        .info-value { 
            font-weight: bold; 
            color: #e0e0e0;
        }
        
        .stock-badge { 
            padding: 0.36rem 0.9rem; /* 90% of 0.4rem 1rem */
            border-radius: 18px; /* 90% of 20px */
            font-size: 0.675rem; /* 90% of 0.75rem */
            font-weight: 600;
            display: inline-block; 
            margin: 4.5px 0; /* 90% of 5px */
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }
        
        .stock-badge.high { 
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .stock-badge.low { 
            background: rgba(255, 152, 0, 0.15);
            color: #ff9800;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }
        
        .stock-badge.out { 
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        
        .badge { 
            padding: 0.36rem 0.9rem; /* 90% of 0.4rem 1rem */
            border-radius: 18px; /* 90% of 20px */
            font-size: 0.63rem; /* 90% of 0.7rem */
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
        
        .rating { 
            color: #f39c12; 
            font-size: 0.765rem; /* 90% of 0.85rem */
            margin: 7.2px 0; /* 90% of 8px */
        }
        
        .product-actions { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 7.2px; /* 90% of 8px */
            margin-top: 10.8px; /* 90% of 12px */
        }
        
        .btn { 
            padding: 7.2px 10.8px; /* 90% of 8px 12px */
            border: none; 
            border-radius: 7.2px; /* 90% of 8px */
            cursor: pointer; 
            font-size: 0.675rem; /* 90% of 0.75rem */
            text-align: center;
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
        
        .btn-info { 
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: white;
        }
        
        .btn-info:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 3.6px 10.8px rgba(33, 150, 243, 0.3); /* 90% scale */
        }
        
        /* Stock Update Modal */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.8);
            z-index: 1000; 
        }
        
        .modal.show { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        
        .modal-content { 
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 27px; /* 90% of 30px */
            border-radius: 14.4px; /* 90% of 16px */
            max-width: 360px; /* 90% of 400px */
            width: 90%;
            box-shadow: 0 9px 36px rgba(0,0,0,0.6); /* 90% scale */
            border: 1px solid #2a2a2a;
        }
        
        .modal-content h3 { 
            margin-bottom: 18px; /* 90% of 20px */
            color: #e0e0e0;
            font-size: 1.62rem; /* 90% of 1.8rem */
        }
        
        .modal-content p {
            color: #909090;
            margin-bottom: 13.5px; /* 90% of 15px */
            font-size: 0.9rem;
        }
        
        .modal-content label {
            color: #909090;
            font-weight: 600;
            font-size: 0.765rem; /* 90% of 0.85rem */
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }
        
        .modal-content input { 
            width: 100%; 
            padding: 9px; /* 90% of 10px */
            border: 1px solid #2a2a2a;
            border-radius: 7.2px; /* 90% of 8px */
            margin: 9px 0; /* 90% of 10px */
            background: #0f0f0f;
            color: #e0e0e0;
            font-size: 0.9rem;
        }
        
        .modal-content input:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.1); /* 90% of 3px */
        }
        
        .modal-actions { 
            display: flex; 
            gap: 9px; /* 90% of 10px */
            margin-top: 18px; /* 90% of 20px */
        }
        
        .modal-actions button { 
            flex: 1; 
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
        
        .no-products { 
            text-align: center; 
            padding: 54px 18px; /* 90% of 60px 20px */
            color: #707070;
            font-size: 1.44rem; /* 90% of 1.6rem */
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            border-radius: 14.4px; /* 90% of 16px */
            border: 1px solid #2a2a2a;
        }
        
        .no-products h2 {
            color: #909090;
            margin-bottom: 9px; /* 90% of 10px */
            font-size: 1.62rem; /* 90% of 1.8rem */
        }
        
        .no-products p {
            color: #707070;
            font-size: 0.9rem;
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

</style>
</head>
<body>
    <div class="container">
        
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
            <p id="modalProductName" style="color: #909090; margin-bottom: 13.5px;"></p>
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