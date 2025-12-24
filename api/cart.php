<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'add') {
    $product_id = (int)$data['product_id'];
    $customer_id = $_SESSION['user_id'];
    
    // Check if product exists and has stock
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product || $product['stock'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product not available']);
        exit();
    }
    
    // Check if already in cart
    $stmt = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$customer_id, $product_id]);
    $cart_item = $stmt->fetch();
    
    if ($cart_item) {
        // Update quantity
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE cart_id = ?");
        $stmt->execute([$cart_item['cart_id']]);
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, 1)");
        $stmt->execute([$customer_id, $product_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Added to cart']);
}
?>