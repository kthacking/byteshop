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

// Handle AJAX Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_qty') {
    header('Content-Type: application/json');
    $cart_id = (int)$_POST['cart_id'];
    $quantity = (int)$_POST['quantity'];
    $response = ['success' => false];

    if ($quantity > 0) {
        // Check stock
        $stmt = $pdo->prepare("SELECT p.stock FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.cart_id = ? AND c.customer_id = ?");
        $stmt->execute([$cart_id, $customer_id]);
        $product = $stmt->fetch();

        if ($product && $quantity <= $product['stock']) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
            $stmt->execute([$quantity, $cart_id, $customer_id]);
            $response['success'] = true;
        } else {
            $response['message'] = 'Requested quantity not available';
            echo json_encode($response); exit;
        }
    }

    // Recalculate Totals
    $stmt = $pdo->prepare("
        SELECT 
            c.cart_id,
            c.quantity,
            p.price,
            (p.price * c.quantity) as item_subtotal
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        WHERE c.customer_id = ? AND p.status = 'active'
    ");
    $stmt->execute([$customer_id]);
    $cart_items_data = $stmt->fetchAll();

    $total_items = 0;
    $subtotal = 0;
    $current_item_subtotal = 0;

    foreach ($cart_items_data as $item) {
        $total_items += $item['quantity'];
        $subtotal += $item['item_subtotal'];
        if ($item['cart_id'] == $cart_id) {
            $current_item_subtotal = $item['item_subtotal'];
        }
    }

    $shipping = ($subtotal > 0 && $subtotal < 1000) ? 50 : 0;
    $grand_total = $subtotal + $shipping;

    $response['data'] = [
        'item_subtotal' => number_format($current_item_subtotal, 2),
        'total_items' => $total_items,
        'subtotal' => number_format($subtotal, 2),
        'shipping' => $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE',
        'grand_total' => number_format($grand_total, 2),
        'shipping_val' => $shipping,
        'remaining' => number_format(1000 - $subtotal, 2)
    ];

    echo json_encode($response);
    exit;
}

// Handle POST actions (Update/Remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            // Chek product stock before updating
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

