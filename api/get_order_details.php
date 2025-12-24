<?php
/**
 * ByteShop - Get Order Details API
 * Returns order details including items for a specific order
 * Works for: Customer, Shop Owner, and Admin
 */

require_once '../config/db.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Require login (any role)
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit;
}

$user_id = get_user_id();
$user_role = get_user_role();
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Build query based on user role
    $order_query = "
        SELECT 
            o.order_id,
            o.customer_id,
            o.total_amount,
            o.order_status,
            o.order_date,
            o.delivery_address,
            o.payment_method,
            u.name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.user_id
        WHERE o.order_id = ?
    ";
    
    $params = [$order_id];
    
    // Add role-based restrictions
    if ($user_role === 'customer') {
        // Customers can only see their own orders
        $order_query .= " AND o.customer_id = ?";
        $params[] = $user_id;
    } 
    elseif ($user_role === 'shop_owner') {
        // Shop owners can only see orders containing their products
        $order_query .= " AND EXISTS (
            SELECT 1 FROM order_items oi
            JOIN markets m ON oi.market_id = m.market_id
            WHERE oi.order_id = o.order_id AND m.owner_id = ?
        )";
        $params[] = $user_id;
    }
    // Admin has no restrictions - can see all orders
    
    // Execute order query
    $stmt = $pdo->prepare($order_query);
    $stmt->execute($params);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }
    
    // Get order items with product and market details
    $items_query = "
        SELECT 
            oi.order_item_id,
            oi.quantity,
            oi.price,
            oi.subtotal,
            p.product_id,
            p.product_name,
            p.product_image,
            p.category,
            m.market_name,
            m.market_id
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN markets m ON oi.market_id = m.market_id
        WHERE oi.order_id = ?
    ";
    
    $items_params = [$order_id];
    
    // Shop owners only see items from their markets
    if ($user_role === 'shop_owner') {
        $items_query .= " AND m.owner_id = ?";
        $items_params[] = $user_id;
    }
    
    $items_query .= " ORDER BY oi.order_item_id";
    
    $stmt = $pdo->prepare($items_query);
    $stmt->execute($items_params);
    $items = $stmt->fetchAll();
    
    // Return response
    echo json_encode([
        'success' => true,
        'message' => 'Order details retrieved successfully',
        'order' => $order,
        'items' => $items,
        'data' => [ // Alternative structure for backward compatibility
            'order' => $order,
            'items' => $items
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get Order Details Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>