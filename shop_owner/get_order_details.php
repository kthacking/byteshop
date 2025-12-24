<?php
/**
 * ByteShop - Get Order Details API
 * 
 * Returns order details for a specific order (shop owner access only)
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require shop owner login
require_shop_owner();

header('Content-Type: application/json');

$user_id = get_user_id();
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Get shop owner's market ID
    $market_stmt = $pdo->prepare("SELECT market_id FROM markets WHERE owner_id = ? AND status = 'active'");
    $market_stmt->execute([$user_id]);
    $market = $market_stmt->fetch();
    if (!$market) {
    echo json_encode(['success' => false, 'message' => 'No active market found']);
    exit;
}

$market_id = $market['market_id'];

// Verify order belongs to this market
$verify_stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM order_items 
    WHERE order_id = ? AND market_id = ?
");
$verify_stmt->execute([$order_id, $market_id]);
$verify = $verify_stmt->fetch();

if ($verify['count'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get order details
$order_stmt = $pdo->prepare("
    SELECT 
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
         WHERE oi.order_id = o.order_id AND oi.market_id = ?) as market_subtotal
    FROM orders o
    INNER JOIN users u ON o.customer_id = u.user_id
    WHERE o.order_id = ?
");
$order_stmt->execute([$market_id, $order_id]);
$order = $order_stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Format order date
$order['order_date'] = date('d M Y, h:i A', strtotime($order['order_date']));

// Get order items (only from this market)
$items_stmt = $pdo->prepare("
    SELECT 
        p.product_name,
        oi.quantity,
        oi.price,
        oi.subtotal
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ? AND oi.market_id = ?
");
$items_stmt->execute([$order_id, $market_id]);
$items = $items_stmt->fetchAll();

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
} catch(PDOException $e) {
echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>