// Fetch cart items with product and   market details
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
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            color: #e0e0e0;
            min-height: 100vh;
        }

        .container {
             max-width: 100%;
            margin: 1.8rem auto; /* 90% of 2rem */
            padding: 0 0.9rem; /* 90% of 1rem */
        }

        .alert {
            padding: 13.5px;
            border-radius: 7.2px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 12.6px;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border-color: rgba(40, 167, 69, 0.3);
        }

        .alert-error {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.3);
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 315px;
            gap: 18px;
            
        }

        .cart-items {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            max-width: 100%;
            padding: 18px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-items h2 {
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
        }

        .cart-item {
            display: flex;
            gap: 18px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-bottom: 13.5px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.02);
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 107, 53, 0.2);
            border-color: rgba(255, 107, 53, 0.3);
        }

        .item-image {
            width: 108px;
            height: 108px;
            object-fit: cover;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 16.2px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 7.2px;
        }

        .item-market {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 12.6px;
            margin-bottom: 7.2px;
            font-weight: 600;
        }

        .item-category {
            display: inline-block;
            background: rgba(74, 158, 255, 0.15);
            padding: 3.6px 10.8px;
            border-radius: 13.5px;
            font-size: 10.8px;
            color: #4a9eff;
            margin-bottom: 9px;
            border: 1px solid rgba(74, 158, 255, 0.3);
        }

        .item-price {
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: 9px;
        }

        .item-stock {
            font-size: 10.8px;
            color: #28a745;
            margin-top: 4.5px;
            font-weight: 500;
        }

        .item-actions {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 7.2px;
            padding: 4.5px;
            background: rgba(255, 255, 255, 0.05);
        }

        .quantity-control input {
            width: 54px;
            text-align: center;
            border: none;
            font-size: 14.4px;
            font-weight: 600;
            background: transparent;
            color: #ffffff;
        }

        .quantity-control button {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            width: 27px;
            height: 27px;
            border-radius: 5.4px;
            cursor: pointer;
            font-size: 16.2px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
        }

        .quantity-control button:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.5);
        }

        .btn {
            padding: 9px 18px;
            border: none;
            border-radius: 7.2px;
            cursor: pointer;
            font-size: 12.6px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-remove {
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }

        .btn-remove:hover {
            background: rgba(255, 71, 87, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.5);
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            color: #ff6b35;
            border: 2px solid rgba(255, 107, 53, 0.5);
        }

        .btn-outline:hover {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border-color: transparent;
        }

        .cart-summary {
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            padding: 22.5px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            position: sticky;
            top: 98px;
            max-height: max-content;
           
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-summary h2 {
            margin-bottom: 18px;
            color: #ffffff;
            border-bottom: 2px solid rgba(255, 107, 53, 0.5);
            padding-bottom: 9px;
            font-size: 18px;
            font-weight: 700;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10.8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 12.6px;
            color: #e0e0e0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: 9px;
            padding-top: 13.5px;
            border-top: 2px solid rgba(255, 107, 53, 0.5);
        }

        .checkout-btn {
            width: 100%;
            padding: 13.5px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-size: 16.2px;
            position: relative;
            top:35px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 18px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.6);
        }

        .checkout-btn:disabled {
            background: rgba(128, 128, 128, 0.3);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .empty-cart {
            text-align: center;
            padding: 54px 18px;
        }

        .empty-cart img {
            width: 180px;
            opacity: 0.3;
            margin-bottom: 18px;
        }

        .empty-cart h2 {
            color: #ffffff;
            margin-bottom: 9px;
            font-size: 18px;
        }

        .empty-cart p {
            color: #a0a0a0;
            margin-bottom: 27px;
            font-size: 12.6px;
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
                height: 180px;
            }

            .cart-summary {
                position: static;
            }

            .container {
                padding: 13.5px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    <div class="container">
        
        
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
                    <a href="index.php" class="btn btn-primary" style="padding: 13.5px 36px; font-size: 14.4px;">
                        Browse Markets
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Layout -->
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-items">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <h2>Cart Items (<?php echo $total_items; ?>)</h2>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Clear entire cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-outline">Clear Cart</button>
                        </form>
                    </div>

                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <?php
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
                                <div style="margin-top: 9px; font-weight: 600; color: #ffffff; font-size: 13.5px;" id="item-subtotal-<?php echo $item['cart_id']; ?>">
                                    Subtotal: ₹<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <div class="quantity-control">
                                    <button type="button" onclick="updateItemQuantity(this, <?php echo $item['cart_id']; ?>, -1)">-</button>
                                    <input type="number" id="qty-<?php echo $item['cart_id']; ?>" value="<?php echo $item['quantity']; ?>" readonly>
                                    <button type="button" onclick="updateItemQuantity(this, <?php echo $item['cart_id']; ?>, 1, <?php echo $item['stock']; ?>)">+</button>
                                </div>

                                <form method="POST" style="margin-top: 9px;">
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
                        <span id="summary-items">Items (<?php echo $total_items; ?>)</span>
                        <span id="summary-subtotal">₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span id="summary-shipping"><?php echo $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE'; ?></span>
                    </div>
                    
                    <div id="summary-shipping-msg">
                    <?php if ($subtotal >= 1000): ?>
                        <div style="color: #28a745; font-size: 10.8px; margin-top: 4.5px; text-align: center; font-weight: 500;">
                            🎉 You got FREE shipping!
                        </div>
                    <?php else: ?>
                        <div style="color: #ff9800; font-size: 10.8px; margin-top: 4.5px; text-align: center; font-weight: 500;">
                            Add ₹<?php echo number_format(1000 - $subtotal, 2); ?> more for FREE shipping
                        </div>
                    <?php endif; ?>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="summary-total">₹<?php echo number_format($grand_total, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="checkout-btn">
                        Proceed to Checkout →
                    </a>

                    <div style="margin-top: 68px; padding: 13.5px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; font-size: 11.7px; color: #a0a0a0; border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="font-weight: 600; margin-bottom: 7.2px; color: #ffffff;">💳 Payment Options:</div>
                        <div>• Cash on Delivery</div>
                        <div>• 100% Secure Checkout</div>
                        <div>• Easy Returns</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateItemQuantity(btn, cartId, change, maxStock = 100) {
            const input = document.getElementById('qty-' + cartId);
            let currentQty = parseInt(input.value);
            let newQty = currentQty + change;

            if (newQty < 1) return; // Prevent going below 1
            if (newQty > maxStock) {
                alert('Maximum stock reached!');
                return;
            }

            // Optimistic update
            input.value = newQty;
            btn.disabled = true; // Prevent rapid clicking

            const formData = new FormData();
            formData.append('ajax_action', 'update_qty');
            formData.append('cart_id', cartId);
            formData.append('quantity', newQty);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    // Update Item Subtotal
                    document.getElementById('item-subtotal-' + cartId).textContent = 'Subtotal: ₹' + data.data.item_subtotal;
                    
                    // Update Summary
                    document.getElementById('summary-items').textContent = 'Items (' + data.data.total_items + ')';
                    document.getElementById('summary-subtotal').textContent = '₹' + data.data.subtotal;
                    document.getElementById('summary-shipping').textContent = data.data.shipping;
                    document.getElementById('summary-total').textContent = '₹' + data.data.grand_total;

                    // Update Shipping Message
                    const msgDiv = document.getElementById('summary-shipping-msg');
                    if (data.data.shipping_val === 0) {
                        msgDiv.innerHTML = '<div style="color: #28a745; font-size: 10.8px; margin-top: 4.5px; text-align: center; font-weight: 500;">🎉 You got FREE shipping!</div>';
                    } else {
                        msgDiv.innerHTML = '<div style="color: #ff9800; font-size: 10.8px; margin-top: 4.5px; text-align: center; font-weight: 500;">Add ₹' + data.data.remaining + ' more for FREE shipping</div>';
                    }
                } else {
                    alert(data.message || 'Error updating cart');
                    input.value = currentQty; // Revert
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                input.value = currentQty;
                alert('Connection error');
            });
        }
    </script>
</body>
</html>