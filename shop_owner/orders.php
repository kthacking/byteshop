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
    <title>My Orders - MarketX</title>
    <style>
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

        /* Container & Nav Pill */
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        
        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
            min-height: 140px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .stat-card h3 { font-size: 0.8rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; position: relative; z-index: 2; }
        .stat-card .value { font-size: 2rem; font-weight: 800; position: relative; z-index: 2; letter-spacing: -1px; }
        .stat-card .icon-overlay { position: absolute; right: -10px; bottom: -10px; font-size: 6rem; opacity: 0.1; transform: rotate(-15deg); }

        .bg-gradient-blue { background: linear-gradient(135deg, #2563EB 0%, #60A5FA 100%); }
        .bg-gradient-orange { background: linear-gradient(135deg, #EA580C 0%, #FB923C 100%); }
        .bg-gradient-purple { background: linear-gradient(135deg, #7C3AED 0%, #A78BFA 100%); }

        /* Filters */
        .card { background: #fff; border-radius: var(--card-radius); padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-bottom: 2rem; }
        .card-header { font-size: 1.1rem; font-weight: 700; color: #111; margin-bottom: 1.5rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; }

        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-gray); }
        .filter-group input, .filter-group select {
            padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.9rem;
            transition: all 0.2s;
        }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255, 75, 43, 0.1); }

        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #111; color: #fff; }
        .btn-primary:hover { background: var(--primary); transform: translateY(-2px); }
        .btn-secondary { background: #f3f4f6; color: var(--text-dark); margin-left: 0.5rem; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; background: #F9FAFB; color: var(--text-gray); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #E5E7EB; }
        td { padding: 1rem; border-bottom: 1px solid #E5E7EB; font-size: 0.9rem; color: #1F2937; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #F9FAFB; }

        .status { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status.placed { background: #EFF6FF; color: #1D4ED8; }
        .status.packed { background: #FFF7ED; color: #C2410C; }
        .status.shipped { background: #F3E8FF; color: #7C3AED; }
        .status.delivered { background: #ECFDF5; color: #059669; }
        .status.cancelled { background: #FEF2F2; color: #DC2626; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .alert-warning { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal.active { display: flex; }
        .modal-content { background: #fff; border-radius: 16px; padding: 2rem; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f3f4f6; }
        .modal-header h2 { font-size: 1.5rem; font-weight: 800; color: #111; }
        .close-modal { background: none; border: none; font-size: 2rem; cursor: pointer; color: var(--text-gray); }
        .close-modal:hover { color: var(--primary); }

        .order-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .detail-item label { display: block; font-size: 0.75rem; color: var(--text-gray); text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem; }
        .detail-item .value { font-size: 1rem; color: #111; font-weight: 500; }

        .action-select { padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 6px; flex: 1; margin-right: 10px; }

    </style>
</head>
<body>
    <nav class="navbar">
        <h1>🛒 Market<span>X</span> Owner</h1>
        <div class="user-info">
            <span>👋 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="logout-btn">Log Output</a>
        </div>
    </nav>

    <div class="container">
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="my_market.php">My Market</a>
            <a href="products.php">Products</a>
            <a href="orders.php" class="active">Orders</a>
        </div>

        <div class="header">
            <h2>Order Management</h2>
            <p>Track and manage your customer orders.</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                ✅ <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                ❌ <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-warning">
                ⚠️ <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($market_id): ?>
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card bg-gradient-orange">
                    <div class="icon-overlay">🛍️</div>
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-card bg-gradient-blue">
                    <div class="icon-overlay">💰</div>
                    <h3>Total Revenue</h3>
                    <div class="value">₹<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card bg-gradient-purple">
                    <div class="icon-overlay">📊</div>
                    <h3>Avg Order Value</h3>
                    <div class="value">₹<?php echo $total_orders > 0 ? number_format($total_revenue / $total_orders, 2) : '0.00'; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">Filter Orders</div>
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Status</label>
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
                            <label>From</label>
                            <input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                        </div>
                        <div class="filter-group">
                            <label>To</label>
                            <input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                        </div>
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Order ID, Client..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="orders.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">Orders List (<?php echo $total_orders; ?>)</div>
                <?php if (count($orders) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                        <td>
                                            <div style="font-weight:600;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div style="font-size:0.8rem; color:#6B7280;"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                        </td>
                                        <td><?php echo $order['items_count']; ?></td>
                                        <td>₹<?php echo number_format($order['market_subtotal'], 2); ?></td>
                                        <td>
                                            <span class="status <?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M, h:i A', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="viewOrder(<?php echo $order['order_id']; ?>)">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:3rem; color:#6B7280;">
                        <h3 style="margin-bottom:0.5rem;">No orders found</h3>
                        <p>Try adjusting your search or filters.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <!-- Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            <div id="orderDetailsContent">
                <!-- Content via JS -->
            </div>
        </div>
    </div>

    <script>
        function viewOrder(orderId) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderDetailsContent');
            
            modal.classList.add('active');
            content.innerHTML = '<div style="text-align:center; padding:2rem;">Loading details...</div>';
            
            fetch(`get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        content.innerHTML = generateOrderHTML(data.order, data.items);
                    } else {
                        content.innerHTML = '<div class="alert alert-error">Error loading order details</div>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div class="alert alert-error">Connection error</div>';
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
            
            // Build status options for select
            const statuses = ['placed', 'packed', 'shipped', 'delivered', 'cancelled'];
            let optionsHTML = '';
            statuses.forEach(s => {
                const selected = order.order_status === s ? 'selected' : '';
                const label = s.charAt(0).toUpperCase() + s.slice(1);
                optionsHTML += `<option value="${s}" ${selected}>${label}</option>`;
            });

            return `
                <div class="order-details-grid">
                    <div class="detail-item">
                        <label>Order ID</label>
                        <div class="value">#${order.order_id}</div>
                    </div>
                    <div class="detail-item">
                        <label>Date</label>
                        <div class="value">${order.order_date}</div>
                    </div>
                    <div class="detail-item">
                        <label>Customer</label>
                        <div class="value">${order.customer_name}</div>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <div class="value">${order.customer_email}</div>
                    </div>
                    <div class="detail-item">
                        <label>Address</label>
                        <div class="value">${order.delivery_address}</div>
                    </div>
                    <div class="detail-item">
                        <label>Total Amount</label>
                        <div class="value" style="color:var(--primary); font-size:1.2rem;">₹${parseFloat(order.market_subtotal).toFixed(2)}</div>
                    </div>
                </div>
                
                <h3 style="margin-bottom:1rem; font-size:1.1rem;">Items Ordered</h3>
                <div class="table-responsive" style="margin-bottom:2rem; border-radius:8px; border:1px solid #e5e7eb;">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                    </table>
                </div>
                
                <div style="background:#f9fafb; padding:1.5rem; border-radius:12px; border:1px solid #e5e7eb;">
                    <h3 style="margin-bottom:1rem; font-size:1rem;">Update Status</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="${order.order_id}">
                        <div style="display: flex; align-items: center;">
                            <select name="new_status" class="action-select">
                                ${optionsHTML}
                            </select>
                            <button type="submit" class="btn btn-primary">Update</button>
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