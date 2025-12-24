<?php
/**
 * ByteShop - Get Order Details API
 * Returns detailed information about a specific order
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require login
require_login();

header('Content-Type: application/json');

if (!isset($_GET['order_id'])) {
    json_response(false, 'Order ID is required');
}

$order_id = clean_input($_GET['order_id']);

try {
    // Get order details
    $order_query = "SELECT o.*, u.name as customer_name, u.email, u.phone 
                    FROM orders o
                    LEFT JOIN users u ON o.customer_id = u.user_id
                    WHERE o.order_id = ?";
    
    // Check user role and permissions
    if (is_customer()) {
        $order_query .= " AND o.customer_id = ?";
        $stmt = $pdo->prepare($order_query);
        $stmt->execute([$order_id, get_user_id()]);
    } elseif (is_shop_owner()) {
        // Shop owners can only see orders for their markets
        $order_query .= " AND EXISTS (
            SELECT 1 FROM order_items oi
            JOIN markets m ON oi.market_id = m.market_id
            WHERE oi.order_id = o.order_id AND m.owner_id = ?
        )";
        $stmt = $pdo->prepare($order_query);
        $stmt->execute([$order_id, get_user_id()]);
    } else {
        // Admin can see all orders
        $stmt = $pdo->prepare($order_query);
        $stmt->execute([$order_id]);
    }
    
    $order = $stmt->fetch();
    
    if (!$order) {
        json_response(false, 'Order not found or access denied');
    }
    
    // Get order items
    $items_query = "SELECT oi.*, p.product_name, p.product_image, m.market_name
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.product_id
                    LEFT JOIN markets m ON oi.market_id = m.market_id
                    WHERE oi.order_id = ?";
    
    $stmt = $pdo->prepare($items_query);
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Format order date
    $order['order_date'] = date('M d, Y h:i A', strtotime($order['order_date']));
    
    json_response(true, 'Order details retrieved successfully', [
        'order' => $order,
        'items' => $items
    ]);
    
} catch (PDOException $e) {
    json_response(false, 'Database error: ' . $e->getMessage());
}
?>