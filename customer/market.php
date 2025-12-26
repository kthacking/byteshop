<?php
/**
 * ByteShop - Market Products Page
 * 
 * Displays all products from a specific market with search and filter options
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_customer(); // Only customers can access

$market_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get market details
$stmt = $pdo->prepare("
    SELECT m.*, u.name as owner_name 
    FROM markets m 
    JOIN users u ON m.owner_id = u.user_id 
    WHERE m.market_id = ? AND m.status = 'active'
");
$stmt->execute([$market_id]);
$market = $stmt->fetch();

if (!$market) {
    header('Location: index.php?error=market_not_found');
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$category = isset($_GET['category']) ? clean_input($_GET['category']) : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 999999;
$stock_filter = isset($_GET['stock']) ? clean_input($_GET['stock']) : '';
$sort = isset($_GET['sort']) ? clean_input($_GET['sort']) : 'newest';

// Build query
$sql = "SELECT * FROM products WHERE market_id = :market_id AND status = 'active'";
$params = ['market_id' => $market_id];

if ($search) {
    $sql .= " AND product_name LIKE :search";
    $params['search'] = "%$search%";
}

if ($category) {
    $sql .= " AND category = :category";
    $params['category'] = $category;
}

$sql .= " AND price BETWEEN :min_price AND :max_price";
$params['min_price'] = $min_price;
$params['max_price'] = $max_price;

if ($stock_filter === 'in_stock') {
    $sql .= " AND stock > 0";
} elseif ($stock_filter === 'out_of_stock') {
    $sql .= " AND stock = 0";
}

// Sorting
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY rating DESC";
        break;
    case 'name':
        $sql .= " ORDER BY product_name ASC";
        break;
    default:
        $sql .= " ORDER BY created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get all categories from this market (for filter dropdown)
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE market_id = ? AND status = 'active' ORDER BY category");
$stmt->execute([$market_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get price range
$stmt = $pdo->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE market_id = ? AND status = 'active'");
$stmt->execute([$market_id]);
$price_range = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($market['market_name']); ?> - ByteShop</title>
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
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

    /* Market Header */
    .market-header {
        background: white;
        padding: 2rem;
        margin: 2rem auto;
        max-width: 1400px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        display: flex;
        gap: 2rem;
        align-items: center;
    }

    .market-image {
        width: 150px;
        height: 150px;
        border-radius: 10px;
        object-fit: cover;
    }

    .market-info h1 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: #667eea;
    }

    .market-meta {
        display: flex;
        gap: 2rem;
        margin-top: 1rem;
        color: #666;
    }

    .market-meta span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .rating-stars {
        color: #ffa502;
    }

    /* Filters Section */
    .filters-section {
        background: white;
        padding: 1.5rem;
        margin: 2rem auto;
        max-width: 1400px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .filters-form {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #555;
        font-size: 0.9rem;
    }

    .form-group input,
    .form-group select {
        padding: 0.7rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.95rem;
    }

    .btn {
        padding: 0.7rem 1.5rem;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.95rem;
        transition: all 0.3s;
    }

    .btn-primary {
        background: #667eea;
        color: white;
    }

    .btn-primary:hover {
        background: #5568d3;
    }

    .btn-secondary {
        background: #95a5a6;
        color: white;
    }

    .btn-secondary:hover {
        background: #7f8c8d;
    }

    /* Results Info */
    .results-info {
        max-width: 1400px;
        margin: 1rem auto;
        padding: 0 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #666;
    }

    /* Products Grid */
    .products-container {
        max-width: 100%;
        margin: 2rem auto;
        padding: 0 2rem;
        
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 2rem;
    }

    .product-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        max-height: 550px;
        position: relative;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
    }

    .product-image {
        width: 100%;
        height: 250px;
        object-fit: cover;
    }

    .product-content {
        padding: 1.5rem;
    }

    .product-category {
        display: inline-block;
        background: #667eea;
        color: white;
        top: -60px;
        left: 200px;
        position: relative;
        padding: 0.3rem 0.8rem;
        border-radius: 20px 0px 20px 0px;
        font-size: 0.8rem;
        margin-bottom: 0.5rem;
    }

    .product-name {
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0.5rem 0;
        color: #333;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        display: -webkit-box;
        position: relative;
        top: -60px;
        overflow: hidden;

    }

    .product-price {
        font-size: 1.5rem;
        font-weight: bold;
        color: #667eea;
        margin: 0.5rem 0;
        position: relative;
        top: -60px;
    }

    .product-rating {
        color: #eeb141ff;
        margin: 0.5rem 0;
        position: relative;
        transform: rotate(-90deg);
        transform-origin: left top;
        left: -25px;
        top: -38px;
        z-index: 12;

    }



    .product-stock {
        font-size: 0.9rem;
        margin: 0.5rem 0;
        position: relative;
        transform: rotate(-90deg);
        transform-origin: left top;
        left: -22px;
        top: 5px;
        z-index: 12;
        font-weight: 600;
    }

    .stock-in {
        color: #27ae60;

    }

    .stock-out {
        color: #e74c3c;
    }

    .product-details {
        background: #f8f9fa;
        padding: 0.8rem;
        border-radius: 5px;
        margin: 1rem 0;
        max-height: 90px;
        overflow: hidden;
        text-align: justify;
        font-size: 0.85rem;
        color: #666;
        line-height: 1.4;
        position: relative;
        top: -150px;

    }

    .cbb {
        height: 140px;
        width: 100%;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        background-image: url("https://i.pinimg.com/736x/07/ed/1e/07ed1e95ff590083faa79917266969bb.jpg");
        padding: 20px 0 10px 0;
        position: relative;
        top: -293px;
        transition: transform .2s;
    }

    .cbb:hover {
        transform: scale(1.5);
        height: 150px;
        top: -305px;
        left: 10px;
    }

    .product-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .product-actions {
        display: flex;
        justify-content: center;
        gap: 20px;
        position: relative;
        top: -120px;
        z-index: 10;
        left: 20px;

    }

    /* Based on Uiverse.io by Creatlydev - Resized */
  /* SUPER STYLES */
