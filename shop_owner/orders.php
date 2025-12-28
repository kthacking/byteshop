<?php
/**
 * ByteShop - Shop Owner Orders Page
 * 
 * Displays all orders related to the shop owner's market only
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require shop owner login
require_shop_owner();

$user_id = get_user_id();
$user_name = get_user_name();

// Get shop owner's market ID
try {
    $stmt = $pdo->prepare("SELECT market_id, market_name FROM markets WHERE owner_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $market = $stmt->fetch();
    
    if (!$market) {
        $error_message = "You don't have any active market. Please create a market first.";
        $market_id = null;
    } else {
        $market_id = $market['market_id'];
        $market_name = $market['market_name'];
    }
} catch(PDOException $e) {
    $error_message = "Error fetching market: " . $e->getMessage();
    $market_id = null;
}

// Fetch orders for this market only
$orders = [];
$total_orders = 0;
$total_revenue = 0;

if ($market_id) {
    try {
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        // Build query
        $query = "SELECT DISTINCT 
                    o.order_id,
                    o.total_amount,
                    o.order_status,
                    o.order_date,
                    o.delivery_address,
                    o.payment_method,
                    u.name as customer_name,
                    u.email as customer_email,
                    u.phone as customer_phone,
                    (SELECT SUM(oi.subtotal) 
                     FROM order_items oi 
                     WHERE oi.order_id = o.order_id AND oi.market_id = ?) as market_subtotal,
                    (SELECT COUNT(*) 
                     FROM order_items oi 
                     WHERE oi.order_id = o.order_id AND oi.market_id = ?) as items_count
                  FROM orders o
                  INNER JOIN users u ON o.customer_id = u.user_id
                  INNER JOIN order_items oi ON o.order_id = oi.order_id
                  WHERE oi.market_id = ?";
        
        $params = [$market_id, $market_id, $market_id];
        
        // Add status filter
        if ($status_filter !== 'all') {
            $query .= " AND o.order_status = ?";
            $params[] = $status_filter;
        }
        
        // Add date filters
        if ($date_from) {
            $query .= " AND DATE(o.order_date) >= ?";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $query .= " AND DATE(o.order_date) <= ?";
            $params[] = $date_to;
        }
        
        // Add search filter
        if ($search) {
            $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR o.order_id LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $query .= " ORDER BY o.order_date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Calculate totals
        $total_orders = count($orders);
        foreach ($orders as $order) {
            $total_revenue += $order['market_subtotal'];
        }
        
    } catch(PDOException $e) {
        $error_message = "Error fetching orders: " . $e->getMessage();
    }
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $new_status = isset($_POST['new_status']) ? clean_input($_POST['new_status']) : '';
    
    if ($order_id && $new_status) {
        try {
            // Verify this order belongs to shop owner's market
            $verify_stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM order_items 
                WHERE order_id = ? AND market_id = ?
            ");
            $verify_stmt->execute([$order_id, $market_id]);
            $verify = $verify_stmt->fetch();
            
            if ($verify['count'] > 0) {
                $update_stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $update_stmt->execute([$new_status, $order_id]);
                
                $_SESSION['success_message'] = "Order status updated successfully!";
                header("Location: orders.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Unauthorized access to this order.";
            }
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Error updating order: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ByteShop</title>
  <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            padding: 0px;
            color: #e0e0e0;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            
        }
        
        .header {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 22.5px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 27px;
            border: 1px solid rgba(255, 107, 53, 0.15);
        }
        
        .header h1 {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 28.8px;
            margin-bottom: 9px;
            font-weight: 700;
        }
        
        .header .market-info {
            color: #a0a0a0;
            font-size: 14.4px;
        }
        
        .header .market-info strong {
            color: #ff6b35;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(225px, 1fr));
            gap: 18px;
            margin-bottom: 27px;
        }
        
        .stat-card {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 22.5px;
            border-radius: 13.5px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(255, 107, 53, 0.3);
            border-color: rgba(255, 107, 53, 0.3);
        }
        
        .stat-card h3 {
            color: #a0a0a0;
            font-size: 12.6px;
            text-transform: uppercase;
            margin-bottom: 9px;
            letter-spacing: 1px;
            font-weight: 600;
        }
         .content-box {
        background: rgba(26, 26, 26, 0.6);
        backdrop-filter: blur(10px);
        padding: 27px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        margin-bottom: 0px;
        border: 1px solid rgba(255, 107, 53, 0.15);
    }

    .content-box h2 {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 18px;
        padding-bottom: 9px;
        border-bottom: 2px solid rgba(255, 107, 53, 0.3);
        font-size: 18px;
        font-weight: 700;
    }

    .content-box h1 {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 25.2px;
        font-weight: 700;
        margin-bottom: 9px;
    }

    .content-box p {
        color: #a0a0a0;
        margin-top: 4.5px;
    }
        .stat-card .value {
            font-size: 32.4px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .filters-section {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 22.5px;
            border-radius: 13.5px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 27px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .filters-section h3 {
            margin-bottom: 18px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 18px;
            font-weight: 700;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 13.5px;
            margin-bottom: 18px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 4.5px;
            color: #b0b0b0;
            font-size: 12.6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 9px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 7.2px;
            font-size: 12.6px;
            transition: all 0.3s ease;
            color: #e0e0e0;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #ff6b35;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }
        
        .filter-group select option {
            background: #1a1a1a;
            color: #e0e0e0;
        }
        
        .filter-buttons {
            display: flex;
            gap: 9px;
        }
        
        .btn {
            padding: 10.8px 22.5px;
            border: none;
            border-radius: 7.2px;
            font-size: 12.6px;
            font-weight: 600;
            cursor: pointer;
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
            background: rgba(224, 224, 224, 0.1);
            color: #a0a0a0;
            border: 1px solid rgba(224, 224, 224, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(224, 224, 224, 0.15);
        }
        
        .orders-section {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            padding: 22.5px;
            border-radius: 13.5px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .orders-section h3 {
            margin-bottom: 18px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 18px;
            font-weight: 700;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }
        
        .orders-table table {
            width: 100%;
            min-width: 900px;
        }
        
        .orders-table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 13.5px;
            text-align: left;
            font-weight: 600;
            color: #b0b0b0;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            font-size: 12.6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-table td {
            padding: 13.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 12.6px;
        }
        
        .orders-table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        
        .orders-table td small {
            color: #777;
            font-size: 11.7px;
        }
        
        .status-badge {
            padding: 4.5px 10.8px;
            border-radius: 18px;
            font-size: 10.8px;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid;
        }
        
        .status-placed {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.3);
        }
        
        .status-packed {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .status-shipped {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .status-delivered {
            background: rgba(0, 212, 170, 0.15);
            color: #00d4aa;
            border-color: rgba(0, 212, 170, 0.3);
        }
        
        .status-cancelled {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.3);
        }
        
        .action-btn {
            padding: 5.4px 10.8px;
            margin: 0 2.7px;
            border: none;
            border-radius: 4.5px;
            font-size: 10.8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.5);
        }
        
        .alert {
            padding: 13.5px 18px;
            border-radius: 7.2px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 12.6px;
            border: 1px solid;
        }
        
        .alert-success {
            background: rgba(0, 212, 170, 0.15);
            color: #00d4aa;
            border-color: rgba(0, 212, 170, 0.3);
        }
        
        .alert-error {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.3);
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border-color: rgba(255, 193, 7, 0.3);
        }
        
        .no-orders {
            text-align: center;
            padding: 36px;
            color: #777;
        }
        
        .no-orders i {
            font-size: 57.6px;
            margin-bottom: 18px;
        }
        
        .no-orders div {
            font-size: 57.6px;
            margin-bottom: 18px;
        }
        
        .no-orders h3 {
            color: #ffffff;
            font-size: 18px;
            margin-bottom: 9px;
        }
        
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 18px;
            padding: 9px 18px;
            background: rgba(255, 107, 53, 0.2);
            border-radius: 7.2px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 107, 53, 0.3);
        }
        
        .back-link:hover {
            background: rgba(255, 107, 53, 0.3);
            transform: translateY(-2px);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: rgba(26, 26, 26, 0.95);
            backdrop-filter: blur(10px);
            padding: 27px;
            border-radius: 13.5px;
            max-width: 720px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 107, 53, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 13.5px;
            border-bottom: 2px solid rgba(255, 107, 53, 0.3);
        }
        
        .modal-header h2 {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 21.6px;
            font-weight: 700;
        }
        
        .close-modal {
            font-size: 25.2px;
            cursor: pointer;
            color: #a0a0a0;
            background: none;
            border: none;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            color: #ff6b35;
            transform: rotate(90deg);
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 18px;
        }
        
        .detail-item {
            padding: 13.5px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 7.2px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .detail-item label {
            display: block;
            font-size: 10.8px;
            color: #a0a0a0;
            margin-bottom: 4.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .detail-item .value {
            font-size: 14.4px;
            color: #ffffff;
            font-weight: 600;
        }
        
        .order-items-table {
            width: 100%;
            margin-top: 18px;
        }
        
        .order-items-table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 9px;
            text-align: left;
            font-size: 11.7px;
            color: #b0b0b0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .order-items-table td {
            padding: 9px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
            font-size: 12.6px;
        }
        
        #orderDetailsContent h3 {
            margin-top: 27px;
            margin-bottom: 13.5px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 16.2px;
            font-weight: 700;
        }
        
        #orderDetailsContent form {
            margin-top: 27px;
        }
        
        #orderDetailsContent form > div {
            display: flex;
            gap: 9px;
            align-items: center;
        }
        
        #orderDetailsContent form select {
            flex: 1;
            padding: 9px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 7.2px;
            color: #e0e0e0;
            font-size: 12.6px;
        }
        
        #orderDetailsContent form select option {
            background: #1a1a1a;
            color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/shop_owner_header.php'; ?>
        <div class="content-box">
        <div class="header">
            <h1 style="margin-bottom:79px;">📦 My Orders</h1>
        
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✓ <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                ✗ <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-warning">
                ⚠ <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($market_id): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value">₹<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Order</h3>
                    <div class="value">₹<?php echo $total_orders > 0 ? number_format($total_revenue / $total_orders, 2) : '0.00'; ?></div>
                </div>
            </div>
            </div>
            <div class="filters-section">
                <h3>🔍 Filter Orders</h3>
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Order Status</label>
                            <select name="status">
                                <option value="all" <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="placed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'placed') ? 'selected' : ''; ?>>Placed</option>
                                <option value="packed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'packed') ? 'selected' : ''; ?>>Packed</option>
                                <option value="shipped" <?php echo (isset($_GET['status']) && $_GET['status'] === 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo (isset($_GET['status']) && $_GET['status'] === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>From Date</label>
                            <input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>To Date</label>
                            <input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Order ID, Customer..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="orders.php" class="btn btn-secondary">Clear Filters</a>
                    </div>
                </form>
            </div>
            
            <div class="orders-section">
                <h3>📋 Orders List (<?php echo $total_orders; ?> orders)</h3>
                
                <?php if (count($orders) > 0): ?>
                    <div class="orders-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <small style="color: #999;"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td><?php echo $order['items_count']; ?> items</td>
                                        <td><strong>₹<?php echo number_format($order['market_subtotal'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <button class="action-btn btn-view" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-orders">
                        <div style="font-size: 64px;">📭</div>
                        <h3>No Orders Found</h3>
                        <p>No orders match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="orderDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        function viewOrder(orderId) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderDetailsContent');
            
            modal.classList.add('active');
            content.innerHTML = '<p style="text-align: center; padding: 40px;">Loading...</p>';
            
            fetch(`get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        content.innerHTML = generateOrderHTML(data.order, data.items);
                    } else {
                        content.innerHTML = '<p style="color: red; text-align: center;">Error loading order details</p>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<p style="color: red; text-align: center;">Error loading order details</p>';
                });
        }
        
        function generateOrderHTML(order, items) {
            let itemsHTML = '';
            items.forEach(item => {
                itemsHTML += `
                    <tr>
                        <td>${item.product_name}</td>
                        <td>${item.quantity}</td>
                        <td>₹${parseFloat(item.price).toFixed(2)}</td>
                        <td><strong>₹${parseFloat(item.subtotal).toFixed(2)}</strong></td>
                    </tr>
                `;
            });
            
            return `
                <div class="order-details-grid">
                    <div class="detail-item">
                        <label>Order ID</label>
                        <div class="value">#${order.order_id}</div>
                    </div>
                    <div class="detail-item">
                        <label>Order Date</label>
                        <div class="value">${order.order_date}</div>
                    </div>
                    <div class="detail-item">
                        <label>Customer Name</label>
                        <div class="value">${order.customer_name}</div>
                    </div>
                    <div class="detail-item">
                        <label>Customer Email</label>
                        <div class="value">${order.customer_email}</div>
                    </div>
                    <div class="detail-item">
                        <label>Customer Phone</label>
                        <div class="value">${order.customer_phone || 'N/A'}</div>
                    </div>
                    <div class="detail-item">
                        <label>Payment Method</label>
                        <div class="value">${order.payment_method}</div>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <label>Delivery Address</label>
                        <div class="value">${order.delivery_address}</div>
                    </div>
                    <div class="detail-item">
                        <label>Order Status</label>
                        <div class="value">
                            <span class="status-badge status-${order.order_status}">
                                ${order.order_status.charAt(0).toUpperCase() + order.order_status.slice(1)}
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <label>Total Amount</label>
                        <div class="value" style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 18px;">₹${parseFloat(order.market_subtotal).toFixed(2)}</div>
                    </div>
                </div>
                
                <h3 style="margin-top: 30px; margin-bottom: 15px;">Order Items</h3>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHTML}
                    </tbody>
                </table>
                
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px;">Update Order Status</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="${order.order_id}">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="new_status" style="flex: 1; padding: 10px; border: 2px solid rgba(255, 255, 255, 0.15); border-radius: 8px; background: rgba(255, 255, 255, 0.05); color: #e0e0e0;">
                                <option value="placed" ${order.order_status === 'placed' ? 'selected' : ''}>Placed</option>
                                <option value="packed" ${order.order_status === 'packed' ? 'selected' : ''}>Packed</option>
                                <option value="shipped" ${order.order_status === 'shipped' ? 'selected' : ''}>Shipped</option>
                                <option value="delivered" ${order.order_status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                <option value="cancelled" ${order.order_status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            `;
        }
        
        function closeModal() {
            document.getElementById('orderModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>