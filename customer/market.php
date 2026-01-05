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
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #0a0a0a;
        color: #e0e0e0;
        font-size: 14.4px; /* 90% of 16px */
    }

    /* Header */
    .header {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        padding: 0.9rem 0; /* 90% of 1rem */
        box-shadow: 0 1.8px 9px rgba(0, 0, 0, 0.3); /* 90% scale */
    }

    .header-content {
        max-width: 1260px; /* 90% of 1400px */
        margin: 0 auto;
        padding: 0 1.8rem; /* 90% of 2rem */
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        font-size: 1.62rem; /* 90% of 1.8rem */
        font-weight: bold;
        text-decoration: none;
        color: white;
    }

    .nav-links {
        display: flex;
        gap: 1.8rem; /* 90% of 2rem */
        align-items: center;
    }

    .nav-links a {
        color: white;
        text-decoration: none;
        transition: opacity 0.3s;
        font-size: 0.9rem;
    }

    .nav-links a:hover {
        opacity: 0.8;
    }

    .cart-icon {
        position: relative;
    }

    .cart-count {
        position: absolute;
        top: -7.2px; /* 90% of -8px */
        right: -7.2px; /* 90% of -8px */
        background: #f44336;
        color: white;
        border-radius: 50%;
        width: 18px; /* 90% of 20px */
        height: 18px; /* 90% of 20px */
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.63rem; /* 90% of 0.7rem */
        font-weight: bold;
    }

    /* Filters Section */
    .filters-section {
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        padding: 1.35rem; /* 90% of 1.5rem */
        margin: 1.8rem auto; /* 90% of 2rem */
        max-width: 1260px; /* 90% of 1400px */
        border-radius: 14.4px; /* 90% of 16px */
        box-shadow: 0 3.6px 14.4px rgba(0, 0, 0, 0.4); /* 90% scale */
        border: 1px solid #2a2a2a;
    }

    .filters-form {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
        gap: 0.9rem; /* 90% of 1rem */
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        margin-bottom: 0.45rem; /* 90% of 0.5rem */
        font-weight: 600;
        color: #909090;
        font-size: 0.81rem; /* 90% of 0.9rem */
        text-transform: uppercase;
        letter-spacing: 0.45px; /* 90% of 0.5px */
    }

    .form-group input,
    .form-group select {
        padding: 0.63rem; /* 90% of 0.7rem */
        border: 1px solid #2a2a2a;
        border-radius: 7.2px; /* 90% of 8px */
        font-size: 0.855rem; /* 90% of 0.95rem */
        background: #0f0f0f;
        color: #e0e0e0;
        transition: all 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #ff6b35;
        box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.1); /* 90% of 3px */
    }

    .btn {
        padding: 0.63rem 1.35rem; /* 90% of 0.7rem 1.5rem */
        border: none;
        border-radius: 7.2px; /* 90% of 8px */
        cursor: pointer;
        font-size: 0.855rem; /* 90% of 0.95rem */
        transition: all 0.3s;
        font-weight: 600;
    }

    .btn-primary {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 5.4px 18px rgba(255, 107, 53, 0.4); /* 90% scale */
    }

    .btn-secondary {
        background: #2a2a2a;
        color: white;
    }

    .btn-secondary:hover {
        background: #3a3a3a;
    }

    /* Results Info */
    .results-info {
        max-width: 1260px; /* 90% of 1400px */
        margin: 0.9rem auto; /* 90% of 1rem */
        padding: 0 1.8rem; /* 90% of 2rem */
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #909090;
        font-size: 0.9rem;
    }

    /* Products Grid */
    .products-container {
        max-width: 100%;
        margin: 1.8rem auto; /* 90% of 2rem */
        padding: 0 1.8rem; /* 90% of 2rem */
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(252px, 1fr)); /* 90% of 280px */
        gap: 1.8rem; /* 90% of 2rem */
    }

    .product-card {
        background: black;
        border-radius: 14.4px; /* 90% of 16px */
        overflow: hidden;
        max-height: 495px; /* 90% of 550px */
        position: relative;
        box-shadow: 0 3.6px 14.4px rgba(226, 152, 16, 0); /* 90% scale */
        transition: transform 0.3s, box-shadow 0.3s;
        border: 1px solid #2a2a2a;
    }

    .product-card:hover {
        transform: translateY(-4.5px); /* 90% of -5px */
        box-shadow: 0 7.2px 27px rgba(255, 145, 0, 0.96); /* 90% scale */
    }

    .product-image {
        width: 100%;
        height: 225px; /* 90% of 250px */
        object-fit: cover;
    }

    .product-content {
        padding: 1.35rem; /* 90% of 1.5rem */
    }

    .product-category {
        display: inline-block;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        top: -54px; /* 90% of -60px */
        left: 180px; /* 90% of 200px */
        position: relative;
        padding: 0.27rem 0.72rem; /* 90% of 0.3rem 0.8rem */
        border-radius: 18px 0px 18px 0px; /* 90% of 20px */
        font-size: 0.72rem; /* 90% of 0.8rem */
        margin-bottom: 0.45rem; /* 90% of 0.5rem */
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.45px; /* 90% of 0.5px */
    }

    .product-name {
        font-size: 1.08rem; /* 90% of 1.2rem */
        font-weight: 600;
        margin: 0.45rem 0; /* 90% of 0.5rem */
        color: #ffffffff;
        position: relative;
        top: -54px; /* 90% of -60px */
        max-width: 100%;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        display: -webkit-box;
        word-break: break-word;
        overflow: hidden;
    }

    .product-price {
        font-size: 1.35rem; 
        font-weight: bold;
        background: linear-gradient(135deg, #98f34eff 0%, rgba(255, 115, 1, 1) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0.45rem 0; 
        position: relative;
        top: -67px; 
    }

     .product-rating {
            color: #ffa502;
            margin: 0.5rem 0;
            position: relative;
             top:25px;
             right:-8px;
             font-weight: 600;

        }

        .product-stock {
            font-size: 0.9rem;
            margin: 0.5rem 0;
             position: relative;
             top:20px;
             font-weight: 600;
        }

        .stock-in {
            color: #27ae60;
        }

        .stock-out {
            color: #e74d3cdc;
            opacity: 0;
        }
    /* .product-rating {
        color: #eeb141ff;
        margin: 0.45rem 0; 
        position: relative;
        transform: rotate(-90deg);
        transform-origin: left top;
        left: -22.5px; 
        top: -94.2px; 
        z-index: 12;
        font-size: 0.9rem;
    }

    .product-stock {
        font-size: 0.81rem;
        margin: 0.45rem 0; 
        position: relative;
        transform: rotate(-90deg);
        transform-origin: left top;
        left: -19.8px; 
        top: -55px; 
        z-index: 12;
        font-weight: 600;
    } */

    .stock-in {
        color: #4caf50;
    }

    .stock-out {
        color: #f44336;
    }

    .product-details {
        background: #0f0f0f;
        padding: 0.72rem; /* 90% of 0.8rem */
        border-radius: 7.2px; /* 90% of 8px */
        margin: 0.9rem 0; /* 90% of 1rem */
        max-height: 81px; /* 90% of 90px */
        overflow: hidden;
        text-align: justify;
        font-size: 0.765rem; /* 90% of 0.85rem */
        color: #909090;
        line-height: 1.4;
        position: relative;
        top: -140px; /* 90% of -150px */
        max-width: 100%;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        display: -webkit-box;
        word-break: break-word;
        border: 1px solid #2a2a2a;
    }
/* 
    .cbb {
        height: 126px; 
        width: 100%;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        background-image: url("https://i.pinimg.com/736x/07/ed/1e/07ed1e95ff590083faa79917266969bb.jpg");
        padding: 18px 0 9px 0; 
        position: relative;
        top: -263.7px; 
        transition: transform .2s;
    }

    .cbb:hover {
        transform: scale(1.5);
        height: 135px; 
        top: -274.5px;
        left: 9px; 
    } */

    .product-actions {
        display: flex;
        justify-content: center;
        gap: 18px; /* 90% of 20px */
        position: relative;
        top: -113px; /* 90% of -120px */
        z-index: 10;
        left: 28px; /* 90% of 20px */
    }

    /* Super Styles - Cart Button */
    .btn-cart {
        cursor: pointer;
        border: none;
        background: linear-gradient(135deg, #6e00ff 0%, #ff00bc 100%);
        color: #fff;
        width: 72px; /* 90% of 80px */
        height: 72px; /* 90% of 80px */
        top: -27px; 
        
        border-radius: 50%;
        overflow: hidden;
        position: relative;
        display: grid;
        place-content: center;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        font-weight: 700;
        padding: 0;
        font-size: 9.9px; /* 90% of 11px */
        box-shadow: 0 9px 18px rgba(255, 0, 188, 0.3); /* 90% scale */
    }

    .btn-cart:disabled {
        background: #2a2a2a;
        cursor: not-allowed;
        width: auto;
        height: auto;
        color:#ff6b35;
        padding: 9px 18px; /* 90% of 10px 20px */
        border-radius: 7.2px; /* 90% of 8px */
        top: -3px;
        font-size: 12.6px; /* 90% of 14px */
        box-shadow: none;
    }

    .button__text {
        position: absolute;
        inset: 0;
        animation: text-rotation 8s linear infinite;
    }

    .button__text>span {
        position: absolute;
        transform: rotate(calc(28deg * var(--index)));
        inset: 3.6px; /* 90% of 4px */
        text-shadow: 0 1.8px 4.5px rgba(255, 196, 0, 0.2); /* 90% scale */
    }

    .button__circle {
        position: relative;
        width: 28.8px; /* 90% of 32px */
        height: 28.8px; /* 90% of 32px */
        overflow: hidden;
        background: #fff;
        color: #6e00ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        box-shadow: inset 0 0 4.5px rgba(0, 0, 0, 0.1); /* 90% scale */
    }

    .button__icon {
        width: 10.8px; /* 90% of 12px */
    }

    .button__icon--copy {
        position: absolute;
        transform: translate(-150%, 150%);
    }

    .btn-cart:hover:not(:disabled) {
        background: #111;
        transform: scale(1.1) translateY(-4.5px); /* 90% of -5px */
        box-shadow: 0 13.5px 27px rgba(110, 0, 255, 0.4); /* 90% scale */
    }

    .btn-cart:hover:not(:disabled) .button__circle {
        background: #111;
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

    /* View Button */
    .btn-view {
        width: 45px; /* 90% of 50px */
        height: 45px; /* 90% of 50px */
        border-radius: 50%;
        background: linear-gradient(135deg, #6e00ff 0%, #ff00bc 100%);
        border: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4.5px 13.5px rgba(255, 0, 188, 0.4); /* 90% scale */
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        position: relative;
        top:-4px;
        text-decoration: none;
    }

    .svgIcon {
        width: 10.8px; /* 90% of 12px */
        transition-duration: 0.3s;
    }

    .svgIcon path {
        fill: white;
    }

    .btn-view:hover {
        width: 126px; /* 90% of 140px */
        border-radius: 45px; /* 90% of 50px */
        background: #000;
        align-items: center;
        box-shadow: 0 9px 22.5px rgba(110, 0, 255, 0.5); /* 90% scale */
        transform: scale(1.05);
    }

    .btn-view:hover .svgIcon {
        transition-duration: 0.3s;
        transform: translateY(-200%);
    }

    .btn-view::before {
        position: absolute;
        bottom: -18px; /* 90% of -20px */
        content: "View Details";
        color: white;
        font-size: 0px;
        white-space: nowrap;
    }

    .btn-view:hover::before {
        font-size: 11.7px; /* 90% of 13px */
        opacity: 1;
        bottom: unset;
        transition-duration: 0.3s;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3.6rem 1.8rem; /* 90% of 4rem 2rem */
        color: #707070;
        background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
        border-radius: 14.4px; /* 90% of 16px */
        border: 1px solid #2a2a2a;
    }

    .empty-state h2 {
        font-size: 1.8rem; /* 90% of 2rem */
        margin-bottom: 0.9rem; /* 90% of 1rem */
        color: #909090;
    }

    .empty-state p {
        color: #707070;
        font-size: 0.9rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .filters-form {
            grid-template-columns: 1fr;
        }

        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(225px, 1fr)); /* 90% of 250px */
            gap: 0.9rem; /* 90% of 1rem */
        }
    }
</style>
</head>

<body>
    <?php include '../includes/customer_header.php'; ?>

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
                                ?>
                    </div>

                    <div class="product-stock">
                        <?php if ($product['stock'] > 0): ?>
                        <span class="stock-in">✓ In Stock</span>
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
                style="display: inline-block; margin-top: 0.9rem;">
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