.btn-cart {
    cursor: pointer;
    border: none;
    /* Changed to a Super Gradient */
    background: linear-gradient(135deg, #6e00ff 0%, #ff00bc 100%);
    color: #fff;
    width: 80px;
    height: 80px;
    top: -30px;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    display: grid;
    place-content: center;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Bouncy transition */
    font-weight: 700; /* Made text bolder */
    padding: 0;
    font-size: 11px;
    /* Added a glow effect */
    box-shadow: 0 10px 20px rgba(255, 0, 188, 0.3); 
}

/* Logic to handle Disabled/Out of Stock */
.btn-cart:disabled {
    background: #bdc3c7; /* Flat grey */
    cursor: not-allowed;
    width: auto;
    height: auto;
    padding: 10px 20px;
    border-radius: 8px;
    top: 0;
    font-size: 14px;
    box-shadow: none; /* No glow for disabled */
}

.button__text {
    position: absolute;
    inset: 0;
    animation: text-rotation 8s linear infinite;
}

.button__text>span {
    position: absolute;
    transform: rotate(calc(28deg * var(--index)));
    inset: 4px;
    text-shadow: 0 2px 5px rgba(0,0,0,0.2); /* Slight shadow on text for readability */
}

.button__circle {
    position: relative;
    width: 32px;
    height: 32px;
    overflow: hidden;
    background: #fff;
    /* Text color inside the circle matches the gradient vibe */
    color: #6e00ff; 
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
}

.button__icon {
    width: 12px;
}

.button__icon--copy {
    position: absolute;
    transform: translate(-150%, 150%);
}

/* Hover Effects */
.btn-cart:hover:not(:disabled) {
    /* Change background to deep dark on hover */
    background: #111; 
    transform: scale(1.1) translateY(-5px); /* Grows and floats up */
    box-shadow: 0 15px 30px rgba(110, 0, 255, 0.4); /* Stronger glow on hover */
}

.btn-cart:hover:not(:disabled) .button__circle {
    background: #111; /* Circle turns dark */
    color: #fff; /* Icon turns white */
}

.btn-cart:hover:not(:disabled) .button__icon {
    color: #fff;
}

.btn-cart:hover:not(:disabled) .button__icon:first-child {
    transition: transform 0.3s ease-in-out;
    transform: translate(150%, -150%);
}

.btn-cart:hover:not(:disabled) .button__icon--copy {
    transition: transform 0.3s ease-in-out 0.1s;
    transform: translate(0);
}

@keyframes text-rotation {
    to {
        rotate: 360deg;
    }
}

    /* Based on Uiverse.io by vinodjangid07 */
   /* SUPER STYLE - VIEW BUTTON */
.btn-view {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    /* Super Gradient (matches cart button) */
    background: linear-gradient(135deg, #6e00ff 0%, #ff00bc 100%);
    border: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Glowing shadow instead of outline */
    box-shadow: 0 5px 15px rgba(255, 0, 188, 0.4);
    cursor: pointer;
    /* Bouncy animation */
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    position: relative;
    text-decoration: none;
}

.svgIcon {
    width: 12px;
    transition-duration: 0.3s;
}

.svgIcon path {
    fill: white;
}

.btn-view:hover {
    width: 140px;
    border-radius: 50px;
    /* Deep Black on hover for premium contrast */
    background: #000;
    align-items: center;
    /* Stronger glow on hover */
    box-shadow: 0 10px 25px rgba(110, 0, 255, 0.5);
    transform: scale(1.05); /* Slight pop */
}

.btn-view:hover .svgIcon {
    transition-duration: 0.3s;
    transform: translateY(-200%);
}

.btn-view::before {
    position: absolute;
    bottom: -20px;
    content: "View Details";
    color: white;
    font-size: 0px;
    white-space: nowrap; /* Ensures text doesn't wrap awkwardly during animation */
}

.btn-view:hover::before {
    font-size: 13px;
    opacity: 1;
    bottom: unset;
    transition-duration: 0.3s;
}

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #666;
    }

    .empty-state h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .filters-form {
            grid-template-columns: 1fr;
        }

        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .market-header {
            flex-direction: column;
            text-align: center;
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

    <!-- Market Header -->
    <div class="market-header">
        <?php if ($market['market_image']): ?>
        <?php
         // Detect if image is URL or local file
         $is_url = preg_match('/^https?:\/\//i', $market['market_image']);
         $image_src = $is_url ? $market['market_image'] : '../uploads/markets/' . $market['market_image'];
         ?>

        <img src="<?php echo htmlspecialchars($image_src); ?>" class="market-image"
            alt="<?php echo htmlspecialchars($market['market_name']); ?>"
            onerror="this.src='../assets/images/placeholder.jpg'">
        <?php else: ?>
        <img src="../assets/images/default-market.jpg" alt="Market" class="market-image">
        <?php endif; ?>

        <div class="market-info">
            <h1><?php echo htmlspecialchars($market['market_name']); ?></h1>
            <p><?php echo htmlspecialchars($market['description']); ?></p>
            <div class="market-meta">
                <span>📍 <?php echo htmlspecialchars($market['location']); ?></span>
                <span>🏷️ <?php echo htmlspecialchars($market['market_category']); ?></span>
                <span class="rating-stars">
                    ⭐ <?php echo number_format($market['rating'], 1); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" action="" class="filters-form">
            <input type="hidden" name="id" value="<?php echo $market_id; ?>">

            <div class="form-group">
                <label>Search Products</label>
                <input type="text" name="search" placeholder="Search by product name..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"
                        <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Min Price</label>
                <input type="number" name="min_price" min="0" step="0.01"
                    value="<?php echo $min_price > 0 ? $min_price : ''; ?>" placeholder="₹0">
            </div>

            <div class="form-group">
                <label>Max Price</label>
                <input type="number" name="max_price" min="0" step="0.01"
                    value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" placeholder="₹999999">
            </div>

            <div class="form-group">
                <label>Stock</label>
                <select name="stock">
                    <option value="">All</option>
                    <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>
                        In Stock
                    </option>
                    <option value="out_of_stock" <?php echo $stock_filter === 'out_of_stock' ? 'selected' : ''; ?>>
                        Out of Stock
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Sort By</label>
                <select name="sort">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High
                    </option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to
                        Low</option>
                    <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="market.php?id=<?php echo $market_id; ?>" class="btn btn-secondary">Reset</a>
        </form>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <span><?php echo count($products); ?> products found</span>
    </div>

    <!-- Products Grid -->
    <div class="products-container">
        <?php if (count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <?php if ($product['product_image']): ?>
                <?php
                            // Detect if image is URL or local file
                            $is_url = preg_match('/^https?:\/\//i', $product['product_image']);
                            $product_image_src = $is_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']);
                            ?>
                <img src="<?php echo $product_image_src; ?>"
                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image"
                    onerror="this.src='../assets/images/default-product.jpg'">
                <?php else: ?>
                <img src="../assets/images/default-product.jpg" alt="Product" class="product-image">
                <?php endif; ?>

                <div class="product-content">
                    <span class="product-category">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>

                    <h3 class="product-name">
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </h3>

                    <div class="product-price">
                        ₹<?php echo number_format($product['price'], 2); ?>
                    </div>

                    <div class="product-rating">
                        <?php 
                                $rating = $product['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '⭐' : '☆';
                                }
                                #echo ' (' . number_format($rating, 1) . ')';
                                ?>
                    </div>

                    <div class="product-stock">
                        <?php if ($product['stock'] > 0): ?>
                        <span class="stock-in">✓ In Stock <?php #echo $product['stock']; ?> </span>
                        <?php else: ?>
                        <span class="stock-out">✗ Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($product['has_details'] && $product['details']): ?>
                    <div class="product-details">
                        <?php echo htmlspecialchars($product['details']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="product-actions">
                        <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)"
                            <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>

                            <?php if ($product['stock'] > 0): ?>
                            <p class="button__text">
                                <span style="--index: 0;">A</span>
                                <span style="--index: 1;">D</span>
                                <span style="--index: 2;">D</span>
                                <span style="--index: 3;"> </span>
                                <span style="--index: 4;">T</span>
                                <span style="--index: 5;">O</span>
                                <span style="--index: 6;"> </span>
                                <span style="--index: 7;">C</span>
                                <span style="--index: 8;">A</span>
                                <span style="--index: 9;">R</span>
                                <span style="--index: 10;">T</span>
                                <span style="--index: 11;"> </span>
                                <span style="--index: 12;">•</span>
                            </p>

                            <div class="button__circle">
                                <svg viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg"
                                    class="button__icon" width="14">
                                    <path
                                        d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                                        fill="currentColor"></path>
                                </svg>
                                <svg viewBox="0 0 14 15" fill="none" width="14" xmlns="http://www.w3.org/2000/svg"
                                    class="button__icon button__icon--copy">
                                    <path
                                        d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                                        fill="currentColor"></path>
                                </svg>
                            </div>
                            <?php else: ?>
                            Out of Stock
                            <?php endif; ?>

                        </button>
                        <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn-view">

                            <svg class="svgIcon" viewBox="0 0 576 512">
                                <path
                                    d="M288 32c-80.8 0-145.5 46.8-192.8 80.6C48.3 156 16 205.8 16 256c0 50.2 32.3 100 79.2 143.4C142.5 433.2 207.2 480 288 480s145.5-46.8 192.8-80.6c46.9-33.5 79.2-83.3 79.2-133.4 0-50.2-32.3-100-79.2-143.4C433.5 78.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1-288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z">
                                </path>
                            </svg>
                            
                        </a>
                    </div>

                </div>
                <div class="cbb">

                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <h2>No Products Found</h2>
            <p>Try adjusting your filters or search criteria.</p>
            <a href="market.php?id=<?php echo $market_id; ?>" class="btn btn-primary"
                style="display: inline-block; margin-top: 1rem;">
                Clear Filters
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
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

    function addToCart(productId) {
        fetch('../api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Product added to cart!');
                    updateCartCount();
                } else {
                    alert('✗ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to add product to cart');
            });
    }
    </script>
</body>

</html>