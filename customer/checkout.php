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
    INNER JOIN markets m ON p.market_id = m.market_id
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
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            font-size: 14.4px; /* 90% of 16px */
        }

        .container {
            max-width: 1080px; /* 90% of 1200px */
            margin: 1.8rem auto; /* 90% of 2rem */
            padding: 0 0.9rem; /* 90% of 1rem */
        }

        .container > h2 {
            font-size: 1.8rem; /* 90% of 2rem */
            color: #e0e0e0;
            font-weight: 700;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 360px; /* 90% of 400px */
            gap: 1.8rem; /* 90% of 2rem */
            margin-top: 1.8rem; /* 90% of 2rem */
        }

        .checkout-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 1.8rem; /* 90% of 2rem */
            border-radius: 14.4px; /* 90% of 16px */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            border: 1px solid #2a2a2a;
        }

        .section-title {
            font-size: 1.35rem; /* 90% of 1.5rem */
            margin-bottom: 1.35rem; /* 90% of 1.5rem */
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            border-bottom: 2px solid #2a2a2a;
            padding-bottom: 0.45rem; /* 90% of 0.5rem */
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 1.35rem; /* 90% of 1.5rem */
        }

        .form-group label {
            display: block;
            margin-bottom: 0.45rem; /* 90% of 0.5rem */
            font-weight: 600;
            color: #909090;
            font-size: 0.81rem; /* 90% of 0.9rem */
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.675rem; /* 90% of 0.75rem */
            border: 2px solid #2a2a2a;
            border-radius: 7.2px; /* 90% of 8px */
            font-size: 0.9rem; /* 90% of 1rem */
            transition: border-color 0.3s;
            background: #0f0f0f;
            color: #e0e0e0;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.1); /* 90% of 3px */
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #5a5a5a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.9rem; /* 90% of 1rem */
        }

        .cart-item {
            display: flex;
            gap: 0.9rem; /* 90% of 1rem */
            padding: 0.9rem; /* 90% of 1rem */
            border-bottom: 1px solid #2a2a2a;
            align-items: center;
             background: #3a3a3a;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item img {
            width: 54px; /* 90% of 60px */
            height: 54px; /* 90% of 60px */
            object-fit: cover;
            border-radius: 7.2px; /* 90% of 8px */
            border: 1px solid #2a2a2a;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            color: #e0e0e0;
            font-size: 0.9rem;
        }

        .cart-item-market {
            font-size: 0.765rem; /* 90% of 0.85rem */
            color: #909090;
            margin-top: 0.225rem; /* 90% of 0.25rem */
        }

        .cart-item-price {
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.08rem; /* 90% of 1.2rem */
        }

        .order-summary {
            background: #0f0f0f;
            padding: 1.35rem; /* 90% of 1.5rem */
            border-radius: 7.2px; /* 90% of 8px */
            margin-top: 0.9rem; /* 90% of 1rem */
            border: 1px solid #2a2a2a;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.9rem; /* 90% of 1rem */
            font-size: 0.9rem; /* 90% of 1rem */
            color: #b0b0b0;
        }

        .summary-row.total {
            font-size: 1.17rem; /* 90% of 1.3rem */
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            border-top: 2px solid #2a2a2a;
            padding-top: 0.9rem; /* 90% of 1rem */
            margin-top: 0.9rem; /* 90% of 1rem */
        }

        .btn {
            padding: 0.9rem 1.8rem; /* 90% of 1rem 2rem */
            border: none;
            border-radius: 7.2px; /* 90% of 8px */
            font-size: 0.9rem; /* 90% of 1rem */
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
            margin-top: 0.9rem; /* 90% of 1rem */
        }

        .btn-primary:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 4.5px 13.5px rgba(255, 107, 53, 0.4); /* 90% scale */
        }

        .btn-secondary {
            background: #2a2a2a;
            color: #b0b0b0;
        }

        .btn-secondary:hover {
            background: #3a3a3a;
            color: #e0e0e0;
        }

        .alert {
            padding: 0.9rem; /* 90% of 1rem */
            border-radius: 7.2px; /* 90% of 8px */
            margin-bottom: 0.9rem; /* 90% of 1rem */
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border-left: 3.6px solid #f44336; /* 90% of 4px */
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border-left: 3.6px solid #4caf50; /* 90% of 4px */
        }

        .empty-cart {
            text-align: center;
            padding: 2.7rem; /* 90% of 3rem */
            color: #707070;
        }

        .empty-cart-icon {
            font-size: 3.6rem; /* 90% of 4rem */
            margin-bottom: 0.9rem; /* 90% of 1rem */
        }

        .empty-cart h3 {
            color: #909090;
            margin-bottom: 0.9rem; /* 90% of 1rem */
            font-size: 1.62rem; /* 90% of 1.8rem */
        }

        .empty-cart p {
            margin: 0.9rem 0; /* 90% of 1rem */
            color: #707070;
            font-size: 0.9rem;
        }

        .cart-item > div:last-child {
            margin-top: 0.225rem; /* 90% of 0.25rem */
            color: #909090;
            font-size: 0.81rem; /* 90% of 0.9rem */
        }

        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
        <?php include '../includes/customer_header.php'; ?>
    <div class="container">
        <h2>Checkout</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($cart_empty): ?>
            <div class="checkout-section">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty!</h3>
                    <p>Add some products to proceed with checkout.</p>
                    <a href="index.php" class="btn btn-primary" style="width: auto;">Browse Markets</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="checkout-grid">
                    <!-- Delivery Details -->
                    <div class="checkout-section">
                        <h3 class="section-title">Delivery Details</h3>

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
                            <label>Payment Method *</label>
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
                            <h3 class="section-title">Order Summary</h3>

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
                                        <div style="margin-top: 0.225rem;">Qty: <?php echo $item['quantity']; ?></div>
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
                                    <span style="color: #4caf50; font-weight: 600;">FREE</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total Amount</span>
                                    <span>₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary">
                                Place Order
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