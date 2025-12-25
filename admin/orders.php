<?php
/**
 * ByteShop - Admin Orders Management
 * View and manage all orders across the system
 */

require_once '../config/db.php';
require_once '../includes/session.php';

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
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f5f5;
    }

    .container {
        display: flex;
        min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
        width: 250px;
        background: #2c3e50;
        color: white;
        padding: 20px;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
    }

    .sidebar h2 {
        margin-bottom: 30px;
        color: #3498db;
    }

    .sidebar ul {
        list-style: none;
    }

    .sidebar ul li {
        margin: 15px 0;
    }

    .sidebar ul li a {
        color: white;
        text-decoration: none;
        display: block;
        padding: 10px;
        border-radius: 5px;
        transition: 0.3s;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
        background: #34495e;
    }

    /* Main Content */
    .main-content {
        flex: 1;
        padding: 30px;
    }

    .header {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .header h1 {
        color: #2c3e50;
        margin-bottom: 10px;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
        color: #7f8c8d;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .stat-card .number {
        font-size: 28px;
        font-weight: bold;
        color: #2c3e50;
    }

    .stat-card.blue .number {
        color: #3498db;
    }

    .stat-card.green .number {
        color: #27ae60;
    }

    .stat-card.orange .number {
        color: #e67e22;
    }

    .stat-card.purple .number {
        color: #9b59b6;
    }

    .stat-card.red .number {
        color: #e74c3c;
    }

    /* Filters */
    .filters {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .filters form {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: end;
    }

    .filters .form-group {
        flex: 1;
        min-width: 160px;
    }

    .filters label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-weight: 500;
        font-size: 14px;
    }

    .filters select,
    .filters input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .filters button {
        padding: 10px 20px;
        background: #3498db;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .filters button:hover {
        background: #2980b9;
    }

    /* Orders List */
    .orders-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .order-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: 0.3s;
    }

    .order-card:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #ecf0f1;
    }

    .order-id {
        font-size: 18px;
        font-weight: bold;
        color: #2c3e50;
    }

    .order-date {
        color: #7f8c8d;
        font-size: 14px;
    }

    .order-body {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }

    .customer-info h4 {
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 16px;
    }

    .customer-info p {
        color: #7f8c8d;
        font-size: 14px;
        margin: 5px 0;
    }

    .order-stats {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .stat-item {
        background: #ecf0f1;
        padding: 10px;
        border-radius: 5px;
        text-align: center;
    }

    .stat-item .label {
        font-size: 12px;
        color: #7f8c8d;
    }

    .stat-item .value {
        font-size: 20px;
        font-weight: bold;
        color: #2c3e50;
    }

    .order-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* Status Badge */
    .status-badge {
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.placed {
        background: #3498db;
        color: white;
    }

    .status-badge.packed {
        background: #f39c12;
        color: white;
    }

    .status-badge.shipped {
        background: #9b59b6;
        color: white;
    }

    .status-badge.delivered {
        background: #27ae60;
        color: white;
    }

    .status-badge.cancelled {
        background: #e74c3c;
        color: white;
    }

    /* Buttons */
    .btn {
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        text-align: center;
        width: 100%;
    }

    .btn-primary {
        background: #3498db;
        color: white;
    }

    .btn-success {
        background: #27ae60;
        color: white;
    }

    .btn-info {
        background: #17a2b8;
        color: white;
    }

    .btn:hover {
        opacity: 0.85;
    }

    /* Status Dropdown */
    select.status-select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 5px;
        cursor: pointer;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 1000;
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-content h3 {
        margin-bottom: 20px;
        color: #2c3e50;
    }

    .modal-close {
        float: right;
        font-size: 28px;
        cursor: pointer;
        color: #7f8c8d;
    }

    .modal-close:hover {
        color: #e74c3c;
    }

    .order-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
        margin: 10px 0;
    }

    .order-item h4 {
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .order-item p {
        color: #7f8c8d;
        font-size: 14px;
        margin: 3px 0;
    }

    /* Alerts */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .no-orders {
        text-align: center;
        padding: 60px 20px;
        color: #95a5a6;
        font-size: 18px;
        background: white;
        border-radius: 10px;
    }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>ByteShop Admin</h2>
            <ul>
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="users.php">👥 Users</a></li>
                <li><a href="markets.php">🏪 Markets</a></li>
                <li><a href="products.php">📦 Products</a></li>
                <li><a href="orders.php" class="active">🛒 Orders</a></li>
                <li><a href="analytics.php">📈 Analytics</a></li>
                <li><a href="../logout.php">🚪 Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Orders Management</h1>
                <p>View and manage all customer orders</p>
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
                    <h3>Total Orders</h3>
                    <div class="number"><?php echo $stats['total_orders']; ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Total Revenue</h3>
                    <div class="number">₹<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>Placed</h3>
                    <div class="number"><?php echo $stats['placed_orders']; ?></div>
                </div>
                <div class="stat-card purple">
                    <h3>Shipped</h3>
                    <div class="number"><?php echo $stats['shipped_orders']; ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Delivered</h3>
                    <div class="number"><?php echo $stats['delivered_orders']; ?></div>
                </div>
                <div class="stat-card red">
                    <h3>Cancelled</h3>
                    <div class="number"><?php echo $stats['cancelled_orders']; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Order ID / Customer"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="placed" <?php echo $status_filter === 'placed' ? 'selected' : ''; ?>>Placed
                            </option>
                            <option value="packed" <?php echo $status_filter === 'packed' ? 'selected' : ''; ?>>Packed
                            </option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>
                                Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>
                                Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>
                                Cancelled</option>
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
                    <div class="form-group">
                        <label>&nbsp;</label>
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
                            <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                            <div class="order-date">📅
                                <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
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
                            <p style="margin-top: 10px;">
                                <strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                            </p>
                        </div>

                        <div class="order-stats">
                            <div class="stat-item">
                                <div class="label">Total Amount</div>
                                <div class="value">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="label">Items</div>
                                <div class="value"><?php echo $order['item_count']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="label">Payment</div>
                                <div class="value" style="font-size: 14px;"><?php echo $order['payment_method']; ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-actions">
                            <button class="btn btn-info" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                👁 View Details
                            </button>

                            <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <select name="status" class="status-select"
                                    onchange="if(confirm('Update order status?')) this.form.submit();">
                                    <option value="">Change Status</option>
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
                <p>Try adjusting your filters or search terms</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeOrderModal()">&times;</span>
            <h3>Order Details</h3>
            <div id="orderDetailsContent">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- <script>
    function viewOrderDetails(orderId) {
        const modal = document.getElementById('orderModal');
        const contentDiv = document.getElementById('orderDetailsContent');

        modal.classList.add('show');
        contentDiv.innerHTML =
            '<div style="padding:50px; text-align:center;"><div class="spinner-border text-primary"></div></div>';

        fetch(`../api/get_order_details.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order || data.data.order;
                    const items = data.items || data.data.items;

                    // --- Logic for Timeline ---
                    // Define the order of your statuses
                    const steps = ['pending', 'processing', 'shipped', 'delivered'];
                    const currentStatus = order.order_status.toLowerCase();
                    // Find index (if cancelled, we might just show text, but assuming standard flow here)
                    let activeIndex = steps.indexOf(currentStatus);
                    if (activeIndex === -1 && currentStatus === 'completed') activeIndex =
                    3; // map completed to delivered

                    // Helper to generate timeline HTML
                    const renderTimeline = () => {
                        if (currentStatus === 'cancelled') {
                            return `<div class="alert alert-danger" style="margin:20px 0; text-align:center;">🚫 This order has been Cancelled</div>`;
                        }

                        let timelineHtml = '<div class="od-timeline">';
                        const labels = ['Order Placed', 'Processing', 'Out for Delivery', 'Delivered'];

                        steps.forEach((step, index) => {
                            let className = 'od-step';
                            if (index < activeIndex) className += ' completed';
                            if (index === activeIndex) className += ' active';

                            timelineHtml += `
                            <div class="${className}">
                                <div class="od-step-circle">${index + 1}</div>
                                <div class="od-step-label">${labels[index]}</div>
                            </div>`;
                        });
                        timelineHtml += '</div>';
                        return timelineHtml;
                    };

                    // Calculations
                    const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                    const grandTotal = parseFloat(order.total_amount);
                    // Assuming tax/shipping are calculated or part of the total. 
                    // If you don't have these specifically in DB, we can estimate or hide them.
                    const shippingCost = grandTotal > subtotal ? (grandTotal - subtotal) : 0;

                    let html = `
                <div class="od-modal-body">
                    <div class="od-top-bar">
                        <div class="od-title-group">
                            <h2>Order #${order.order_id}</h2>
                            <span>Placed on ${new Date(order.order_date).toLocaleDateString()} at ${new Date(order.order_date).toLocaleTimeString()}</span>
                        </div>
                        <div class="od-actions">
                            <button class="btn-action" onclick="window.print()">🖨️ Print</button>
                            <button class="btn-action">⬇️ Invoice</button>
                        </div>
                    </div>

                    ${renderTimeline()}

                    <div class="od-content-wrapper">
                        <div class="od-details-grid">
                            <div class="od-box">
                                <div class="od-subtitle">Customer Details</div>
                                <div class="od-data-point"><strong>${order.customer_name}</strong></div>
                                <div class="od-data-point">📧 ${order.customer_email}</div>
                                <div class="od-data-point">📞 ${order.customer_phone || '--'}</div>
                            </div>
                            <div class="od-box">
                                <div class="od-subtitle">Shipping To</div>
                                <div class="od-data-point">📍 ${order.delivery_address.replace(/\n/g, '<br>')}</div>
                                <div style="margin-top:15px;" class="od-subtitle">Payment Method</div>
                                <div class="od-data-point">💳 ${order.payment_method}</div>
                            </div>
                        </div>

                        <table class="od-products-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th style="text-align:right;">Cost</th>
                                </tr>
                            </thead>
                            <tbody>`;

                    items.forEach(item => {
                        let img = item.product_image ?
                            (item.product_image.startsWith('http') ? item.product_image :
                                `../uploads/products/${item.product_image}`) :
                            '../assets/images/default-product.jpg';

                        html += `
                        <tr>
                            <td>
                                <div class="od-product-flex">
                                    <img src="${img}" class="od-thumb" onerror="this.src='../assets/images/default-product.jpg'">
                                    <div>
                                        <div style="font-weight:600; color:#2c3e50;">${item.product_name}</div>
                                        <div style="font-size:0.8rem; color:#999;">Seller: ${item.market_name}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:#666;">${item.category}</td>
                            <td style="text-align:right;">
                                <div style="color:#2c3e50; font-weight:600;">₹${parseFloat(item.subtotal).toFixed(2)}</div>
                                <div style="font-size:0.75rem; color:#999;">${item.quantity} x ₹${parseFloat(item.price).toFixed(2)}</div>
                            </td>
                        </tr>`;
                    });

                    html += `
                            </tbody>
                        </table>

                        <div style="padding: 20px 0;">
                            <div class="od-summary-row">
                                <span>Subtotal</span>
                                <span>₹${subtotal.toFixed(2)}</span>
                            </div>
                            <div class="od-summary-row">
                                <span>Shipping & Handling</span>
                                <span>${shippingCost > 0 ? '₹'+shippingCost.toFixed(2) : 'Free'}</span>
                            </div>
                            <div class="od-summary-row od-summary-total">
                                <span>Grand Total</span>
                                <span>₹${grandTotal.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                </div>`;

                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<div class="alert alert-danger m-4">${data.message}</div>`;
                }
            })
            .catch(err => console.error(err));
    }


    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('show');
    }

    // Close modal when clicking outside
    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });
