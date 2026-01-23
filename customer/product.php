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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #2D3436;
            --bg-color: #FAFAFA;
            --card-bg: #FFFFFF;
            --text-main: #333333;
            --text-sub: #666666;
            --border-color: #EEEEEE;
            --shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Breadcrumb */
        .breadcrumb {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 1.5rem;
            color: var(--text-sub);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover { text-decoration: underline; }

        /* Product Detail Container */
        .product-detail {
            max-width: 1200px;
            margin: 0 auto 3rem;
            padding: 0 1.5rem;
        }

        .product-main {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(0,0,0,0.03);
        }

        /* Product Image */
        .product-image-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .main-product-image {
            width: 100%;
            height: 450px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }
        
        .main-product-image:hover { transform: scale(1.02); }

        /* Product Info */
        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .product-category-badge {
            display: inline-block;
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            width: fit-content;
            text-transform: uppercase;
        }

        .product-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--secondary);
            line-height: 1.2;
        }

        .product-rating-section {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .rating-stars { color: #FFC107; font-size: 1.2rem; }
        .rating-text { color: var(--text-sub); font-size: 0.9rem; font-weight: 500; }

        .product-price-section {
            margin: 1rem 0;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .product-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .currency {
            font-size: 1.5rem;
            color: var(--text-sub);
            font-weight: 500;
        }

        .product-stock {
            margin-top: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .stock-available { color: #4CAF50; }
        .stock-unavailable { color: #F44336; }

        .product-description {
            color: var(--text-sub);
            line-height: 1.8;
            margin-bottom: 1rem;
        }
        
        .product-description h3 {
            color: var(--secondary);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        /* Quantity Selector */
        .quantity-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .quantity-section label { font-weight: 600; font-size: 1rem; color: var(--secondary); }

        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid var(--border-color);
            border-radius: 50px;
            overflow: hidden;
            background: #F9FAFB;
        }

        .quantity-btn {
            background: transparent;
            border: none;
            padding: 0.6rem 1.2rem;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--secondary);
            transition: all 0.2s;
        }

        .quantity-btn:hover { background: #eee; }

        .quantity-input {
            border: none;
            text-align: center;
            width: 50px;
            font-size: 1rem;
            font-weight: 600;
            background: transparent;
            color: var(--secondary);
        }
        
        .quantity-input:focus { outline: none; }

        /* Action Buttons */
        .product-actions {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            flex: 2;
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:hover {
            background: #e55e2d;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }
        
        .btn-primary:active { transform: translateY(0); }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: white;
            color: var(--secondary);
            border: 1px solid var(--border-color);
            flex: 1;
        }

        .btn-secondary:hover {
            background: #f8f8f8;
            border-color: #ddd;
        }

        /* Market Info */
        .market-info-box {
            margin-top: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .market-avatar {
             width: 50px; 
             height: 50px; 
             border-radius: 50%; 
             background: #eee;
             display: flex;
             align-items: center;
             justify-content: center;
             font-size: 1.5rem;
        }
        
        .market-details h4 {
            font-size: 1rem;
            color: var(--secondary);
            margin-bottom: 2px;
        }
        
        .market-details p {
            font-size: 0.85rem;
            color: var(--text-sub);
        }

        .market-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        /* Related Products */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .related-product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: var(--shadow);
            text-decoration: none;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .related-product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .related-product-info { padding: 1.2rem; }

        .related-product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .related-product-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Reviews */
        .reviews-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .review-item {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem 0;
        }

        .review-item:last-child { border-bottom: none; }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .reviewer-name { font-weight: 600; color: var(--secondary); }
        .review-date { color: var(--text-sub); font-size: 0.85rem; }
        .review-rating { color: #FFC107; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .review-text { color: var(--text-sub); line-height: 1.6; }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            font-weight: 500;
            text-align: center;
        }
        .alert.show { display: block; animation: fadeIn 0.3s; }
        .alert-success { background: #E8F5E9; color: #2E7D32; }
        .alert-error { background: #FFEBEE; color: #C62828; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .product-main { grid-template-columns: 1fr; padding: 1.5rem; gap: 2rem; }
            .product-title { font-size: 1.8rem; }
            .product-price { font-size: 2rem; }
            .product-actions { flex-direction: column; }
            .related-products-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        }
        @media (max-width: 480px) {
             .related-products-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>

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
                <div>
                     <span class="product-category-badge">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>
                    <h1 class="product-title" style="margin-top:10px;">
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </h1>
                </div>

                <div class="product-rating-section">
                    <span class="rating-stars">
                        <?php 
                        $rating = $product['rating'];
                        echo $rating > 0 ? str_repeat('⭐', round($rating)) : '☆☆☆☆☆';
                        ?>
                    </span>
                    <span class="rating-text">
                        <?php echo number_format($rating, 1); ?> (<?php echo count($reviews); ?> reviews)
                    </span>
                </div>

                <div class="product-price-section">
                    <div class="product-price">
                        <span class="currency">₹</span><?php echo number_format($product['price'], 2); ?>
                    </div>
                    <div class="product-stock">
                        <?php if ($product['stock'] > 0): ?>
                            <span class="stock-available">
                                <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="stock-unavailable">
                                <i class="fas fa-times-circle"></i> Currently Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($product['has_details'] && $product['details']): ?>
                    <div class="product-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['details'])); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Quantity Selector -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="quantity-section">
                        <label>Quantity</label>
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
                            <i class="fas fa-shopping-bag"></i> Add to Cart
                        </button>
                    </div>
                <?php else: ?>
                    <div class="product-actions">
                        <button class="btn btn-primary" disabled style="background:#ccc; box-shadow:none;">
                            Out of Stock
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Market Info -->
                <div class="market-info-box">
                    <div class="market-avatar">
                         <i class="fas fa-store" style="color:#ccc;"></i>
                    </div>
                    <div class="market-details">
                        <h4>Sold by <a href="market.php?id=<?php echo $product['market_id']; ?>" class="market-link"><?php echo htmlspecialchars($product['market_name']); ?></a></h4>
                        <p>
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($product['market_location']); ?> • 
                            ⭐ <?php echo number_format($product['market_rating'], 1); ?> Rating
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (count($related_products) > 0): ?>
            <div class="related-products-section">
                <h2 class="section-title"><i class="fas fa-layer-group" style="color:var(--primary);"></i> Similar Products</h2>
                <div class="related-products-grid">
                    <?php foreach ($related_products as $related): ?>
                        <a href="product.php?id=<?php echo $related['product_id']; ?>" class="related-product-card">
                            <?php if ($related['product_image']): ?>
                                <?php
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
                <h2 class="section-title"><i class="fas fa-comments" style="color:var(--primary);"></i> Customer Reviews</h2>
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
                    const badge = document.getElementById('customerCartCount');
                    if (data.success && badge) {
                         badge.textContent = data.data.count;
                         if(data.data.count > 0) badge.style.display = 'flex';
                    }
                })
                .catch(err => console.log('Cart update skipped'));
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
            const alertBox = document.getElementById('alertMessage');
            
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
                if (data.success) {
                    showAlert('success', '✓ Product added to cart successfully!');
                    updateCartCount();
                } else {
                    showAlert('error', '✗ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Failed to add product to cart');
            });
        }

        function showAlert(type, message) {
            const alertBox = document.getElementById('alertMessage');
            alertBox.className = 'alert show ' + (type === 'success' ? 'alert-success' : 'alert-error');
            alertBox.textContent = message;
            
            // Scroll to alert
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

            setTimeout(() => {
                alertBox.className = 'alert';
            }, 3000);
        }
    </script>
</body>
</html>