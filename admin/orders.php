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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #7f8c8d; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #2c3e50; }
        .stat-card.blue .number { color: #3498db; }
        .stat-card.green .number { color: #27ae60; }
        .stat-card.orange .number { color: #e67e22; }
        .stat-card.purple .number { color: #9b59b6; }
        .stat-card.red .number { color: #e74c3c; }
        
        /* Filters */
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filters form { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
        .filters .form-group { flex: 1; min-width: 160px; }
        .filters label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; font-size: 14px; }
        .filters select, .filters input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filters button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .filters button:hover { background: #2980b9; }
        
        /* Orders List */
        .orders-list { display: flex; flex-direction: column; gap: 15px; }
        
        .order-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.3s; }
        .order-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #ecf0f1; }
        .order-id { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .order-date { color: #7f8c8d; font-size: 14px; }
        
        .order-body { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        
        .customer-info h4 { color: #2c3e50; margin-bottom: 8px; font-size: 16px; }
        .customer-info p { color: #7f8c8d; font-size: 14px; margin: 5px 0; }
        
        .order-stats { display: flex; flex-direction: column; gap: 10px; }
        .stat-item { background: #ecf0f1; padding: 10px; border-radius: 5px; text-align: center; }
        .stat-item .label { font-size: 12px; color: #7f8c8d; }
        .stat-item .value { font-size: 20px; font-weight: bold; color: #2c3e50; }
        
        .order-actions { display: flex; flex-direction: column; gap: 10px; }
        
        /* Status Badge */
        .status-badge { padding: 8px 15px; border-radius: 20px; font-size: 13px; font-weight: 500; display: inline-block; }
        .status-badge.placed { background: #3498db; color: white; }
        .status-badge.packed { background: #f39c12; color: white; }
        .status-badge.shipped { background: #9b59b6; color: white; }
        .status-badge.delivered { background: #27ae60; color: white; }
        .status-badge.cancelled { background: #e74c3c; color: white; }
        
        /* Buttons */
        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-align: center; width: 100%; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.85; }
        
        /* Status Dropdown */
        select.status-select { padding: 8px; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 20px; color: #2c3e50; }
        .modal-close { float: right; font-size: 28px; cursor: pointer; color: #7f8c8d; }
        .modal-close:hover { color: #e74c3c; }
        
        .order-item { padding: 15px; background: #f8f9fa; border-radius: 5px; margin: 10px 0; }
        .order-item h4 { color: #2c3e50; margin-bottom: 5px; }
        .order-item p { color: #7f8c8d; font-size: 14px; margin: 3px 0; }
        
        /* Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .no-orders { text-align: center; padding: 60px 20px; color: #95a5a6; font-size: 18px; background: white; border-radius: 10px; }
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
                        <input type="text" name="search" placeholder="Order ID / Customer" value="<?php echo htmlspecialchars($search); ?>">
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
                                    <p style="margin-top: 10px;"><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
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
                                        <div class="value" style="font-size: 14px;"><?php echo $order['payment_method']; ?></div>
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
                                            <select name="status" class="status-select" onchange="if(confirm('Update order status?')) this.form.submit();">
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

    <script>
        function viewOrderDetails(orderId) {
            // Show modal
            document.getElementById('orderModal').classList.add('show');
            document.getElementById('orderDetailsContent').innerHTML = '<p style="text-align:center; padding:20px;">Loading...</p>';
            
            // Fetch order details
            fetch(`../api/get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Use either data.order or data.data.order (for backward compatibility)
                        const order = data.order || data.data.order;
                        const items = data.items || data.data.items;
                        
                        let html = '<div style="margin-bottom: 20px;">';
                        html += `<h4 style="margin-bottom: 10px;">Order #${order.order_id}</h4>`;
                        html += `<p><strong>Customer:</strong> ${order.customer_name}</p>`;
                        html += `<p><strong>Email:</strong> ${order.customer_email}</p>`;
                        html += `<p><strong>Phone:</strong> ${order.customer_phone || 'N/A'}</p>`;
                        html += `<p><strong>Date:</strong> ${new Date(order.order_date).toLocaleString()}</p>`;
                        html += `<p><strong>Status:</strong> <span class="status-badge ${order.order_status}">${order.order_status.toUpperCase()}</span></p>`;
                        html += `<p><strong>Payment:</strong> ${order.payment_method}</p>`;
                        html += `<p><strong>Delivery Address:</strong><br>${order.delivery_address.replace(/\n/g, '<br>')}</p>`;
                        html += `<p style="margin-top:10px;"><strong>Total:</strong> <span style="font-size:20px; color:#27ae60;">₹${parseFloat(order.total_amount).toFixed(2)}</span></p>`;
                        html += '</div>';
                        
                        html += '<h4 style="margin: 20px 0 10px 0; border-top: 2px solid #ecf0f1; padding-top: 20px;">Order Items:</h4>';
                        
                        items.forEach(item => {
                            // Detect if image is URL or local file
                            let imageSrc = '../assets/images/default-product.jpg';
                            if (item.product_image) {
                                if (item.product_image.startsWith('http://') || item.product_image.startsWith('https://')) {
                                    imageSrc = item.product_image;
                                } else {
                                    imageSrc = '../uploads/products/' + item.product_image;
                                }
                            }
                            
                            html += '<div class="order-item" style="display: flex; gap: 15px; align-items: center;">';
                            html += `<img src="${imageSrc}" alt="${item.product_name}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;" onerror="this.src='../assets/images/default-product.jpg'">`;
                            html += '<div style="flex: 1;">';
                            html += `<h4 style="margin-bottom: 5px;">${item.product_name}</h4>`;
                            html += `<p style="color: #7f8c8d; font-size: 13px;">🏪 ${item.market_name}</p>`;
                            html += `<p style="color: #7f8c8d; font-size: 13px;">🏷️ ${item.category}</p>`;
                            html += `<p style="margin-top: 5px;"><strong>Qty:</strong> ${item.quantity} × ₹${parseFloat(item.price).toFixed(2)} = <strong>₹${parseFloat(item.subtotal).toFixed(2)}</strong></p>`;
                            html += '</div>';
                            html += '</div>';
                        });
                        
                        document.getElementById('orderDetailsContent').innerHTML = html;
                    } else {
                        document.getElementById('orderDetailsContent').innerHTML = `<p style="color: #e74c3c; text-align: center; padding: 20px;">${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetailsContent').innerHTML = '<p style="color: #e74c3c; text-align: center; padding: 20px;">Error loading order details. Please try again.</p>';
                });
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