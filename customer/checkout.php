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
            $error_message = "Failed to place order. Please try again! " . $e->getMessage();
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
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

        .container > h2 {
            font-size: 1.8rem;
            color: var(--secondary);
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .checkout-section {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.03);
            margin-bottom: 2rem;
        }
        
        .checkout-section:last-child { margin-bottom: 0; }

        .section-title {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            color: var(--secondary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #fff;
            color: var(--text-main);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }

        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }

        .cart-item:last-child { border-bottom: none; }

        .cart-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .cart-item-info { flex: 1; }

        .cart-item-name {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.95rem;
        }

        .cart-item-market {
            font-size: 0.8rem;
            color: var(--text-sub);
            margin-top: 3px;
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--primary);
            font-size: 1rem;
        }

        .order-summary-box {
            background: #F9F9F9;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1rem;
            border: 1px solid #eee;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-size: 0.95rem;
            color: var(--text-sub);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--secondary);
            border-top: 1px solid #ddd;
            padding-top: 1rem;
            margin-top: 1rem;
            margin-bottom: 0;
        }
        .summary-row.total span:last-child { color: var(--primary); }

        .btn {
            display: block;
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            margin-top: 1.5rem;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            background: #e55e2d;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-secondary {
            background: #f1f1f1;
            color: var(--text-sub);
            margin-top: 1rem;
        }

        .btn-secondary:hover {
            background: #e1e1e1;
            color: var(--secondary);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #e57373;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem;
        }
        .empty-cart-icon { font-size: 4rem; color: #ddd; margin-bottom: 1rem; }

        @media (max-width: 900px) {
            .checkout-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    <div class="container">
        <h2><i class="fas fa-lock" style="color:#2ecc71; font-size: 0.8em; margin-right:10px;"></i> Secure Checkout</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($cart_empty): ?>
            <div class="checkout-section">
                <div class="empty-cart">
                    <div class="empty-cart-icon"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Your cart is empty!</h3>
                    <p>Add some products to proceed with checkout.</p>
                    <a href="index.php" class="btn btn-primary" style="width: auto; display: inline-block;">Browse Markets</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="checkout-grid">
                    <!-- Delivery Details -->
                    <div class="checkout-section">
                        <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Delivery Details</h3>

                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>" required placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="10-digit mobile number" maxlength="10" required>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3" placeholder="House No., Building, Street" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" required placeholder="City">
                            </div>

                            <div class="form-group">
                                <label>State</label>
                                <input type="text" name="state" required placeholder="State">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Pincode</label>
                            <input type="text" name="pincode" placeholder="6-digit pincode" maxlength="6" required>
                        </div>

                        <h3 class="section-title" style="margin-top: 2rem;"><i class="fas fa-credit-card"></i> Payment</h3>
                        
                        <div class="form-group">
                            <label>Select Payment Method</label>
                            <select name="payment_method" required>
                                <option value="COD">💵 Cash on Delivery (COD)</option>
                                <option value="UPI">📱 UPI (GPay/PhonePe)</option>
                                <option value="Card">💳 Debit/Credit Card</option>
                            </select>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div>
                        <div class="checkout-section" style="position: sticky; top: 100px;">
                            <h3 class="section-title"><i class="fas fa-receipt"></i> Order Summary</h3>

                            <div style="max-height: 300px; overflow-y: auto; padding-right: 5px;">
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
                                            onerror="this.src='../assets/images/default-product.jpg'">
                                        <div class="cart-item-info">
                                            <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <div class="cart-item-market"><?php echo htmlspecialchars($item['market_name']); ?></div>
                                            <div style="font-size: 0.8rem; color: #888;">Qty: <?php echo $item['quantity']; ?></div>
                                        </div>
                                        <div class="cart-item-price">₹<?php echo number_format($item['subtotal'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="order-summary-box">
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
                            
                            <div style="margin-top: 1rem; font-size: 0.8rem; color: #666; text-align: center;">
                                By placing this order, you agree to our Terms of Service.
                            </div>

                            <button type="submit" name="place_order" class="btn btn-primary">
                                Confirm & Place Order
                            </button>

                            <a href="cart.php" class="btn btn-secondary">
                                Return to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>