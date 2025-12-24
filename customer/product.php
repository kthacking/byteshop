<?php
/**
 * ByteShop - Product Details Page
 * 
 * Displays detailed information about a single product
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_customer(); // Only customers can access

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get product details with market information
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        m.market_name,
        m.market_id,
        m.location as market_location,
        m.market_category,
        m.rating as market_rating,
        u.name as owner_name
    FROM products p
    JOIN markets m ON p.market_id = m.market_id
    JOIN users u ON m.owner_id = u.user_id
    WHERE p.product_id = ? AND p.status = 'active' AND m.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php?error=product_not_found');
    exit;
}

// Get related products from the same market (same category)
$stmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE market_id = ? 
    AND category = ? 
    AND product_id != ? 
    AND status = 'active' 
    LIMIT 4
");
$stmt->execute([$product['market_id'], $product['category'], $product_id]);
$related_products = $stmt->fetchAll();

// Get product reviews (if reviews table exists)
$stmt = $pdo->prepare("
    SELECT r.*, u.name as customer_name 
    FROM reviews r
    JOIN users u ON r.customer_id = u.user_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - ByteShop</title>
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

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        /* Breadcrumb */
        .breadcrumb {
            max-width: 1400px;
            margin: 2rem auto 1rem;
            padding: 0 2rem;
            color: #666;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Product Detail Container */
        .product-detail {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .product-main {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 2rem;
        }

        /* Product Image Section */
        .product-image-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .main-product-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }

        .image-placeholder {
            width: 100%;
            height: 500px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        /* Product Info Section */
        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .product-category-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            width: fit-content;
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            line-height: 1.3;
        }

        .product-rating-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .rating-stars {
            color: #ffa502;
            font-size: 1.5rem;
        }

        .rating-text {
            color: #666;
            font-size: 1.1rem;
        }

        .product-price-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
          .product-image {
            width: 100%;
            height:100% ;
            object-fit: cover;
            border-radius: 10px;
        }

        .product-price {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
        }

        .product-stock {
            margin-top: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .stock-available {
            color: #27ae60;
        }

        .stock-unavailable {
            color: #e74c3c;
        }

        .product-description {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            line-height: 1.8;
            color: #555;
        }

        .product-description h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        /* Quantity Selector */
        .quantity-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-section label {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .quantity-btn {
            background: #f0f0f0;
            border: none;
            padding: 0.7rem 1.2rem;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .quantity-btn:hover {
            background: #e0e0e0;
        }

        .quantity-input {
            border: none;
            text-align: center;
            width: 60px;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 0.7rem 0;
        }

        /* Action Buttons */
        .product-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            flex: 2;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #333;
            flex: 1;
        }

        .btn-secondary:hover {
            background: #bdc3c7;
        }

        /* Market Info Section */
        .market-info-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #764ba2;
        }

        .market-info-box h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        .market-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
            color: #555;
        }

        .market-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .market-link:hover {
            text-decoration: underline;
        }

        /* Related Products Section */
        .related-products-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .related-product-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .related-product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .related-product-info {
            padding: 1rem;
        }

        .related-product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .related-product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
        }

        /* Reviews Section */
        .reviews-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        .review-item {
            border-bottom: 1px solid #e0e0e0;
            padding: 1.5rem 0;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .reviewer-name {
            font-weight: 600;
            color: #333;
        }

        .review-date {
            color: #999;
            font-size: 0.9rem;
        }

        .review-rating {
            color: #ffa502;
            margin-bottom: 0.5rem;
        }

        .review-text {
            color: #555;
            line-height: 1.6;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert.show {
            display: block;
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

        /* Responsive */
        @media (max-width: 768px) {
            .product-main {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .product-title {
                font-size: 1.8rem;
            }

            .product-price {
                font-size: 2rem;
            }

            .product-actions {
                flex-direction: column;
            }

            .related-products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">🛒 ByteShop</a>
            <nav class="nav-links">
                <a href="index.php">Markets</a>
                <a href="orders.php">My Orders</a>
                <a href="cart.php" class="cart-icon">
                    🛒 Cart
                    <span class="cart-count" id="cartCount">0</span>
                </a>
                <span>Hi, <?php echo htmlspecialchars(get_user_name()); ?></span>
                <a href="../logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php">Home</a> / 
        <a href="market.php?id=<?php echo $product['market_id']; ?>">
            <?php echo htmlspecialchars($product['market_name']); ?>
        </a> / 
        <span><?php echo htmlspecialchars($product['product_name']); ?></span>
    </div>

    <!-- Alert Messages -->
    <div class="product-detail">
        <div id="alertMessage" class="alert"></div>
    </div>

    <!-- Product Detail -->
    <div class="product-detail">
        <div class="product-main">
            <!-- Product Image Section -->
            <div class="product-image-section">
                <?php if ($product['product_image']): ?>
                    <?php
                    // Detect if image is URL or local file
                    $is_url = preg_match('/^https?:\/\//i', $product['product_image']);
                    $product_image_src = $is_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']);
                    ?>
                    <img src="<?php echo $product_image_src; ?>" 
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                         class="main-product-image"
                         onerror="this.src='../assets/images/default-product.jpg'">
                <?php else: ?>
                    <img src="../assets/images/default-product.jpg" alt="Product" class="main-product-image">
                <?php endif; ?>
            </div>

            <!-- Product Info Section -->
            <div class="product-info-section">
                <span class="product-category-badge">
                    <?php echo htmlspecialchars($product['category']); ?>
                </span>

                <h1 class="product-title">
                    <?php echo htmlspecialchars($product['product_name']); ?>
                </h1>

                <div class="product-rating-section">
                    <span class="rating-stars">
                        <?php 
                        $rating = $product['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '⭐' : '☆';
                        }
                        ?>
                    </span>
                    <span class="rating-text">
                        <?php echo number_format($rating, 1); ?> out of 5
                    </span>
                </div>

                <div class="product-price-section">
                    <div class="product-price">
                        ₹<?php echo number_format($product['price'], 2); ?>
                    </div>
                    <div class="product-stock">
                        <?php if ($product['stock'] > 0): ?>
                            <span class="stock-available">
                                ✓ In Stock (<?php echo $product['stock']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="stock-unavailable">
                                ✗ Currently Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($product['has_details'] && $product['details']): ?>
                    <div class="product-description">
                        <h3>📝 Product Details</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['details'])); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Quantity Selector -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="quantity-section">
                        <label>Quantity:</label>
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="decrementQuantity()">−</button>
                            <input type="number" id="quantity" class="quantity-input" 
                                   value="1" min="1" max="<?php echo $product['stock']; ?>" readonly>
                            <button class="quantity-btn" onclick="incrementQuantity()">+</button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="product-actions">
                        <button class="btn btn-primary" onclick="addToCart()">
                            🛒 Add to Cart
                        </button>
                        <a href="cart.php" class="btn btn-secondary">
                            View Cart
                        </a>
                    </div>
                <?php else: ?>
                    <div class="product-actions">
                        <button class="btn btn-primary" disabled>
                            Out of Stock
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Market Info -->
                <div class="market-info-box">
                    <h3>🏪 Sold By</h3>
                    <div class="market-info-item">
                        <strong>Market:</strong>
                        <a href="market.php?id=<?php echo $product['market_id']; ?>" class="market-link">
                            <?php echo htmlspecialchars($product['market_name']); ?>
                        </a>
                    </div>
                    <div class="market-info-item">
                        <span>📍 <?php echo htmlspecialchars($product['market_location']); ?></span>
                    </div>
                    <div class="market-info-item">
                        <span>🏷️ <?php echo htmlspecialchars($product['market_category']); ?></span>
                    </div>
                    <div class="market-info-item">
                        <span>⭐ <?php echo number_format($product['market_rating'], 1); ?> Market Rating</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (count($related_products) > 0): ?>
            <div class="related-products-section">
                <h2 class="section-title">🔍 Related Products</h2>
                <div class="related-products-grid">
                    <?php foreach ($related_products as $related): ?>
                        <a href="product.php?id=<?php echo $related['product_id']; ?>" 
                           class="related-product-card" style="text-decoration: none; color: inherit;">
                            <?php if ($related['product_image']): ?>
                                <?php
                                // Detect if image is URL or local file
                                $is_related_url = preg_match('/^https?:\/\//i', $related['product_image']);
                                $related_image_src = $is_related_url ? htmlspecialchars($related['product_image']) : '../uploads/products/' . htmlspecialchars($related['product_image']);
                                ?>
                                <img src="<?php echo $related_image_src; ?>" 
                                     alt="<?php echo htmlspecialchars($related['product_name']); ?>" 
                                     class="related-product-image"
                                     onerror="this.src='../assets/images/default-product.jpg'">
                            <?php else: ?>
                                <img src="../assets/images/default-product.jpg" alt="Product" class="related-product-image">
                            <?php endif; ?>
                            <div class="related-product-info">
                                <div class="related-product-name">
                                    <?php echo htmlspecialchars($related['product_name']); ?>
                                </div>
                                <div class="related-product-price">
                                    ₹<?php echo number_format($related['price'], 2); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reviews Section -->
        <?php if (count($reviews) > 0): ?>
            <div class="reviews-section">
                <h2 class="section-title">⭐ Customer Reviews</h2>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <span class="reviewer-name">
                                <?php echo htmlspecialchars($review['customer_name']); ?>
                            </span>
                            <span class="review-date">
                                <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                            </span>
                        </div>
                        <div class="review-rating">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $review['rating'] ? '⭐' : '☆';
                            }
                            ?>
                        </div>
                        <div class="review-text">
                            <?php echo htmlspecialchars($review['review_text']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const productId = <?php echo $product_id; ?>;
        const maxStock = <?php echo $product['stock']; ?>;

        // Load cart count on page load
        window.addEventListener('DOMContentLoaded', updateCartCount);

        function updateCartCount() {
            fetch('../api/cart.php?action=count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('cartCount').textContent = data.data.count;
                    }
                });
        }

        function incrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            let currentValue = parseInt(quantityInput.value);
            if (currentValue < maxStock) {
                quantityInput.value = currentValue + 1;
            }
        }

        function decrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            let currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        }

        function addToCart() {
            const quantity = parseInt(document.getElementById('quantity').value);
            
            fetch('../api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                const alertDiv = document.getElementById('alertMessage');
                if (data.success) {
                    alertDiv.className = 'alert alert-success show';
                    alertDiv.textContent = '✓ ' + quantity + ' item(s) added to cart successfully!';
                    updateCartCount();
                    
                    // Reset quantity to 1
                    document.getElementById('quantity').value = 1;
                } else {
                    alertDiv.className = 'alert alert-error show';
                    alertDiv.textContent = '✗ ' + data.message;
                }
                
                // Hide alert after 3 seconds
                setTimeout(() => {
                    alertDiv.classList.remove('show');
                }, 3000);

                // Scroll to top to show alert
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.getElementById('alertMessage');
                alertDiv.className = 'alert alert-error show';
                alertDiv.textContent = '✗ Failed to add product to cart';
                setTimeout(() => {
                    alertDiv.classList.remove('show');
                }, 3000);
            });
        }
    </script>
</body>
</html>