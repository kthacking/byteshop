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
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            font-size: 14.4px; /* 90% of 16px */
        }

        .container {
            max-width: 100%;
            margin: 1.8rem auto; /* 90% of 2rem */
            padding: 0 0.9rem; /* 90% of 1rem */
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.8rem; /* 90% of 2rem */
        }

        .page-header h2 {
            font-size: 1.8rem; /* 90% of 2rem */
            color: #e0e0e0;
            font-weight: 700;
        }

        .alert {
            padding: 0.9rem; /* 90% of 1rem */
            border-radius: 7.2px; /* 90% of 8px */
            margin-bottom: 0.9rem; /* 90% of 1rem */
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border-left: 3.6px solid #4caf50; /* 90% of 4px */
        }

        .order-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            border-radius: 14.4px; /* 90% of 16px */
            padding: 1.35rem; /* 90% of 1.5rem */
            margin-bottom: 1.35rem; /* 90% of 1.5rem */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #2a2a2a;
        }

        .order-card:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 7.2px 27px rgba(255, 107, 53, 0.15); /* 90% scale */
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.9rem; /* 90% of 1rem */
            border-bottom: 2px solid #2a2a2a;
            margin-bottom: 0.9rem; /* 90% of 1rem */
        }

        .order-id {
            font-size: 0.99rem; /* 90% of 1.1rem */
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .order-date {
            color: #909090;
            font-size: 0.81rem; /* 90% of 0.9rem */
        }

        .order-body {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.9rem; /* 90% of 1rem */
            margin-bottom: 0.9rem; /* 90% of 1rem */
        }

        .order-info {
            display: flex;
            flex-direction: column;
        }

        .order-info-label {
            font-size: 0.765rem; /* 90% of 0.85rem */
            color: #909090;
            margin-bottom: 0.225rem; /* 90% of 0.25rem */
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }

        .order-info-value {
            font-size: 0.9rem; /* 90% of 1rem */
            font-weight: 600;
            color: #e0e0e0;
        }

        .status-badge {
            display: inline-block;
            padding: 0.45rem 0.9rem; /* 90% of 0.5rem 1rem */
            border-radius: 18px; /* 90% of 20px */
            font-size: 0.765rem; /* 90% of 0.85rem */
            font-weight: 600;
            text-transform: uppercase;
            color: white;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }

        .order-progress {
            margin-top: 0.9rem; /* 90% of 1rem */
            padding-top: 0.9rem; /* 90% of 1rem */
            border-top: 2px solid #2a2a2a;
        }

        .progress-label {
            font-size: 0.81rem; /* 90% of 0.9rem */
            color: #909090;
            margin-bottom: 0.45rem; /* 90% of 0.5rem */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }

        .progress-bar-container {
            width: 100%;
            height: 7.2px; /* 90% of 8px */
            background: #2a2a2a;
            border-radius: 9px; /* 90% of 10px */
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
            border-radius: 9px; /* 90% of 10px */
            transition: width 0.5s ease;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 0.9rem; /* 90% of 1rem */
        }

        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .progress-step-icon {
            width: 36px; /* 90% of 40px */
            height: 36px; /* 90% of 40px */
            border-radius: 50%;
            background: #2a2a2a;
            color: #707070;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.45rem; /* 90% of 0.5rem */
            font-size: 1.08rem; /* 90% of 1.2rem */
            transition: all 0.3s;
            border: 2px solid #2a2a2a;
        }

        .progress-step.active .progress-step-icon {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            transform: scale(1.1);
            border-color: #ff6b35;
            box-shadow: 0 0 0 3.6px rgba(255, 107, 53, 0.2); /* 90% of 4px */
        }

        .progress-step.completed .progress-step-icon {
            background: #4caf50;
            color: white;
            border-color: #4caf50;
        }

        .progress-step-text {
            font-size: 0.675rem; /* 90% of 0.75rem */
            color: #707070;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }

        .progress-step.active .progress-step-text {
            color: #ff6b35;
        }

        .progress-step.completed .progress-step-text {
            color: #4caf50;
        }

        .order-actions {
            display: flex;
            gap: 0.9rem; /* 90% of 1rem */
            margin-top: 0.9rem; /* 90% of 1rem */
        }

        .btn {
            padding: 0.54rem 1.35rem; /* 90% of 0.6rem 1.5rem */
            border: none;
            border-radius: 7.2px; /* 90% of 8px */
            font-size: 0.81rem; /* 90% of 0.9rem */
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 4.5px 13.5px rgba(255, 107, 53, 0.4); /* 90% scale */
        }

        .btn-outline {
            background: transparent;
            color: #ff6b35;
            border: 2px solid #ff6b35;
        }

        .btn-outline:hover {
            background: #ff6b35;
            color: white;
        }

        .empty-orders {
            text-align: center;
            padding: 3.6rem 1.8rem; /* 90% of 4rem 2rem */
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            border-radius: 14.4px; /* 90% of 16px */
            border: 1px solid #2a2a2a;
        }

        .empty-orders-icon {
            font-size: 4.5rem; /* 90% of 5rem */
            margin-bottom: 0.9rem; /* 90% of 1rem */
        }

        .empty-orders h3 {
            color: #909090;
            margin-bottom: 0.9rem; /* 90% of 1rem */
            font-size: 1.62rem; /* 90% of 1.8rem */
        }

        .empty-orders p {
            color: #707070;
            margin-bottom: 1.35rem; /* 90% of 1.5rem */
            font-size: 0.9rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 1.8rem; /* 90% of 2rem */
            border-radius: 14.4px; /* 90% of 16px */
            max-width: 540px; /* 90% of 600px */
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 9px 36px rgba(0,0,0,0.6); /* 90% scale */
            border: 1px solid #2a2a2a;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.35rem; /* 90% of 1.5rem */
            padding-bottom: 0.9rem; /* 90% of 1rem */
            border-bottom: 2px solid #2a2a2a;
        }

        .modal-header h3 {
            color: #e0e0e0;
            font-size: 1.62rem; /* 90% of 1.8rem */
        }

        .modal-close {
            font-size: 1.35rem; /* 90% of 1.5rem */
            cursor: pointer;
            color: #707070;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #f44336;
        }

        .order-item {
            display: flex;
            gap: 0.9rem; /* 90% of 1rem */
            padding: 0.9rem; /* 90% of 1rem */
            border-bottom: 1px solid #2a2a2a;
            align-items: center;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-image {
            width: 72px; /* 90% of 80px */
            height: 72px; /* 90% of 80px */
            object-fit: cover;
            border-radius: 7.2px; /* 90% of 8px */
            border: 1px solid #2a2a2a;
        }

        .order-item-info {
            flex: 1;
        }

        .order-item-name {
            font-weight: 600;
            margin-bottom: 0.225rem; /* 90% of 0.25rem */
            color: #e0e0e0;
            font-size: 0.9rem;
        }

        .order-item-market {
            font-size: 0.765rem; /* 90% of 0.85rem */
            color: #909090;
        }

        .order-item-price {
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.08rem; /* 90% of 1.2rem */
        }

        #modalBody > div:first-child {
            margin-bottom: 0.9rem; /* 90% of 1rem */
            color: #b0b0b0;
        }

        #modalBody h4 {
            margin: 1.35rem 0 0.9rem; /* 90% of 1.5rem 0 1rem */
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        #modalBody > div:last-child {
            margin-top: 1.35rem; /* 90% of 1.5rem */
            padding-top: 0.9rem; /* 90% of 1rem */
            border-top: 2px solid #2a2a2a;
        }

        #modalBody > div:last-child > div {
            display: flex;
            justify-content: space-between;
            font-size: 1.08rem; /* 90% of 1.2rem */
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .order-item > div:last-child {
            margin-top: 0.45rem; /* 90% of 0.5rem */
            color: #909090;
            font-size: 0.81rem; /* 90% of 0.9rem */
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
                margin-bottom: 0.9rem; /* 90% of 1rem */
            }

            .order-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
        <?php include '../includes/customer_header.php'; ?>
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
            <p>Start shopping and your orders will appear here.</p>
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
        modalBody.innerHTML = '<p style="text-align: center; padding: 1.8rem; color: #909090;">Loading...</p>';
        
        // Fetch order details
        fetch(`../api/get_order_details.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use either data.order or data.data.order (for backward compatibility)
                    const order = data.order || data.data.order;
                    const items = data.items || data.data.items;
                    
                    let html = '<div style="margin-bottom: 0.9rem;"><strong>Delivery Address:</strong><br>' + order.delivery_address + '</div>';
                    html += '<h4 style="margin: 1.35rem 0 0.9rem;">Order Items</h4>';
                    
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
                                    <div style="margin-top: 0.45rem; color: #909090;">Qty: ${item.quantity} × ₹${parseFloat(item.price).toFixed(2)}</div>
                                </div>
                                <div class="order-item-price">₹${parseFloat(item.subtotal).toFixed(2)}</div>
                            </div>
                        `;
                    });
                    
                    html += `
                        <div style="margin-top: 1.35rem; padding-top: 0.9rem; border-top: 2px solid #2a2a2a;">
                            <div style="display: flex; justify-content: space-between; font-size: 1.08rem; font-weight: 700;">
                                <span>Total Amount</span>
                                <span>₹${parseFloat(order.total_amount).toFixed(2)}</span>
                            </div>
                        </div>
                    `;
                    
                    modalBody.innerHTML = html;
                } else {
                    modalBody.innerHTML = '<p style="text-align: center; color: #f44336;">Failed to load order details: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<p style="text-align: center; color: #f44336;">Error loading order details. Please try again.</p>';
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