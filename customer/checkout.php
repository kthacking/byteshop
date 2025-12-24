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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            color: white;
            font-size: 1.8rem;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            transition: opacity 0.3s;
        }

        .navbar a:hover {
            opacity: 0.8;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-top: 2rem;
        }

        .checkout-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #667eea;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            color: #333;
        }

        .cart-item-market {
            font-size: 0.85rem;
            color: #888;
        }

        .cart-item-price {
            font-weight: 700;
            color: #667eea;
        }

        .order-summary {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
            border-top: 2px solid #e0e0e0;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .empty-cart {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-cart-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
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
    <nav class="navbar">
        <div class="navbar-content">
            <h1>🛒 ByteShop</h1>
            <div>
                <a href="index.php">Home</a>
                <a href="cart.php">Cart</a>
                <a href="orders.php">My Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 style="font-size: 2rem; color: #333;">Checkout</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($cart_empty): ?>
            <div class="checkout-section">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty!</h3>
                    <p style="margin: 1rem 0;">Add some products to proceed with checkout.</p>
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
                                        <div style="margin-top: 0.25rem;">Qty: <?php echo $item['quantity']; ?></div>
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
                                    <span style="color: #27ae60; font-weight: 600;">FREE</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total Amount</span>
                                    <span>₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary">
                                Place Order
                            </button>

                            <a href="cart.php" class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;">
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