</script> -->
<style>
    /* Animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .od-modal-body {
        font-family: 'Inter', -apple-system, sans-serif;
        /* Modern font stack */
        background-color: #f4f6f8;
        color: #455a64;
        padding: 25px;
        animation: fadeIn 0.3s ease-out;
    }

    /* Header & Actions */
    .od-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .od-title-group h2 {
        margin: 0;
        font-size: 1.6rem;
        color: #1a1a1a;
        font-weight: 700;
    }

    .od-title-group span {
        font-size: 0.9rem;
        color: #9aa5b1;
    }

    .od-actions .btn-action {
        background: white;
        border: 1px solid #dfe3e8;
        padding: 8px 15px;
        border-radius: 6px;
        color: #455a64;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s;
        margin-left: 8px;
    }

    .od-actions .btn-action:hover {
        background: #f9fafb;
        border-color: #c4cdd5;
    }

    /* Status Timeline (Stepper) */
    .od-timeline {
        display: flex;
        justify-content: space-between;
        margin: 30px 0 40px 0;
        position: relative;
        padding: 0 20px;
    }

    .od-timeline::before {
        content: '';
        position: absolute;
        top: 14px;
        left: 40px;
        right: 40px;
        height: 3px;
        background: #dfe3e8;
        z-index: 0;
    }

    .od-step {
        position: relative;
        z-index: 1;
        text-align: center;
        width: 25%;
    }

    .od-step-circle {
        width: 32px;
        height: 32px;
        background: #dfe3e8;
        border-radius: 50%;
        margin: 0 auto 8px auto;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
        transition: background 0.3s;
    }

    .od-step-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #9aa5b1;
        text-transform: uppercase;
    }

    /* Active State for Timeline */
    .od-step.active .od-step-circle {
        background: #27ae60;
        box-shadow: 0 0 0 4px rgba(39, 174, 96, 0.2);
    }

    .od-step.active .od-step-label {
        color: #27ae60;
    }

    /* Completed State (Past steps) */
    .od-step.completed .od-step-circle {
        background: #27ae60;
    }

    /* Content Cards */
    .od-content-wrapper {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .od-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-bottom: 1px solid #eee;
    }

    .od-box {
        padding: 25px;
    }

    .od-box:first-child {
        border-right: 1px solid #eee;
    }

    .od-subtitle {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #9aa5b1;
        font-weight: 700;
        margin-bottom: 15px;
        letter-spacing: 0.5px;
    }

    .od-data-point {
        margin-bottom: 8px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 0.95rem;
    }

    /* Product List Table Style */
    .od-products-table {
        width: 100%;
        border-collapse: collapse;
    }

    .od-products-table th {
        text-align: left;
        padding: 15px 25px;
        background: #f9fafb;
        color: #637381;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .od-products-table td {
        padding: 20px 25px;
        border-bottom: 1px solid #f4f6f8;
        vertical-align: middle;
    }

    .od-product-flex {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .od-thumb {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        object-fit: cover;
        border: 1px solid #eee;
    }

    /* Summary Footer */
    .od-summary-row {
        display: flex;
        justify-content: flex-end;
        padding: 10px 25px;
    }

    .od-summary-row span:first-child {
        width: 150px;
        text-align: right;
        color: #637381;
        margin-right: 20px;
    }

    .od-summary-row span:last-child {
        width: 100px;
        text-align: right;
        font-weight: 600;
        color: #212b36;
    }

    .od-summary-total {
        background: #f9fafb;
        padding: 20px 25px;
        margin-top: 10px;
        border-top: 1px solid #eee;
    }

    .od-summary-total span:last-child {
        color: #27ae60;
        font-size: 1.2rem;
        font-weight: 800;
    }
</style>

<script>
    function viewOrderDetails(orderId) {
        const modal = document.getElementById('orderModal');
        const contentDiv = document.getElementById('orderDetailsContent');

        modal.classList.add('show');
        contentDiv.innerHTML =
            '<div style="padding:50px; text-align:center;"><div class="spinner-border text-primary"></div></div>';

        fetch(`../api/get_order_details.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order || data.data.order;
                    const items = data.items || data.data.items;

                    // --- Logic for Timeline ---
                    const steps = ['pending', 'processing', 'shipped', 'delivered'];
                    const currentStatus = order.order_status.toLowerCase();
                    let activeIndex = steps.indexOf(currentStatus);
                    if (activeIndex === -1 && currentStatus === 'completed') activeIndex = 3;

                    const renderTimeline = () => {
                        if (currentStatus === 'cancelled') {
                            return `<div class="alert alert-danger" style="margin:20px 0; text-align:center;">🚫 This order has been Cancelled</div>`;
                        }

                        let timelineHtml = '<div class="od-timeline">';
                        const labels = ['Order Placed', 'Processing', 'Out for Delivery', 'Delivered'];

                        steps.forEach((step, index) => {
                            let className = 'od-step';
                            if (index < activeIndex) className += ' completed';
                            if (index === activeIndex) className += ' active';

                            timelineHtml += `
                        <div class="${className}">
                            <div class="od-step-circle">${index + 1}</div>
                            <div class="od-step-label">${labels[index]}</div>
                        </div>`;
                        });
                        timelineHtml += '</div>';
                        return timelineHtml;
                    };

                    const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                    const grandTotal = parseFloat(order.total_amount);
                    const shippingCost = grandTotal > subtotal ? (grandTotal - subtotal) : 0;

                    let html = `
            <div class="od-modal-body">
                <div class="od-top-bar">
                    <div class="od-title-group">
                        <h2>Order #${order.order_id}</h2>
                        <span>Placed on ${new Date(order.order_date).toLocaleDateString()} at ${new Date(order.order_date).toLocaleTimeString()}</span>
                    </div>
                    <div class="od-actions">
                        <button class="btn-action" onclick="printOrderDetails()">🖨️ Print</button>
                        <button class="btn-action">⬇️ Invoice</button>
                    </div>
                </div>

                ${renderTimeline()}

                <div class="od-content-wrapper">
                    <div class="od-details-grid">
                        <div class="od-box">
                            <div class="od-subtitle">Customer Details</div>
                            <div class="od-data-point"><strong>${order.customer_name}</strong></div>
                            <div class="od-data-point">📧 ${order.customer_email}</div>
                            <div class="od-data-point">📞 ${order.customer_phone || '--'}</div>
                        </div>
                        <div class="od-box">
                            <div class="od-subtitle">Shipping To</div>
                            <div class="od-data-point">📍 ${order.delivery_address.replace(/\n/g, '<br>')}</div>
                            <div style="margin-top:15px;" class="od-subtitle">Payment Method</div>
                            <div class="od-data-point">💳 ${order.payment_method}</div>
                        </div>
                    </div>

                    <table class="od-products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th style="text-align:right;">Cost</th>
                            </tr>
                        </thead>
                        <tbody>`;

                    items.forEach(item => {
                        let img = item.product_image ?
                            (item.product_image.startsWith('http') ? item.product_image :
                                `../uploads/products/${item.product_image}`) :
                            '../assets/images/default-product.jpg';

                        html += `
                    <tr>
                        <td>
                            <div class="od-product-flex">
                                <img src="${img}" class="od-thumb" onerror="this.src='../assets/images/default-product.jpg'">
                                <div>
                                    <div style="font-weight:600; color:#2c3e50;">${item.product_name}</div>
                                    <div style="font-size:0.8rem; color:#999;">Seller: ${item.market_name}</div>
                                </div>
                            </div>
                        </td>
                        <td style="color:#666;">${item.category}</td>
                        <td style="text-align:right;">
                            <div style="color:#2c3e50; font-weight:600;">₹${parseFloat(item.subtotal).toFixed(2)}</div>
                            <div style="font-size:0.75rem; color:#999;">${item.quantity} x ₹${parseFloat(item.price).toFixed(2)}</div>
                        </td>
                    </tr>`;
                    });

                    html += `
                        </tbody>
                    </table>

                    <div style="padding: 20px 0;">
                        <div class="od-summary-row">
                            <span>Subtotal</span>
                            <span>₹${subtotal.toFixed(2)}</span>
                        </div>
                        <div class="od-summary-row">
                            <span>Shipping & Handling</span>
                            <span>${shippingCost > 0 ? '₹'+shippingCost.toFixed(2) : 'Free'}</span>
                        </div>
                        <div class="od-summary-row od-summary-total">
                            <span>Grand Total</span>
                            <span>₹${grandTotal.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>`;

                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<div class="alert alert-danger m-4">${data.message}</div>`;
                }
            })
            .catch(err => console.error(err));
    }


    // NEW FUNCTION: Print only the modal content
    function printOrderDetails() {
        const printContent = document.getElementById('orderDetailsContent').innerHTML;
        const originalContent = document.body.innerHTML;

        // Create a temporary wrapper with print styles
        document.body.innerHTML = `
        <html>
            <head>
                <title>Print Order</title>
                <style>
                    @media print {
                        @page {
                            size: A4;
                            margin: 15mm;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            font-family: Arial, sans-serif;
                        }
                        .od-actions {
                            display: none !important;
                        }
                        .od-modal-body {
                            box-shadow: none !important;
                        }
                    }
                    ${getOrderModalStyles()}
                </style>
            </head>
            <body>
                ${printContent}
            </body>
        </html>
    `;

        window.print();

        // Restore original content
        document.body.innerHTML = originalContent;

        // Re-attach event listeners after restoring content
        window.location.reload();
    }

    // Helper function to include existing styles
    function getOrderModalStyles() {
        return `
        .od-modal-body { max-width: 900px; margin: 0 auto; }
        .od-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e0e0e0; }
        .od-title-group h2 { margin: 0; color: #2c3e50; font-size: 1.8rem; }
        .od-title-group span { color: #7f8c8d; font-size: 0.9rem; }
        .od-actions { display: flex; gap: 10px; }
        .btn-action { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 5px; cursor: pointer; font-size: 0.9rem; }
        .od-timeline { display: flex; justify-content: space-between; margin: 30px 0; position: relative; }
        .od-timeline::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 2px; background: #e0e0e0; z-index: 0; }
        .od-step { flex: 1; text-align: center; position: relative; z-index: 1; }
        .od-step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold; transition: all 0.3s; }
        .od-step.completed .od-step-circle { background: #27ae60; color: white; }
        .od-step.active .od-step-circle { background: #3498db; color: white; box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2); }
        .od-step-label { font-size: 0.85rem; color: #7f8c8d; font-weight: 500; }
        .od-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        .od-box { background: #f8f9fa; padding: 20px; border-radius: 8px; }
        .od-subtitle { font-weight: 700; color: #2c3e50; margin-bottom: 12px; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .od-data-point { margin: 8px 0; color: #555; line-height: 1.6; }
        .od-products-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .od-products-table thead { background: #34495e; color: white; }
        .od-products-table th, .od-products-table td { padding: 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .od-product-flex { display: flex; align-items: center; gap: 15px; }
        .od-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .od-summary-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #ecf0f1; font-size: 0.95rem; }
        .od-summary-total { font-weight: 700; font-size: 1.2rem; color: #2c3e50; border-top: 2px solid #34495e; margin-top: 10px; padding-top: 15px; }
    `;
    }


    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('show');
    }

    // Close modal when clicking outside
    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });
</script>

</body>

</html>