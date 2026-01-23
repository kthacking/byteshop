<?php
/**
 * ByteShop - Admin Orders Management
 * View and manage all orders across the system
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
// Require admin access
require_admin();

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $order_id = clean_input($_POST['order_id']);
                $new_status = clean_input($_POST['status']);
                
                $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $stmt->execute([$new_status, $order_id]);
                
                $_SESSION['success'] = "Order status updated successfully!";
                header('Location: orders.php');
                exit;
                break;
        }
    }
}

// Helper Function
if (!function_exists('format_indian_short')) {
    function format_indian_short($num) {
        $num = (float)$num;
        if ($num >= 10000000) return '₹' . round($num / 10000000, 2) . 'Cr';
        if ($num >= 100000) return '₹' . round($num / 100000, 2) . 'L';
        if ($num >= 1000) return '₹' . round($num / 1000, 2) . 'K';
        return '₹' . number_format($num, 0);
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
          FROM orders o
          LEFT JOIN users u ON o.customer_id = u.user_id
          WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR o.order_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN order_status = 'placed' THEN 1 ELSE 0 END) as placed_orders,
    SUM(CASE WHEN order_status = 'packed' THEN 1 ELSE 0 END) as packed_orders,
    SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
    SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
FROM orders";
$stats = $pdo->query($stats_query)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - ByteShop Admin</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        
        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .stat-card h3 { font-size: 0.75rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; position: relative; z-index: 2; }
        .stat-card .number { font-size: 1.8rem; font-weight: 800; position: relative; z-index: 2; }
        .stat-card .icon-overlay { position: absolute; right: -10px; bottom: -10px; font-size: 6rem; opacity: 0.1; transform: rotate(-15deg); }

        .bg-gradient-green { background: linear-gradient(135deg, #059669 0%, #34D399 100%); }
        .bg-gradient-blue { background: linear-gradient(135deg, #2563EB 0%, #60A5FA 100%); }
        .bg-gradient-orange { background: linear-gradient(135deg, #EA580C 0%, #FB923C 100%); }
        .bg-gradient-purple { background: linear-gradient(135deg, #7C3AED 0%, #A78BFA 100%); }
        .bg-gradient-teal { background: linear-gradient(135deg, #0D9488 0%, #2DD4BF 100%); }
        .bg-gradient-red { background: linear-gradient(135deg, #DC2626 0%, #F87171 100%); }

        /* Filters */
        .filters { background: #fff; padding: 1.5rem; border-radius: var(--card-radius); border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .filters form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 150px; }
        .filters label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 600; }
        .filters input, .filters select { width: 100%; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; background: #f9fafb; transition: all 0.2s; }
        .filters input:focus, .filters select:focus { border-color: var(--primary); outline: none; background: #fff; }
        .filters button { padding: 0.6rem 1.5rem; background: #000; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; height: 40px; }
        .filters button:hover { background: var(--primary); }

        /* Order Cards */
        .orders-list { display: flex; flex-direction: column; gap: 1rem; }
        .order-card { background: #fff; border-radius: var(--card-radius); padding: 1.5rem; border: 1px solid var(--border-color); transition: all 0.2s; }
        .order-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        .order-header { display: flex; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; }
        .order-id { font-size: 1.25rem; font-weight: 800; color: #111; }
        .order-date { color: var(--text-gray); font-size: 0.85rem; margin-top: 4px; }
        
        .order-body { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 2rem; }
        .customer-info h4 { font-size: 1rem; margin-bottom: 0.5rem; color: #111; }
        .customer-info p { color: var(--text-gray); font-size: 0.9rem; margin: 0.2rem 0; }
        
        .stat-item { background: #f9fafb; padding: 0.8rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .stat-item .label { font-size: 0.75rem; color: var(--text-gray); font-weight: 600; text-transform: uppercase; }
        .stat-item .value { font-size: 1rem; font-weight: 700; color: #111; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status-badge.placed { background: #DBEAFE; color: #1E40AF; }
        .status-badge.packed { background: #FFEDD5; color: #9A3412; }
        .status-badge.shipped { background: #F3E8FF; color: #6B21A8; }
        .status-badge.delivered { background: #ECFDF5; color: #065F46; }
        .status-badge.cancelled { background: #FEF2F2; color: #991B1B; }

        .btn { padding: 0.6rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; width: 100%; margin-bottom: 0.5rem; }
        .btn-info { background: #E0F2FE; color: #0284C7; }
        .btn-info:hover { background: #BAE6FD; }
        
        select.status-select { width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border-color); background: #f9fafb; font-size: 0.85rem; cursor: pointer; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #fff; width: 95%; max-width: 900px; max-height: 90vh; overflow-y: auto; border-radius: 24px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-close { float: right; font-size: 1.5rem; cursor: pointer; color: #9CA3AF; }
        .modal-close:hover { color: #DC2626; }
        
        /* Modal Internal JS Styles */
        .od-modal-body { font-family: 'Inter', sans-serif; }
        .od-top-bar { display: flex; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; }
        .od-title-group h2 { font-size: 1.5rem; font-weight: 800; color: #111; margin: 0; }
        .od-title-group span { color: #6B7280; font-size: 0.9rem; }
        
        .od-timeline { display: flex; justify-content: space-between; margin: 2rem 0; position: relative; padding: 0 1rem; }
        .od-timeline::before { content: ''; position: absolute; top: 15px; left: 0; right: 0; height: 3px; background: #E5E7EB; z-index: 0; }
        .od-step { position: relative; z-index: 1; text-align: center; width: 25%; }
        .od-step-circle { width: 32px; height: 32px; background: #fff; border: 3px solid #E5E7EB; border-radius: 50%; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #9CA3AF; }
        .od-step.active .od-step-circle { border-color: #111; color: #111; background: #fff; transform: scale(1.1); }
        .od-step.completed .od-step-circle { background: #10B981; border-color: #10B981; color: #fff; }
        .od-step-label { font-size: 0.75rem; font-weight: 700; color: #6B7280; text-transform: uppercase; }
        .od-step.active .od-step-label { color: #111; }
        
        .od-content-wrapper { background: #F9FAFB; border-radius: 12px; border: 1px solid #E5E7EB; margin-top: 1rem; padding: 1.5rem; }
        .od-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; border-bottom: 1px solid #E5E7EB; padding-bottom: 1.5rem; margin-bottom: 1.5rem; }
        .od-subtitle { font-size: 0.75rem; text-transform: uppercase; color: #6B7280; font-weight: 700; margin-bottom: 0.5rem; }
        .od-data-point { font-weight: 500; color: #1F2937; margin-bottom: 0.25rem; }
        
        .od-products-table { width: 100%; border-collapse: collapse; }
        .od-products-table th { text-align: left; padding: 0.75rem; background: #F3F4F6; color: #6B7280; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .od-products-table td { padding: 0.75rem; border-bottom: 1px solid #E5E7EB; background: #fff; }
        .od-thumb { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid #E5E7EB; margin-right: 10px; vertical-align: middle; }
        
        .od-summary-row { display: flex; justify-content: flex-end; padding: 0.5rem; font-size: 0.9rem; }
        .od-summary-row span:first-child { width: 150px; text-align: right; color: #6B7280; margin-right: 1rem; }
        .od-summary-row span:last-child { font-weight: 600; color: #111; }
        .od-summary-total { border-top: 2px solid #E5E7EB; margin-top: 0.5rem; padding-top: 1rem; }
        .od-summary-total span:last-child { font-size: 1.25rem; font-weight: 800; color: #059669; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-danger { background: #FEF2F2; color: #991B1B; border: 1px solid #FCA5A5; }
        .no-orders { text-align: center; padding: 4rem; background: #fff; border-radius: 16px; border: 1px solid var(--border-color); color: var(--text-gray); }
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
            <a href="orders.php" class="active">Orders</a>
            <a href="analytics.php">Reports</a>
        </div>

        <div class="header">
            <h2>Orders Management</h2>
            <p>View and manage all customer orders</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card bg-gradient-orange">
                <div class="icon-overlay">📦</div>
                <h3>Total Orders</h3>
                <div class="number"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card bg-gradient-green">
                <div class="icon-overlay">💰</div>
                <h3>Total Revenue</h3>
                <div class="number"><?php echo format_indian_short($stats['total_revenue'] ?? 0); ?></div>
            </div>
            <div class="stat-card bg-gradient-blue">
                <div class="icon-overlay">📥</div>
                <h3>Placed</h3>
                <div class="number"><?php echo $stats['placed_orders']; ?></div>
            </div>
            <div class="stat-card bg-gradient-orange">
                <div class="icon-overlay">📦</div>
                <h3>Packed</h3>
                <div class="number"><?php echo $stats['packed_orders']; ?></div>
            </div>
            <div class="stat-card bg-gradient-purple">
                <div class="icon-overlay">🚚</div>
                <h3>Shipped</h3>
                <div class="number"><?php echo $stats['shipped_orders']; ?></div>
            </div>
            <div class="stat-card bg-gradient-teal">
                <div class="icon-overlay">✅</div>
                <h3>Delivered</h3>
                <div class="number"><?php echo $stats['delivered_orders']; ?></div>
            </div>
             <div class="stat-card bg-gradient-red">
                <div class="icon-overlay">❌</div>
                <h3>Cancelled</h3>
                <div class="number"><?php echo $stats['cancelled_orders']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Order ID / Customer..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="placed" <?php echo $status_filter === 'placed' ? 'selected' : ''; ?>>Placed</option>
                        <option value="packed" <?php echo $status_filter === 'packed' ? 'selected' : ''; ?>>Packed</option>
                        <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="form-group" style="flex: 0;">
                    <button type="submit">Filter</button>
                </div>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (count($orders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">#<?php echo $order['order_id']; ?></div>
                                <div class="order-date">📅 <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
                            </div>
                            <span class="status-badge <?php echo $order['order_status']; ?>">
                                <?php echo strtoupper($order['order_status']); ?>
                            </span>
                        </div>

                        <div class="order-body">
                            <div class="customer-info">
                                <h4>👤 <?php echo htmlspecialchars($order['customer_name']); ?></h4>
                                <p>📧 <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                <p>📱 <?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
                                <p style="margin-top:0.5rem;"><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                            </div>

                            <div class="order-stats">
                                <div class="stat-item">
                                    <div class="label">Total</div>
                                    <div class="value">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="label">Items</div>
                                    <div class="value"><?php echo $order['item_count']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="label">Payment</div>
                                    <div class="value" style="font-size:0.9rem;"><?php echo $order['payment_method']; ?></div>
                                </div>
                            </div>

                            <div class="order-actions">
                                <button class="btn btn-info" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                    👁 View Details
                                </button>

                                <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <select name="status" class="status-select" onchange="if(confirm('Update order status?')) this.form.submit();">
                                            <option value="">Status Update...</option>
                                            <?php if ($order['order_status'] === 'placed'): ?>
                                                <option value="packed">Mark as Packed</option>
                                            <?php endif; ?>
                                            <?php if ($order['order_status'] === 'packed'): ?>
                                                <option value="shipped">Mark as Shipped</option>
                                            <?php endif; ?>
                                            <?php if ($order['order_status'] === 'shipped'): ?>
                                                <option value="delivered">Mark as Delivered</option>
                                            <?php endif; ?>
                                            <option value="cancelled">Cancel Order</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-orders">
                <h2>No Orders Found</h2>
                <p>Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeOrderModal()">&times;</span>
            <div id="orderDetailsContent"></div>
        </div>
    </div>

    <script>
    function viewOrderDetails(orderId) {
        const modal = document.getElementById('orderModal');
        const contentDiv = document.getElementById('orderDetailsContent');
        modal.classList.add('show');
        contentDiv.innerHTML = '<div style="padding:50px; text-align:center;"><div style="font-size:2rem; animation: spin 1s linear infinite;">⏳</div> Loading...</div>';

        fetch(`../api/get_order_details.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order || data.data.order;
                    const items = data.items || data.data.items;
                    const steps = ['placed', 'packed', 'shipped', 'delivered'];
                    const currentStatus = order.order_status.toLowerCase();
                    let activeIndex = steps.indexOf(currentStatus);
                    if (activeIndex === -1 && currentStatus === 'completed') activeIndex = 3;

                    // Timeline Status Logic
                    let timelineHtml = '<div class="od-timeline">';
                    const labels = ['Placed', 'Packed', 'Shipped', 'Delivered'];
                    
                    if(currentStatus === 'cancelled') {
                         timelineHtml = '<div class="alert alert-danger" style="text-align:center; margin: 1rem 0;">🚫 Order Cancelled</div>';
                    } else {
                        labels.forEach((label, index) => {
                            let className = 'od-step';
                            if (index <= activeIndex) className += ' completed';
                            if (index === activeIndex) className += ' active';
                            timelineHtml += `
                                <div class="${className}">
                                    <div class="od-step-circle">${index + 1}</div>
                                    <div class="od-step-label">${label}</div>
                                </div>
                            `;
                        });
                        timelineHtml += '</div>';
                    }

                    // Calculations
                    const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                    const grandTotal = parseFloat(order.total_amount);
                    const shippingCost = grandTotal > subtotal ? (grandTotal - subtotal) : 0;

                    let html = `
                        <div class="od-modal-body">
                            <div class="od-top-bar">
                                <div class="od-title-group">
                                    <h2>Order #${order.order_id}</h2>
                                    <span>Placed on ${new Date(order.order_date).toLocaleDateString()}</span>
                                </div>
                                <button class="btn btn-info" style="width:auto;" onclick="window.print()">🖨️ Print</button>
                            </div>

                            ${timelineHtml}

                            <div class="od-content-wrapper">
                                <div class="od-details-grid">
                                    <div class="od-box">
                                        <div class="od-subtitle">Customer</div>
                                        <div class="od-data-point"><strong>${order.customer_name}</strong></div>
                                        <div class="od-data-point">${order.customer_email}</div>
                                        <div class="od-data-point">${order.customer_phone || ''}</div>
                                    </div>
                                    <div class="od-box">
                                        <div class="od-subtitle">Shipping To</div>
                                        <div class="od-data-point">${order.delivery_address.replace(/\n/g, '<br>')}</div>
                                        <div style="margin-top:1rem;" class="od-subtitle">Payment</div>
                                        <div class="od-data-point">${order.payment_method}</div>
                                    </div>
                                </div>

                                <table class="od-products-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th style="text-align:right;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                    items.forEach(item => {
                        let img = item.product_image ? (item.product_image.startsWith('http') ? item.product_image : `../uploads/products/${item.product_image}`) : '../assets/images/default-product.jpg';
                        html += `
                            <tr>
                                <td>
                                    <img src="${img}" class="od-thumb" onerror="this.src='../assets/images/default-product.jpg'">
                                    <span style="font-weight:600; font-size: 0.9rem;">${item.product_name}</span>
                                    <div style="font-size:0.8rem; color:#6B7280; margin-left:55px;">Qty: ${item.quantity} x ₹${parseFloat(item.price).toFixed(2)}</div>
                                </td>
                                <td style="text-align:right; font-weight:600;">₹${parseFloat(item.subtotal).toFixed(2)}</td>
                            </tr>
                        `;
                    });

                    html += `
                                    </tbody>
                                </table>

                                <div class="od-summary-total">
                                    <div class="od-summary-row">
                                        <span>Subtotal</span>
                                        <span>₹${subtotal.toFixed(2)}</span>
                                    </div>
                                    <div class="od-summary-row">
                                        <span>Shipping</span>
                                        <span>${shippingCost > 0 ? '₹'+shippingCost.toFixed(2) : 'Free'}</span>
                                    </div>
                                    <div class="od-summary-row" style="font-size:1.2rem; margin-top:0.5rem;">
                                        <span>Grand Total</span>
                                        <span style="color:var(--primary);">₹${grandTotal.toFixed(2)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(err => {
                contentDiv.innerHTML = `<div class="alert alert-danger">Error loading details.</div>`;
                console.error(err);
            });
    }

    function closeOrderModal() { document.getElementById('orderModal').classList.remove('show'); }
    
    // Check if clicked outside modal
    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) closeOrderModal();
    });
    </script>
</body>
</html>