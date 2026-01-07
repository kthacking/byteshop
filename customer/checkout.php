<?php
/**
 * ByteShop - Checkout Page
 * Customer can review cart and place order
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require customer login
require_customer();

$customer_id = get_user_id();
$error_message = '';
$success_message = '';

// Fetch cart items with product and market details
$cart_query = "
    SELECT 
        c.cart_id,
        c.quantity,
        p.product_id,
        p.product_name,
        p.product_image,
        p.price,
        p.stock,
        m.market_name,
        m.market_id,
        (c.quantity * p.price) as subtotal
    FROM cart c
    INNER JOIN products p ON c.product_id = p.product_id
    INNER JOIN markets m ON Dark color scheme: Deep blacks (#0a0a0a, #1a1a1a) with subtle gradients Orange accent color: Vibrant orange gradient (#ff6b35 to #f7931e) matching the image Modern cards: Glassmorphism-style cards with borders and hover effects Enhanced typography: Better font weights and sizing for hierarchy Professional badges: Updated status badges with transparency and borders Dark charts: Chart.js configured for dark theme with proper grid colors Smooth animations: Hover effects with shadows and transforms this also do to change ui theme and dont remove class names and functions only do to change ui and styles and dont remove php code and dont add new data or dummy datas give og code and When the page is set to 100%, everything looks too big, but when viewed at 90% zoom, it looks much better. So please reduce the size of all elements in my existing web code to 90%. Decrease font sizes, spacing, card sizes, heights, widths, and overall scale proportionally, without changing any existing class names, IDs, or functionality.p.market_id = m.market_id
    WHERE c.customer_id = :customer_id AND p.status = 'active'
    ORDER BY c.added_at DESC
";

$stmt = $pdo->prepare($cart_query);
$stmt->execute(['customer_id' => $customer_id]);
$cart_items = $stmt->fetchAll();

// Calculate total
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['subtotal'];
}

// Check if cart is empty
$cart_empty = empty($cart_items);

// Fetch user details for pre-filling form
$user_query = "SELECT name, email, phone FROM users WHERE user_id = :user_id";
$stmt = $pdo->prepare($user_query);
$stmt->execute(['user_id' => $customer_id]);
$user = $stmt->fetch();

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    // Validate inputs
    $full_name = clean_input($_POST['full_name']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $city = clean_input($_POST['city']);
    $state = clean_input($_POST['state']);
    $pincode = clean_input($_POST['pincode']);
    $payment_method = clean_input($_POST['payment_method']);
    
    // Validation
    if (empty($full_name) || empty($phone) || empty($address) || empty($city) || empty($state) || empty($pincode)) {
        $error_message = "All fields are required!";
    } elseif (strlen($phone) != 10 || !is_numeric($phone)) {
        $error_message = "Please enter a valid 10-digit phone number!";
    } elseif (strlen($pincode) != 6 || !is_numeric($pincode)) {
        $error_message = "Please enter a valid 6-digit pincode!";
    } elseif ($cart_empty) {
        $error_message = "Your cart is empty!";
    } else {
        // Start transaction
        try {
            $pdo->beginTransaction();
            
            // Check stock availability
            $stock_error = false;
            foreach ($cart_items as $item) {
                if ($item['quantity'] > $item['stock']) {
                    $stock_error = true;
                    $error_message = "Product '{$item['product_name']}' has insufficient stock!";
                    break;
                }
            }
            
            if (!$stock_error) {
                // Create full delivery address
                $delivery_address = "$address, $city, $state - $pincode. Contact: $phone";
                
                // Insert order
                $order_query = "
                    INSERT INTO orders (customer_id, total_amount, order_status, delivery_address, payment_method)
                    VALUES (:customer_id, :total_amount, 'placed', :delivery_address, :payment_method)
                ";
                $stmt = $pdo->prepare($order_query);
                $stmt->execute([
                    'customer_id' => $customer_id,
                    'total_amount' => $total_amount,
                    'delivery_address' => $delivery_address,
                    'payment_method' => $payment_method
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                // Insert order items and update stock
                foreach ($cart_items as $item) {
                    // Insert order item
                    $order_item_query = "
                        INSERT INTO order_items (order_id, product_id, market_id, quantity, price, subtotal)
                        VALUES (:order_id, :product_id, :market_id, :quantity, :price, :subtotal)
                    ";
                    $stmt = $pdo->prepare($order_item_query);
                    $stmt->execute([
                        'order_id' => $order_id,
                        'product_id' => $item['product_id'],
                        'market_id' => $item['market_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['subtotal']
                    ]);
                    
                    // Update product stock
                    $update_stock_query = "
                        UPDATE products 
                        SET stock = stock - :quantity 
                        WHERE product_id = :product_id
                    ";
                    $stmt = $pdo->prepare($update_stock_query);
                    $stmt->execute([
                        'quantity' => $item['quantity'],
                        'product_id' => $item['product_id']
                    ]);
                }
                
                // Clear cart
                $clear_cart_query = "DELETE FROM cart WHERE customer_id = :customer_id";
                $stmt = $pdo->prepare($clear_cart_query);
                $stmt->execute(['customer_id' => $customer_id]);
                
                // Commit transaction
                $pdo->commit();
                
                // Redirect to order confirmation
                header("Location: orders.php?order_placed=$order_id");
                exit;
            } else {
                $pdo->rollBack();
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to place order. Please try again!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ByteShop</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        background-attachment: fixed;
        color: #e0e0e0;
    }

    .container {
        max-width: 1080px;
        margin: 1.8rem auto;
        padding: 0 0.9rem;
    }

    .container h2 {
        font-size: 1.8rem;
        color: #e0e0e0;
        margin-bottom: 1.8rem;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 800;
    }

    .checkout-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 1.8rem;
        margin-top: 1.8rem;
    }

    .checkout-section {
        background: linear-gradient(145deg, #1a1a1a 0%, #0f0f0f 100%);
        padding: 1.8rem;
        border-radius: 10.8px;
        box-shadow: 0 1.8px 9px rgba(0,0,0,0.5);
        border: 1px solid rgba(255, 107, 53, 0.2);
        backdrop-filter: blur(9px);
    }

    .section-title {
        font-size: 1.35rem;
        margin-bottom: 1.35rem;
        color: #ff6b35;
        border-bottom: 1.8px solid rgba(255, 107, 53, 0.3);
        padding-bottom: 0.45rem;
        font-weight: 700;
        letter-spacing: 0.27px;
    }

    .form-group {
        margin-bottom: 1.35rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.45rem;
        font-weight: 600;
        color: #ccc;
        font-size: 0.855rem;
        letter-spacing: 0.27px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.675rem;
        border: 1.8px solid rgba(255, 107, 53, 0.3);
        border-radius: 7.2px;
        font-size: 0.9rem;
        transition: all 0.3s;
        background: rgba(26, 26, 26, 0.6);
        color: #e0e0e0;
        backdrop-filter: blur(9px);
    }

    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: #666;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #ff6b35;
        box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.2);
        background: rgba(26, 26, 26, 0.8);
    }

    .form-group textarea {
        resize: vertical;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.9rem;
    }

    .cart-item {
        display: flex;
        gap: 0.9rem;
        padding: 0.9rem;
        border-bottom: 1px solid rgba(255, 107, 53, 0.15);
        align-items: center;
        transition: all 0.3s;
        border-radius: 7.2px;
    }

    .cart-item:hover {
        background: rgba(255, 107, 53, 0.05);
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .cart-item img {
        width: 54px;
        height: 54px;
        object-fit: cover;
        border-radius: 7.2px;
        border: 1.8px solid rgba(255, 107, 53, 0.3);
    }

    .cart-item-info {
        flex: 1;
    }

    .cart-item-name {
        font-weight: 600;
        color: #e0e0e0;
        font-size: 0.9rem;
        margin-bottom: 0.27rem;
    }

    .cart-item-market {
        font-size: 0.765rem;
        color: #999;
    }

    .cart-item-price {
        font-weight: 700;
        color: #ff6b35;
        font-size: 1.08rem;
    }

    .order-summary {
        background: rgba(26, 26, 26, 0.6);
        padding: 1.35rem;
        border-radius: 7.2px;
        margin-top: 0.9rem;
        border: 1px solid rgba(255, 107, 53, 0.2);
        backdrop-filter: blur(9px);
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.9rem;
        font-size: 0.9rem;
        color: #ccc;
    }

    .summary-row.total {
        font-size: 1.17rem;
        font-weight: 700;
        color: #ff6b35;
        border-top: 1.8px solid rgba(255, 107, 53, 0.3);
        padding-top: 0.9rem;
        margin-top: 0.9rem;
    }

    .btn {
        padding: 0.9rem 1.8rem;
        border: none;
        border-radius: 7.2px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        width: 100%;
        margin-top: 0.9rem;
        box-shadow: 0 3.6px 18px rgba(255, 107, 53, 0.4);
        font-weight: 700;
        letter-spacing: 0.27px;
    }

    .btn-primary:hover {
        transform: translateY(-1.8px);
        box-shadow: 0 4.5px 22.5px rgba(255, 107, 53, 0.6);
    }

    .btn-secondary {
        background: rgba(255, 107, 53, 0.1);
        color: #ff6b35;
        border: 1.8px solid rgba(255, 107, 53, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 107, 53, 0.2);
        border-color: #ff6b35;
    }

    .alert {
        padding: 0.9rem;
        border-radius: 7.2px;
        margin-bottom: 0.9rem;
        font-size: 0.855rem;
        backdrop-filter: blur(9px);
    }

    .alert-error {
        background: rgba(255, 60, 60, 0.15);
        color: #ff6666;
        border-left: 3.6px solid #ff6666;
        border: 1.8px solid rgba(255, 60, 60, 0.4);
    }

    .alert-success {
        background: rgba(76, 175, 80, 0.15);
        color: #66dd88;
        border-left: 3.6px solid #66dd88;
        border: 1.8px solid rgba(76, 175, 80, 0.4);
    }

    .empty-cart {
        text-align: center;
        padding: 2.7rem;
        color: #999;
    }

    .empty-cart-icon {
        font-size: 3.6rem;
        margin-bottom: 0.9rem;
        filter: drop-shadow(0 0 18px rgba(255, 107, 53, 0.3));
    }

    .empty-cart h3 {
        color: #e0e0e0;
        margin-bottom: 0.9rem;
        font-size: 1.35rem;
    }

    .empty-cart p {
        margin: 0.9rem 0;
        color: #999;
        font-size: 0.9rem;
    }

    /* Scrollbar styling */
    ::-webkit-scrollbar {
        width: 9px;
    }

    ::-webkit-scrollbar-track {
        background: #0a0a0a;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        border-radius: 4.5px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #ff6b35;
    }

    /* Enhanced focus states */
    select option {
        background: #1a1a1a;
        color: #e0e0e0;
    }

    @media (max-width: 968px) {
        .checkout-grid {
            grid-template-columns: 1fr;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }

        .container {
            padding: 0 0.9rem;
        }

        .checkout-section {
            padding: 1.35rem;
        }
    }
