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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #2D3436;
            --bg-color: #FAFAFA;
            --card-bg: #FFFFFF;
            --text-main: #333333;
            --text-sub: #666666;
            --border-color: #EEEEEE;
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            font-size: 14px;
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h2 {
            font-size: 2rem;
            color: var(--secondary);
            font-weight: 700;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
        }

        .order-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.03);
            transition: transform 0.2s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .order-id {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--secondary);
        }

        .order-date {
            color: var(--text-sub);
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .order-body {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .order-info-label {
            font-size: 0.8rem;
            color: var(--text-sub);
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .order-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--secondary);
        }

        .order-progress {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .progress-label {
            font-size: 0.85rem;
            color: var(--text-sub);
            margin-bottom: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #F1F1F1;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #FF6B35 0%, #FF9F43 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            position: relative;
        }
        
        /* Line connecting steps */
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #F1F1F1;
            z-index: 0;
        }

        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .progress-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1.2rem;
            transition: all 0.3s;
            border: 2px solid #ddd;
        }

        .progress-step.active .progress-step-icon {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
            transform: scale(1.1);
        }

        .progress-step.completed .progress-step-icon {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .progress-step-text {
            font-size: 0.75rem;
            color: var(--text-sub);
            font-weight: 600;
            text-transform: uppercase;
        }

        .progress-step.active .progress-step-text { color: var(--primary); }
        .progress-step.completed .progress-step-text { color: #4CAF50; }

        .order-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 53, 0.4);
            background: #e55e2d;
        }

        .btn-outline {
            background: white;
            color: var(--secondary);
            border: 1px solid #ddd;
        }

        .btn-outline:hover {
            background: #f8f8f8;
            border-color: #ccc;
        }

        .empty-orders {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .empty-orders-icon { font-size: 4rem; margin-bottom: 1rem; color: #eee; }
        .empty-orders h3 { margin-bottom: 0.5rem; color: var(--secondary); font-size: 1.5rem; }
        .empty-orders p { color: var(--text-sub); margin-bottom: 1.5rem; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 { color: var(--secondary); font-size: 1.5rem; }
        .modal-close { font-size: 1.5rem; cursor: pointer; color: var(--text-sub); }
        .modal-close:hover { color: var(--primary); }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            background: #F9FAFB;
            border-radius: 12px;
            margin-bottom: 0.5rem;
        }

        .order-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            background: white;
            border: 1px solid #eee;
        }

        .order-item-info { flex: 1; }
        .order-item-name { font-weight: 600; color: var(--secondary); font-size: 0.95rem; }
        .order-item-market { font-size: 0.8rem; color: var(--text-sub); }
        .order-item-price { font-weight: 700; color: var(--primary); font-size: 1rem; }

        @media (max-width: 768px) {
            .order-body { grid-template-columns: 1fr; }
            .order-actions { flex-direction: column; }
            .progress-steps { margin-top: 2.5rem; } 
            .progress-step-text { font-size: 0.65rem; }
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
            <div class="alert alert-success"><span>✓</span> <?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <div class="empty-orders-icon">🛍️</div>
                <h3>No Orders Yet</h3>
                <p>Looks like you haven't placed an order yet.</p>
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
                                <?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?>
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
                            <div class="order-info-label">Payment</div>
                            <div class="order-info-value"><?php echo strtoupper($order['payment_method']); ?></div>
                        </div>
                    </div>

                    <?php if ($order['order_status'] !== 'cancelled'): ?>
                        <div class="order-progress">
                            <div class="progress-label">Tracking Status</div>
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
                        <a href="index.php" class="btn btn-outline">Shop More</a>
                        <button class="btn btn-primary" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                            Track & Details
                        </button>
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
            modalBody.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;"><i class="fas fa-spinner fa-spin"></i> Loading details...</div>';
            
            // Fetch order details
            fetch(`../api/get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order || data.data.order;
                        const items = data.items || data.data.items;
                        
                        let html = `
                            <div style="background:#F9FAFB; padding:1rem; border-radius:10px; margin-bottom:1.5rem; font-size:0.9rem;">
                                <strong style="display:block; margin-bottom:0.5rem; color:#333;">Delivery Address:</strong>
                                <span style="color:#666;">${order.delivery_address}</span>
                            </div>
                            <h4 style="margin: 0 0 1rem; color:#333; font-size:1.1rem;">Items</h4>
                        `;
                        
                        items.forEach(item => {
                            let imageSrc = '../assets/images/default-product.jpg';
                            if (item.product_image) {
                                if (item.product_image.startsWith('http')) {
                                    imageSrc = item.product_image;
                                } else {
                                    imageSrc = '../uploads/products/' + item.product_image;
                                }
                            }
                            
                            html += `
                                <div class="order-item">
                                    <img src="${imageSrc}" class="order-item-image" onerror="this.src='../assets/images/default-product.jpg'">
                                    <div class="order-item-info">
                                        <div class="order-item-name">${item.product_name}</div>
                                        <div class="order-item-market">From: ${item.market_name}</div>
                                        <div style="font-size:0.85rem; color:#666; margin-top:2px;">Qty: ${item.quantity} × ₹${parseFloat(item.price).toFixed(2)}</div>
                                    </div>
                                    <div class="order-item-price">₹${parseFloat(item.subtotal).toFixed(2)}</div>
                                </div>
                            `;
                        });
                        
                        html += `
                            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; display: flex; justify-content: space-between; font-weight: 700; font-size: 1.2rem;">
                                <span>Total Amount</span>
                                <span style="color:var(--primary);">₹${parseFloat(order.total_amount).toFixed(2)}</span>
                            </div>
                        `;
                        
                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = '<p style="text-align: center; color: red;">Failed to load order details: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<p style="text-align: center; color: red;">Connection error. Please try again.</p>';
                });
        }
        function closeModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>