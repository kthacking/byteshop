<?php
/**
 * ByteShop - Shopping Cart Page
 * 
 * Features:
 * - Display all cart items
 * - Update quantity
 * - Remove items
 * - Calculate totals
 * - Proceed to checkout
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require customer login
require_customer();

$customer_id = get_user_id();
$message = '';
$error = '';

// Handle POST actions (Update/Remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            // Check product stock before updating
            $stmt = $pdo->prepare("
                SELECT p.stock 
                FROM cart c 
                JOIN products p ON c.product_id = p.product_id 
                WHERE c.cart_id = ? AND c.customer_id = ?
            ");
            $stmt->execute([$cart_id, $customer_id]);
            $product = $stmt->fetch();
            
            if ($product && $quantity <= $product['stock']) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
                $stmt->execute([$quantity, $cart_id, $customer_id]);
                $message = "Cart updated successfully!";
            } else {
                $error = "Requested quantity not available!";
            }
        }
    }
    
    if ($action === 'remove') {
        $cart_id = (int)$_POST['cart_id'];
        $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
        $stmt->execute([$cart_id, $customer_id]);
        $message = "Item removed from cart!";
    }
    
    if ($action === 'clear') {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $message = "Cart cleared!";
    }
}

// Fetch cart items with product and market details
$stmt = $pdo->prepare("
    SELECT 
        c.cart_id,
        c.quantity,
        p.product_id,
        p.product_name,
        p.product_image,
        p.price,
        p.stock,
        p.category,
        m.market_name,
        m.market_id,
        (p.price * c.quantity) as subtotal
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    JOIN markets m ON p.market_id = m.market_id
    WHERE c.customer_id = ? AND p.status = 'active'
    ORDER BY c.added_at DESC
");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$total_items = 0;
$subtotal = 0;
$shipping = 0;
$grand_total = 0;

foreach ($cart_items as $item) {
    $total_items += $item['quantity'];
    $subtotal += $item['subtotal'];
}

// Calculate shipping (example: ₹50 per item, free above ₹1000)
if ($subtotal > 0 && $subtotal < 1000) {
    $shipping = 50;
}

$grand_total = $subtotal + $shipping;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - ByteShop</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            
        }

        .container {
            max-width: 100%;
        }

        /* .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
        } */

        /* .nav-links a {
            margin-left: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #764ba2;
        } */

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }

        .cart-items {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .cart-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .item-market {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .item-category {
            display: inline-block;
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .item-price {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-top: 10px;
        }

        .item-stock {
            font-size: 12px;
            color: #28a745;
            margin-top: 5px;
        }

        .item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }

        .quantity-control input {
            width: 60px;
            text-align: center;
            border: none;
            font-size: 16px;
            font-weight: 600;
        }

        .quantity-control button {
            background: #667eea;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s;
        }

        .quantity-control button:hover {
            background: #764ba2;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #764ba2;
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

        .cart-summary {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .cart-summary h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #667eea;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart img {
            width: 200px;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-cart h2 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-cart p {
            color: #999;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-item {
                flex-direction: column;
            }

            .item-image {
                width: 100%;
                height: 200px;
            }

            .cart-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header
        <div class="header">
            <h1>🛒 Shopping Cart</h1>
            <div class="nav-links">
                <a href="index.php">← Continue Shopping</a>
                <a href="orders.php">My Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div> -->
        <?php include '../includes/customer_header.php'; ?>
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="cart-items">
                <div class="empty-cart">
                    <h2>Your cart is empty!</h2>
                    <p>Add some products to get started</p>
                    <a href="index.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px;">
                        Browse Markets
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Layout -->
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-items">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Cart Items (<?php echo $total_items; ?>)</h2>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Clear entire cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline">Clear Cart</button>
                        </form>
                    </div>

                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <?php
                            // Detect if image is URL or local file
                            $cart_item_image = $item['product_image'] ?: 'default.jpg';
                            $is_cart_url = preg_match('/^https?:\/\//i', $cart_item_image);
                            $cart_image_src = $is_cart_url ? htmlspecialchars($cart_item_image) : '../uploads/products/' . htmlspecialchars($cart_item_image);
                            ?>
                            <img src="<?php echo $cart_image_src; ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                 class="item-image"
                                 onerror="this.src='../assets/images/default-product.jpg'">
                            
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="item-market">
                                    🏪 <?php echo htmlspecialchars($item['market_name']); ?>
                                </div>
                                <span class="item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                <div class="item-price">₹<?php echo number_format($item['price'], 2); ?></div>
                                <div class="item-stock">
                                    <?php echo $item['stock'] > 0 ? "Stock: {$item['stock']} available" : "Out of stock"; ?>
                                </div>
                                <div style="margin-top: 10px; font-weight: 600; color: #333;">
                                    Subtotal: ₹<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <form method="POST" class="quantity-control">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    
                                    <button type="button" onclick="decrementQuantity(this)">-</button>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $item['stock']; ?>" readonly>
                                    <button type="button" onclick="incrementQuantity(this, <?php echo $item['stock']; ?>)">+</button>
                                    <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Update</button>
                                </form>

                                <form method="POST" style="margin-top: 10px;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <button type="submit" class="btn btn-remove" 
                                            onclick="return confirm('Remove this item?')">
                                        🗑️ Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Items (<?php echo $total_items; ?>)</span>
                        <span>₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
                    </div>
                    
                    <?php if ($subtotal >= 1000): ?>
                        <div style="color: #28a745; font-size: 12px; margin-top: 5px; text-align: center;">
                            🎉 You got FREE shipping!
                        </div>
                    <?php else: ?>
                        <div style="color: #ff9800; font-size: 12px; margin-top: 5px; text-align: center;">
                            Add ₹<?php echo number_format(1000 - $subtotal, 2); ?> more for FREE shipping
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>₹<?php echo number_format($grand_total, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="checkout-btn">
                        Proceed to Checkout →
                    </a>

                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #666;">
                        <div style="font-weight: 600; margin-bottom: 8px;">💳 Payment Options:</div>
                        <div>• Cash on Delivery</div>
                        <div>• 100% Secure Checkout</div>
                        <div>• Easy Returns</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function incrementQuantity(btn, maxStock) {
            const input = btn.parentElement.querySelector('input[name="quantity"]');
            let value = parseInt(input.value);
            if (value < maxStock) {
                input.value = value + 1;
            } else {
                alert('Maximum stock reached!');
            }
        }

        function decrementQuantity(btn) {
            const input = btn.parentElement.querySelector('input[name="quantity"]');
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
            }
        }
    </script>
</body>
</html>