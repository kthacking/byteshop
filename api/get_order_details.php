<?php
/**
 * ByteShop - Get Order Details API
 * Returns order details including items for a specific order
 */

require_once '../config/db.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Require customer login
if (!is_logged_in() || get_user_role() !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customer_id = get_user_id();
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Get order details and verify ownership
    $stmt = $pdo->prepare("
        SELECT 
            order_id,
            total_amount,
            order_status,
            order_date,
            delivery_address,
            payment_method
        FROM orders
        WHERE order_id = ? AND customer_id = ?
    ");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Get order items with product and market details
    $stmt = $pdo->prepare("
        SELECT 
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
        JOIN products p ON oi.product_id = p.product_id
        JOIN markets m ON oi.market_id = m.market_id
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>