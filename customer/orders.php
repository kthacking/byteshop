<?php
/**
 * ByteShop - Order History & Tracking
 * Customer can view order history and track orders
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require customer login
require_customer();

$customer_id = get_user_id();
$success_message = '';

// Check for order placement success
if (isset($_GET['order_placed'])) {
    $success_message = "Order placed successfully! Order ID: #" . htmlspecialchars($_GET['order_placed']);
}

// Fetch all orders for this customer
$orders_query = "
    SELECT 
        o.order_id,
        o.total_amount,
        o.order_status,
        o.order_date,
        o.delivery_address,
        o.payment_method,
        COUNT(oi.order_item_id) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.customer_id = :customer_id
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
";

$stmt = $pdo->prepare($orders_query);
$stmt->execute(['customer_id' => $customer_id]);
$orders = $stmt->fetchAll();

// Function to get order status badge color
function getStatusColor($status) {
    $colors = [
        'placed' => '#3498db',
        'packed' => '#f39c12',
        'shipped' => '#9b59b6',
        'delivered' => '#27ae60',
        'cancelled' => '#e74c3c'
    ];
    return $colors[$status] ?? '#95a5a6';
}

// Function to get order status progress
function getStatusProgress($status) {
    $progress = [
        'placed' => 25,
        'packed' => 50,
        'shipped' => 75,
        'delivered' => 100,
        'cancelled' => 0
    ];
    return $progress[$status] ?? 0;
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            color: white;
            font-size: 1.8rem;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            transition: opacity 0.3s;
        }

        .navbar a:hover {
            opacity: 0.8;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h2 {
            font-size: 2rem;
            color: #333;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 1rem;
        }

        .order-id {
            font-size: 1.1rem;
            font-weight: 700;
            color: #667eea;
        }

        .order-date {
            color: #888;
            font-size: 0.9rem;
        }

        .order-body {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .order-info {
            display: flex;
            flex-direction: column;
        }

        .order-info-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 0.25rem;
        }

        .order-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
        }

        .order-progress {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #f0f0f0;
        }

        .progress-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }

        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .progress-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .progress-step.active .progress-step-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.1);
        }

        .progress-step.completed .progress-step-icon {
            background: #27ae60;
            color: white;
        }

        .progress-step-text {
            font-size: 0.75rem;
            color: #888;
            font-weight: 600;
        }

        .progress-step.active .progress-step-text {
            color: #667eea;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .empty-orders {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
        }

        .empty-orders-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .empty-orders h3 {
            color: #666;
            margin-bottom: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #333;
        }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        .order-item-info {
            flex: 1;
        }

        .order-item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .order-item-market {
            font-size: 0.85rem;
            color: #888;
        }

        .order-item-price {
            font-weight: 700;
            color: #667eea;
        }

        @media (max-width: 768px) {
            .order-body {
                grid-template-columns: 1fr;
            }

            .progress-steps {
                flex-wrap: wrap;
            }

            .progress-step {
                flex: 1 0 50%;
                margin-bottom: 1rem;
            }

            .order-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1 🛒 ByteShop</h1>
<div>
<a href="index.php">Home</a>
<a href="cart.php">Cart</a>
<a href="orders.php">My Orders</a>
<a href="../logout.php">Logout</a>
</div>
</div>
</nav>

<div class="container">
    <div class="page-header">
        <h2>My Orders</h2>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <div class="empty-orders-icon">📦</div>
            <h3>No orders yet!</h3>
            <p style="color: #888; margin-bottom: 1.5rem;">Start shopping and your orders will appear here.</p>
            <a href="index.php" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): 
            $status_progress = getStatusProgress($order['order_status']);
            $status_color = getStatusColor($order['order_status']);
        ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                        <div class="order-date">
                            Placed on <?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?>
                        </div>
                    </div>
                    <span class="status-badge" style="background: <?php echo $status_color; ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                </div>

                <div class="order-body">
                    <div class="order-info">
                        <div class="order-info-label">Total Amount</div>
                        <div class="order-info-value">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                    </div>
                    <div class="order-info">
                        <div class="order-info-label">Items</div>
                        <div class="order-info-value"><?php echo $order['total_items']; ?> Item(s)</div>
                    </div>
                    <div class="order-info">
                        <div class="order-info-label">Payment Method</div>
                        <div class="order-info-value"><?php echo strtoupper($order['payment_method']); ?></div>
                    </div>
                </div>

                <?php if ($order['order_status'] !== 'cancelled'): ?>
                    <div class="order-progress">
                        <div class="progress-label">Order Progress</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $status_progress; ?>%"></div>
                        </div>

                        <div class="progress-steps">
                            <div class="progress-step <?php echo in_array($order['order_status'], ['placed', 'packed', 'shipped', 'delivered']) ? 'completed' : ''; ?> <?php echo $order['order_status'] === 'placed' ? 'active' : ''; ?>">
                                <div class="progress-step-icon">📋</div>
                                <div class="progress-step-text">Placed</div>
                            </div>
                            <div class="progress-step <?php echo in_array($order['order_status'], ['packed', 'shipped', 'delivered']) ? 'completed' : ''; ?> <?php echo $order['order_status'] === 'packed' ? 'active' : ''; ?>">
                                <div class="progress-step-icon">📦</div>
                                <div class="progress-step-text">Packed</div>
                            </div>
                            <div class="progress-step <?php echo in_array($order['order_status'], ['shipped', 'delivered']) ? 'completed' : ''; ?> <?php echo $order['order_status'] === 'shipped' ? 'active' : ''; ?>">
                                <div class="progress-step-icon">🚚</div>
                                <div class="progress-step-text">Shipped</div>
                            </div>
                            <div class="progress-step <?php echo $order['order_status'] === 'delivered' ? 'completed active' : ''; ?>">
                                <div class="progress-step-icon">✅</div>
                                <div class="progress-step-text">Delivered</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="order-actions">
                    <button class="btn btn-primary" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                        View Details
                    </button>
                    <a href="index.php" class="btn btn-outline">Continue Shopping</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Details</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalBody">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<script>
   function viewOrderDetails(orderId) {
        const modal = document.getElementById('orderModal');
        const modalBody = document.getElementById('modalBody');
        
        // Show modal
        modal.classList.add('active');
        modalBody.innerHTML = '<p style="text-align: center; padding: 2rem;">Loading...</p>';
        
        // Fetch order details
        fetch(`../api/get_order_details.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use either data.order or data.data.order (for backward compatibility)
                    const order = data.order || data.data.order;
                    const items = data.items || data.data.items;
                    
                    let html = '<div style="margin-bottom: 1rem;"><strong>Delivery Address:</strong><br>' + order.delivery_address + '</div>';
                    html += '<h4 style="margin: 1.5rem 0 1rem; color: #667eea;">Order Items</h4>';
                    
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
                        
                        html += `
                            <div class="order-item">
                                <img src="${imageSrc}" 
                                     class="order-item-image" 
                                     alt="${item.product_name}" 
                                     onerror="this.src='../assets/images/default-product.jpg'">
                                <div class="order-item-info">
                                    <div class="order-item-name">${item.product_name}</div>
                                    <div class="order-item-market">From: ${item.market_name}</div>
                                    <div style="margin-top: 0.5rem; color: #888;">Qty: ${item.quantity} × ₹${parseFloat(item.price).toFixed(2)}</div>
                                </div>
                                <div class="order-item-price">₹${parseFloat(item.subtotal).toFixed(2)}</div>
                            </div>
                        `;
                    });
                    
                    html += `
                        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #f0f0f0;">
                            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: #667eea;">
                                <span>Total Amount</span>
                                <span>₹${parseFloat(order.total_amount).toFixed(2)}</span>
                            </div>
                        </div>
                    `;
                    
                    modalBody.innerHTML = html;
                } else {
                    modalBody.innerHTML = '<p style="text-align: center; color: #e74c3c;">Failed to load order details: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading order details. Please try again.</p>';
            });
    }
    function closeModal() {
        document.getElementById('orderModal').classList.remove('active');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('orderModal');
        if (event.target === modal) {
            closeModal();
        }
    }
</script>
</body>
</html>