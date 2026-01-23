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
            min-height: 100vh;
            font-size: 14px;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .alert-success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .alert-error { background: #FFEBEE; color: #C62828; border: 1px solid #F5C6CB; }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            align-items: start;
        }

        .cart-items-container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2rem;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-header h2 { font-size: 1.5rem; color: var(--secondary); font-weight: 700; }

        .cart-item {
            display: flex;
            gap: 1.5rem;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #eee;
        }

        .item-details { flex: 1; }

        .item-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .item-market {
            font-size: 0.85rem;
            color: var(--text-sub);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .item-category {
            display: inline-block;
            background: #F1F1F1;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            color: var(--text-sub);
            margin-bottom: 0.8rem;
        }

        .item-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .item-subtotal {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--secondary);
            margin-top: 5px;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .quantity-control input {
            width: 40px;
            text-align: center;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: var(--secondary);
            padding: 5px 0;
        }

        .quantity-control button {
            background: #f8f8f8;
            border: none;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--text-sub);
            transition: all 0.2s;
        }
        .quantity-control button:hover { background: #eee; color: var(--secondary); }

        .btn-remove {
            color: #FF4757;
            background: none;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.2s;
        }
        .btn-remove:hover { background: #FFF0F1; }

        .cart-summary {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2rem;
            position: sticky;
            top: 100px;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .cart-summary h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--secondary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: var(--text-sub);
            font-size: 0.95rem;
        }

        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--secondary);
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .total span:last-child { color: var(--primary); }

        .checkout-btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            text-align: center;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
            background: #e55e2d;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
        }
        .empty-cart h2 { color: var(--secondary); margin-bottom: 1rem; }
        .empty-cart p { color: var(--text-sub); margin-bottom: 2rem; }
        .btn-outline {
            border: 1px solid var(--border-color);
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-sub);
            transition: all 0.2s;
        }
        .btn-outline:hover { background: #f8f8f8; border-color: #ccc; }
        
        .btn-primary {
             background: var(--primary);
             color: white;
             padding: 0.8rem 1.5rem;
             border-radius: 50px;
             text-decoration: none;
             font-weight: 600;
             display: inline-block;
             transition: all 0.3s;
        }
        .btn-primary:hover {
            background:#e55e2d;
            transform:translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        @media (max-width: 768px) {
            .cart-layout { grid-template-columns: 1fr; }
            .cart-item { flex-direction: column; gap: 1rem; }
            .item-image { width: 100%; height: 200px; }
            .item-actions { flex-direction: row; justify-content: space-between; width: 100%; align-items: center; }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    <div class="container">
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added anything to your cart yet.</p>
                <a href="index.php" class="btn-primary">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Layout -->
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-items-container">
                    <div class="cart-header">
                        <h2>Shopping Cart (<?php echo $total_items; ?>)</h2>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Clear entire cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn-outline">Clear Cart</button>
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
                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($item['market_name']); ?>
                                </div>
                                <span class="item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                <div class="item-price">₹<?php echo number_format($item['price'], 2); ?></div>
                                <div style="margin-top: 5px; font-size: 0.85rem; color: #666;">
                                    <?php echo $item['stock'] > 0 ? "In Stock" : "<span style='color:red'>Out of stock</span>"; ?>
                                </div>
                                <div class="item-subtotal" id="item-subtotal-<?php echo $item['cart_id']; ?>">
                                    Subtotal: ₹<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <div class="quantity-control">
                                    <button type="button" onclick="updateItemQuantity(this, <?php echo $item['cart_id']; ?>, -1)">−</button>
                                    <input type="text" id="qty-<?php echo $item['cart_id']; ?>" value="<?php echo $item['quantity']; ?>" readonly>
                                    <button type="button" onclick="updateItemQuantity(this, <?php echo $item['cart_id']; ?>, 1, <?php echo $item['stock']; ?>)">+</button>
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <button type="submit" class="btn-remove" onclick="return confirm('Remove this item?')">
                                        <i class="fas fa-trash-alt"></i> Remove
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
                    
                    <div id="summary-shipping-msg" style="margin-bottom: 1rem;">
                    <?php if ($subtotal >= 1000): ?>
                        <div style="color: #2E7D32; font-size: 0.85rem; text-align: center; background: #E8F5E9; padding: 5px; border-radius: 5px;">
                            🎉 Free shipping applied!
                        </div>
                    <?php else: ?>
                        <div style="color: #E65100; font-size: 0.85rem; text-align: center; background: #FFF3E0; padding: 5px; border-radius: 5px;">
                            Add ₹<?php echo number_format(1000 - $subtotal, 2); ?> for free shipping
                        </div>
                    <?php endif; ?>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total to Pay</span>
                        <span id="summary-total">₹<?php echo number_format($grand_total, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="checkout-btn">
                        Proceed to Checkout
                    </a>

                    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 0.85rem;">
                        <div style="display:flex; justify-content:center; gap:10px; margin-bottom:10px; font-size:1.5rem;">
                             <i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard"></i> <i class="fas fa-money-bill-wave"></i>
                        </div>
                        Secure Checkout & Easy Returns
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
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
                        msgDiv.innerHTML = '<div style="color: #2E7D32; font-size: 0.85rem; text-align: center; background: #E8F5E9; padding: 5px; border-radius: 5px;">🎉 Free shipping applied!</div>';
                    } else {
                        msgDiv.innerHTML = '<div style="color: #E65100; font-size: 0.85rem; text-align: center; background: #FFF3E0; padding: 5px; border-radius: 5px;">Add ₹' + data.data.remaining + ' for free shipping</div>';
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