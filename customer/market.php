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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* Light Theme Variables */
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
        background-color: var(--bg-color);
        color: var(--text-main);
        font-size: 14px;
        line-height: 1.5;
    }

    /* Market Header */
    .market-header {
        background: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border-bottom: 1px solid var(--border-color);
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .market-info h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--secondary);
    }

    .market-info p {
        color: var(--text-sub);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Filters Section */
    .filters-section {
        background: white;
        padding: 1.5rem;
        margin: 0 auto 2rem;
        max-width: 1200px;
        border-radius: 12px;
        box-shadow: var(--shadow);
    }

    .filters-form {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        margin-bottom: 0.3rem;
        font-weight: 600;
        color: var(--text-sub);
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    .form-group input,
    .form-group select {
        padding: 0.7rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.9rem;
        background: #F9FAFB;
        color: var(--text-main);
        transition: all 0.3s;
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
    }

    .btn {
        padding: 0.7rem 1.5rem;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: #e55e2d;
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: #F1F1F1;
        color: var(--text-main);
    }

    .btn-secondary:hover {
        background: #e1e1e1;
    }

    /* Results Info */
    .results-info {
        max-width: 1200px;
        margin: 1rem auto;
        padding: 0 1.5rem;
        color: var(--text-sub);
        font-size: 0.9rem;
        font-weight: 500;
    }

    /* Products Grid */
    .products-container {
        max-width: 1200px;
        margin: 0 auto 3rem;
        padding: 0 1.5rem;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 2rem;
    }

    /* Updated Card Style */
    .product-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        position: relative;
        box-shadow: var(--shadow);
        transition: transform 0.3s, box-shadow 0.3s;
        border: 1px solid rgba(0,0,0,0.03);
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }

    .image-wrapper {
        position: relative;
        height: 220px;
        overflow: hidden;
    }

    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }
    
    .product-card:hover .product-image {
        transform: scale(1.1);
    }

    .product-content {
        padding: 1.5rem;
    }

    .product-category {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--primary);
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 0.5rem;
    }

    .product-name {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--secondary);
        margin-bottom: 0.5rem;
    }
    
    .product-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        font-size: 0.85rem;
        color: var(--text-sub);
    }

    .stock-badge {
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .stock-in { background: #E8F5E9; color: #2E7D32; }
    .stock-out { background: #FFEBEE; color: #C62828; }

    .product-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }

    /* Cart Button Animation (Preserved logic, updated style) */
    .btn-cart {
        cursor: pointer;
        border: none;
        background: var(--primary);
        color: #fff;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(255, 107, 53, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    .btn-cart:hover {
        width: 120px;
        border-radius: 25px;
        transform: scale(1.05);
    }
    
    .btn-cart .icon {
        font-size: 1.1rem;
        transition: 0.2s;
    }
    
    .btn-cart .text {
        position: absolute;
        opacity: 0;
        font-size: 0.9rem;
        white-space: nowrap;
        font-weight: 600;
        transform: translateX(10px);
        transition: 0.2s;
    }
    
    .btn-cart:hover .icon {
        transform: translateX(-40px);
        opacity: 0;
    }
    
    .btn-cart:hover .text {
        opacity: 1;
        transform: translateX(0);
    }

    .btn-cart:disabled {
        background: #ccc;
        cursor: not-allowed;
        box-shadow: none;
        width: 45px !important;
    }
    .btn-cart:disabled:hover { pointer-events: none; }

    .btn-view {
        background: white;
        border: 1px solid var(--border-color);
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-main);
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .btn-view:hover {
        background: #F5F5F5;
        border-color: #ddd;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        color: var(--text-sub);
    }

    @media (max-width: 768px) {
        .filters-form {
            grid-template-columns: 1fr 1fr;
        }
        .products-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .market-header { padding: 1.5rem 0; }
        .market-info h1 { font-size: 1.5rem; }
    }
    
    @media (max-width: 480px) {
        .products-grid { grid-template-columns: 1fr; }
        .filters-form { grid-template-columns: 1fr; }
    }
    </style>
</head>

<body>
    <?php include '../includes/customer_header.php'; ?>

    <!-- Market Header -->
     <div class="market-header">
         <div class="header-container">
             <div class="market-info">
                 <h1><?php echo htmlspecialchars($market['market_name']); ?></h1>
                 <p><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> <?php echo htmlspecialchars($market['location']); ?></p>
             </div>
             <div>
                <!-- Could add market banner or owner info here -->
             </div>
         </div>
     </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" action="" class="filters-form">
            <input type="hidden" name="id" value="<?php echo $market_id; ?>">

            <div class="form-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Product name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Min Price</label>
                <input type="number" name="min_price" min="0" step="0.01" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" placeholder="0">
            </div>

            <div class="form-group">
                <label>Max Price</label>
                <input type="number" name="max_price" min="0" step="0.01" value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" placeholder="Max">
            </div>

            <div class="form-group">
                <label>Stock</label>
                <select name="stock">
                    <option value="">Any</option>
                    <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="out_of_stock" <?php echo $stock_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>

            <div class="form-group">
                <label>Sort By</label>
                <select name="sort">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low-High</option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High-Low</option>
                    <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Top Rated</option>
                </select>
            </div>

            <div style="grid-column: 1 / -1; display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Apply Filters</button>
                <a href="market.php?id=<?php echo $market_id; ?>" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <span>Showing <?php echo count($products); ?> products</span>
    </div>

    <!-- Products Grid -->
    <div class="products-container">
        <?php if (count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="image-wrapper">
                    <?php if ($product['product_image']): ?>
                        <?php
                            $is_url = preg_match('/^https?:\/\//i', $product['product_image']);
                            $product_image_src = $is_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']);
                        ?>
                        <img src="<?php echo $product_image_src; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image" onerror="this.src='../assets/images/default-product.jpg'">
                    <?php else: ?>
                        <img src="../assets/images/default-product.jpg" alt="Product" class="product-image">
                    <?php endif; ?>
                </div>

                <div class="product-content">
                    <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                    <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>

                    <div class="product-meta">
                        <div class="product-rating">
                            <?php 
                                $rating = $product['rating'];
                                echo $rating > 0 ? str_repeat('⭐', round($rating)) : 'No ratings';
                            ?>
                        </div>
                        <div class="product-stock">
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-badge stock-in">In Stock</span>
                            <?php else: ?>
                                <span class="stock-badge stock-out">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="product-actions">
                        <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn-view" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <button class="btn-cart" onclick="addToCart(<?php echo $product['product_id']; ?>)" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                            <span class="text">Add to Cart</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <h2>No Products Found</h2>
            <p>We couldn't find any products matching your criteria.</p>
            <a href="market.php?id=<?php echo $market_id; ?>" class="btn btn-primary" style="margin-top: 1rem;">Clear Filters</a>
        </div>
        <?php endif; ?>
    </div>

<script>
    // Load cart count on page load
    window.addEventListener('DOMContentLoaded', updateCartCount);

    function updateCartCount() {
        // Safe check for element existence
        const cartBadge = document.getElementById('customerCartCount');
        
        fetch('../api/cart.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update header badge if it exists
                    if (cartBadge) {
                        cartBadge.textContent = data.data.count;
                        if(data.data.count > 0) cartBadge.style.display = 'flex';
                    }
                }
            })
            .catch(e => console.log('Cart update skipped'));
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
                    // Create a nice toast or alert
                    const btn = event.currentTarget;
                    if(btn) {
                        const originalContent = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i>';
                        btn.style.background = '#4CAF50';
                        setTimeout(() => {
                            btn.innerHTML = originalContent;
                            btn.style.background = '';
                        }, 2000);
                    }
                    updateCartCount();
                } else {
                    alert('Note: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Connection error');
            });
    }
</script>
</body>
</html>