</style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="container">
        <h2>🛒 Checkout</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($cart_empty): ?>
            <div class="checkout-section">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty!</h3>
                    <p style="margin: 0.9rem 0;">Add some products to proceed with checkout.</p>
                    <a href="index.php" class="btn btn-primary" style="width: auto;">Browse Markets</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="checkout-grid">
                    <!-- Delivery Details -->
                    <div class="checkout-section">
                        <h3 class="section-title">📍 Delivery Details</h3>

                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Phone Number *</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="10-digit mobile number" maxlength="10" required>
                        </div>

                        <div class="form-group">
                            <label>Delivery Address *</label>
                            <textarea name="address" rows="3" placeholder="House No., Building, Street" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>City *</label>
                                <input type="text" name="city" required>
                            </div>

                            <div class="form-group">
                                <label>State *</label>
                                <input type="text" name="state" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Pincode *</label>
                            <input type="text" name="pincode" placeholder="6-digit pincode" maxlength="6" required>
                        </div>

                        <div class="form-group">
                            <label>💳 Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="COD">Cash on Delivery (COD)</option>
                                <option value="UPI">UPI</option>
                                <option value="Card">Debit/Credit Card</option>
                            </select>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div>
                        <div class="checkout-section">
                            <h3 class="section-title">📋 Order Summary</h3>

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
                                    <div class="cart-item-info">
                                        <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <div class="cart-item-market">From: <?php echo htmlspecialchars($item['market_name']); ?></div>
                                        <div style="margin-top: 0.225rem; font-size: 0.81rem; color: #999;">Qty: <?php echo $item['quantity']; ?></div>
                                    </div>
                                    <div class="cart-item-price">₹<?php echo number_format($item['subtotal'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>

                            <div class="order-summary">
                                <div class="summary-row">
                                    <span>Subtotal (<?php echo count($cart_items); ?> items)</span>
                                    <span>₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Delivery Charges</span>
                                    <span style="color: #44cc77; font-weight: 600;">FREE</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total Amount</span>
                                    <span>₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary">
                                🎉 Place Order
                            </button>

                            <a href="cart.php" class="btn btn-secondary" style="width: 100%; margin-top: 0.45rem;">
                                ← Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>