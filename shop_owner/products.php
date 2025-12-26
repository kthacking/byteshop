<?php
/**
 * ByteShop - Shop Owner Product Management (SECURITY + PRG PATTERN)
 * 
 * Security Features:
 * - Strict owner_id and market_id validation
 * - No cross-owner data access
 * - Session-based authentication
 * 
 * PRG Pattern (Post-Redirect-Get):
 * - Prevents duplicate submissions on page refresh
 * - Clean URL after form submission
 * - Better user experience
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Ensure only shop owners can access
require_shop_owner();

$owner_id = get_user_id();

// CRITICAL: Get owner's market with strict owner_id check
$stmt = $pdo->prepare("SELECT * FROM markets WHERE owner_id = ? LIMIT 1");
$stmt->execute([$owner_id]);
$market = $stmt->fetch();

if (!$market) {
    // No market found - redirect to market creation
    header("Location: my_market.php?error=no_market");
    exit();
}

$market_id = $market['market_id'];

// ===================================================================
// POST-REDIRECT-GET PATTERN IMPLEMENTATION
// ===================================================================
// All POST operations redirect after completion to prevent duplicate submissions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ADD PRODUCT
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $product_name = clean_input($_POST['product_name']);
        $price = floatval($_POST['price']);
        $category = clean_input($_POST['category']);
        $stock = intval($_POST['stock']);
        $details = clean_input($_POST['details']);
        $has_details = !empty($details) ? 1 : 0;
        
        $error = false;
        $image_name = null;
        $image_source = isset($_POST['image_source']) ? $_POST['image_source'] : 'file';
        
        // Option 1: File Upload
        if ($image_source === 'file' && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['product_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed) && $_FILES['product_image']['size'] <= 5000000) { // 5MB limit
                $new_filename = uniqid() . '_' . time() . '.' . $ext;
                $upload_path = '../uploads/products/' . $new_filename;
                
                // Create directory if not exists
                if (!is_dir('../uploads/products/')) {
                    mkdir('../uploads/products/', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                    $image_name = $new_filename;
                } else {
                    $error = true;
                    header("Location: products.php?error=upload_failed");
                    exit();
                }
            } else {
                $error = true;
                header("Location: products.php?error=invalid_image");
                exit();
            }
        }
        
        // Option 2: Image URL
        elseif ($image_source === 'url' && !empty($_POST['product_image_url'])) {
            $image_url = trim($_POST['product_image_url']);
            
            // Validate URL format
            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                $error = true;
                header("Location: products.php?error=invalid_url");
                exit();
            } elseif (!preg_match('/^https?:\/\//i', $image_url)) {
                $error = true;
                header("Location: products.php?error=url_protocol");
                exit();
            } elseif (!preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $image_url)) {
                $error = true;
                header("Location: products.php?error=url_not_image");
                exit();
            } else {
                // Sanitize and save URL
                $image_name = filter_var($image_url, FILTER_SANITIZE_URL);
            }
        }

        if (!$error) {
            // SECURITY: Use owner's market_id from session, not from form
            $stmt = $pdo->prepare("
                INSERT INTO products 
                (market_id, product_name, product_image, price, category, stock, details, has_details) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $market_id,  // From session, not form input
                $product_name, 
                $image_name, 
                $price, 
                $category, 
                $stock, 
                $details, 
                $has_details
            ])) {
                // PRG: Redirect after successful insert
                header("Location: products.php?success=added");
                exit();
            } else {
                header("Location: products.php?error=add_failed");
                exit();
            }
        }
    }
    
    // EDIT PRODUCT
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $product_id = intval($_POST['product_id']);
        $product_name = clean_input($_POST['product_name']);
        $price = floatval($_POST['price']);
        $category = clean_input($_POST['category']);
        $stock = intval($_POST['stock']);
        $details = clean_input($_POST['details']);
        $has_details = !empty($details) ? 1 : 0;
        
        // CRITICAL: Verify product belongs to this owner's market
        $stmt = $pdo->prepare("
            SELECT p.* FROM products p
            INNER JOIN markets m ON p.market_id = m.market_id
            WHERE p.product_id = ? 
            AND m.owner_id = ?
            AND p.market_id = ?
        ");
        $stmt->execute([$product_id, $owner_id, $market_id]);
        $current_product = $stmt->fetch();
        
        if (!$current_product) {
            // Access denied - not owner's product
            header("Location: products.php?error=access_denied");
            exit();
        }
        
        $image_name = $current_product['product_image'];
        $image_source = isset($_POST['image_source']) ? $_POST['image_source'] : 'file';
        
        // Option 1: File Upload
        if ($image_source === 'file' && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['product_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed) && $_FILES['product_image']['size'] <= 5000000) {
                $new_filename = uniqid() . '_' . time() . '.' . $ext;
                $upload_path = '../uploads/products/' . $new_filename;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                    // Delete old image if it's a local file (not URL)
                    if ($image_name && !preg_match('/^https?:\/\//i', $image_name) && file_exists('../uploads/products/' . $image_name)) {
                        unlink('../uploads/products/' . $image_name);
                    }
                    $image_name = $new_filename;
                }
            }
        }
        
        // Option 2: Image URL
        elseif ($image_source === 'url' && !empty($_POST['product_image_url'])) {
            $image_url = trim($_POST['product_image_url']);
            
            // Validate URL
            if (filter_var($image_url, FILTER_VALIDATE_URL) && 
                preg_match('/^https?:\/\//i', $image_url) && 
                preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $image_url)) {
                
                // Delete old local image if exists (not URL)
                if ($image_name && !preg_match('/^https?:\/\//i', $image_name) && file_exists('../uploads/products/' . $image_name)) {
                    unlink('../uploads/products/' . $image_name);
                }
                
                $image_name = filter_var($image_url, FILTER_SANITIZE_URL);
            }
        }
        
        // SECURITY: Double-check ownership in UPDATE query
        $stmt = $pdo->prepare("
            UPDATE products p
            INNER JOIN markets m ON p.market_id = m.market_id
            SET p.product_name = ?, p.product_image = ?, p.price = ?, 
                p.category = ?, p.stock = ?, p.details = ?, p.has_details = ? 
            WHERE p.product_id = ? 
            AND m.owner_id = ?
            AND p.market_id = ?
        ");
        
        if ($stmt->execute([
            $product_name, 
            $image_name, 
            $price, 
            $category, 
            $stock, 
            $details, 
            $has_details, 
            $product_id,
            $owner_id,  // Verify owner
            $market_id  // Verify market
        ])) {
            // PRG: Redirect after successful update
            header("Location: products.php?success=updated");
            exit();
        } else {
            header("Location: products.php?error=update_failed");
            exit();
        }
    }
    
    // DELETE PRODUCT
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $product_id = intval($_POST['product_id']);
        
        // CRITICAL: Verify product belongs to this owner's market
        $stmt = $pdo->prepare("
            SELECT p.product_image FROM products p
            INNER JOIN markets m ON p.market_id = m.market_id
            WHERE p.product_id = ? 
            AND m.owner_id = ?
            AND p.market_id = ?
        ");
        $stmt->execute([$product_id, $owner_id, $market_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            // Access denied - not owner's product
            header("Location: products.php?error=access_denied");
            exit();
        }
        
        // SECURITY: Double-check ownership in DELETE query
        $stmt = $pdo->prepare("
            DELETE p FROM products p
            INNER JOIN markets m ON p.market_id = m.market_id
            WHERE p.product_id = ? 
            AND m.owner_id = ?
            AND p.market_id = ?
        ");
        
        if ($stmt->execute([$product_id, $owner_id, $market_id])) {
            // Delete image file
            if ($product['product_image'] && file_exists('../uploads/products/' . $product['product_image'])) {
                unlink('../uploads/products/' . $product['product_image']);
            }
            // PRG: Redirect after successful deletion
            header("Location: products.php?success=deleted");
            exit();
        } else {
            header("Location: products.php?error=delete_failed");
            exit();
        }
    }
    
    // If we reach here, invalid action - redirect
    header("Location: products.php");
    exit();
}

// ===================================================================
// GET REQUEST - DISPLAY PAGE
// ===================================================================
// This section only runs on GET requests (after redirect or direct visit)

// Handle success/error messages from URL parameters
$success_msg = '';
$error_msg = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_msg = "Product added successfully!";
            break;
        case 'updated':
            $success_msg = "Product updated successfully!";
            break;
        case 'deleted':
            $success_msg = "Product deleted successfully!";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'add_failed':
            $error_msg = "Failed to add product. Please try again.";
            break;
        case 'update_failed':
            $error_msg = "Failed to update product. Please try again.";
            break;
        case 'delete_failed':
            $error_msg = "Failed to delete product. Please try again.";
            break;
        case 'access_denied':
            $error_msg = "Access denied. Product not found or doesn't belong to you.";
            break;
        case 'upload_failed':
            $error_msg = "Failed to upload image. Please try again.";
            break;
        case 'invalid_image':
            $error_msg = "Invalid image file. Only JPG, PNG, GIF allowed (max 5MB).";
            break;
        case 'invalid_url':
            $error_msg = "Invalid URL format. Please enter a valid image URL.";
            break;
        case 'url_protocol':
            $error_msg = "URL must start with http:// or https://";
            break;
        case 'url_not_image':
            $error_msg = "URL must point to an image file (.jpg, .png, .gif, .webp).";
            break;
        case 'no_market':
            $error_msg = "You need to create a market first!";
            break;
    }
}

// CRITICAL: Get ONLY products from this owner's market
$stmt = $pdo->prepare("
    SELECT p.* FROM products p
    INNER JOIN markets m ON p.market_id = m.market_id
    WHERE m.owner_id = ?
    AND p.market_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$owner_id, $market_id]);
$products = $stmt->fetchAll();

// Get unique categories from THIS OWNER'S products only
$stmt = $pdo->prepare("
    SELECT DISTINCT p.category FROM products p
    INNER JOIN markets m ON p.market_id = m.market_id
    WHERE m.owner_id = ?
    AND p.market_id = ?
    ORDER BY p.category
");
$stmt->execute([$owner_id, $market_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - ByteShop</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .header {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header h1 {
        color: #333;
        font-size: 28px;
    }

    .header .user-info {
        text-align: right;
    }

    .header .user-info p {
        color: #666;
        margin-bottom: 5px;
    }

    .header .user-info span {
        color: #667eea;
        font-weight: bold;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
    }

    .btn-primary {
        background: #667eea;
        color: white;
    }

    .btn-primary:hover {
        background: #5568d3;
    }

    .btn-success {
        background: #48bb78;
        color: white;
    }

    .btn-success:hover {
        background: #38a169;
    }

    .btn-danger {
        background: #f56565;
        color: white;
    }

    .btn-danger:hover {
        background: #e53e3e;
    }

    .btn-warning {
        background: #ed8936;
        color: white;
    }

    .btn-warning:hover {
        background: #dd6b20;
    }

    .btn-secondary {
        background: #718096;
        color: white;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: #c6f6d5;
        color: #22543d;
        border: 1px solid #9ae6b4;
    }

    .alert-error {
        background: #fed7d7;
        color: #742a2a;
        border: 1px solid #fc8181;
    }

    .content-box {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .content-box h2 {
        color: #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 500;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .product-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: #f7fafc;
    }

    .product-info {
        padding: 15px;
    }

    .product-name {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .product-details {
        color: #666;
        font-size: 14px;
        margin-bottom: 5px;
    }

    .product-price {
        font-size: 20px;
        color: #667eea;
        font-weight: bold;
        margin: 10px 0;
    }

    .product-category {
        display: inline-block;
        padding: 4px 10px;
        background: #e6fffa;
        color: #234e52;
        border-radius: 15px;
        font-size: 12px;
        margin-bottom: 10px;
    }

    .product-stock {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 12px;
        margin-left: 5px;
    }

    .product-stock.in-stock {
        background: #c6f6d5;
        color: #22543d;
    }

    .product-stock.low-stock {
        background: #feebc8;
        color: #7c2d12;
    }

    .product-stock.out-of-stock {
        background: #fed7d7;
        color: #742a2a;
    }

    .product-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .product-actions .btn {
        flex: 1;
        text-align: center;
        font-size: 12px;
        padding: 8px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        overflow-y: auto;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .modal-header h3 {
        color: #333;
    }

    .close-modal {
        font-size: 28px;
        cursor: pointer;
        color: #666;
        background: none;
        border: none;
    }

    .stats-bar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }

    .stat-card h3 {
        font-size: 32px;
        margin-bottom: 5px;
    }

    .stat-card p {
        opacity: 0.9;
    }

    .no-products {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .security-badge {
        display: inline-block;
        padding: 5px 12px;
        background: #48bb78;
        color: white;
        border-radius: 5px;
        font-size: 12px;
        margin-top: 5px;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .stats-bar {
            grid-template-columns: 1fr;
        }

        .product-actions {
            flex-direction: column;
        }

        .header {
            flex-direction: column;
            text-align: center;
        }

        .header .user-info {
            text-align: center;
            margin-top: 15px;
        }
    }

    .product-image-container {
        position: relative;
        width: 100%;
        height: 220px;
        overflow: hidden;
        background: #f9f9f9;
    }

    .product-image {
        width: 100%;

        object-fit: cover;
        transition: transform 0.3s;
    }

    .product-card:hover .product-image {
        transform: scale(1.05);
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🛍️ Product Management</h1>
                <p style="color: #666; margin-top: 5px;">
                    Market: <strong><?php echo htmlspecialchars($market['market_name']); ?></strong>
                </p>
                <span class="security-badge">🔒 Owner ID: <?php echo $owner_id; ?> | Market ID:
                    <?php echo $market_id; ?></span>
            </div>
            <div class="user-info">
                <p>Welcome, <span><?php echo htmlspecialchars(get_user_name()); ?></span></p>
                <a href="index.php" class="btn btn-secondary">← Dashboard</a>
                <a href="../logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <?php if ($success_msg): ?>
        <div class="alert alert-success">✓ <?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="alert alert-error">✗ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-bar">
            <div class="stat-card">
                <h3><?php echo count($products); ?></h3>
                <p>My Products</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($products, fn($p) => $p['stock'] > 0)); ?></h3>
                <p>In Stock</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($products, fn($p) => $p['stock'] == 0)); ?></h3>
                <p>Out of Stock</p>
            </div>
        </div>

        <!-- Add Product Button -->
        <div class="content-box">
            <button class="btn btn-success" onclick="openAddModal()">+ Add New Product</button>
        </div>

        <!-- Products Grid -->
        <div class="content-box">
            <h2>Your Products (Market ID: <?php echo $market_id; ?>)</h2>

            <?php if (empty($products)): ?>
            <div class="no-products">
                <p style="font-size: 18px;">📦 No products yet. Add your first product!</p>
            </div>
            <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <?php if ($product['product_image']): ?>
                    <?php
                                // Detect if image is URL or local file
                                $is_url = preg_match('/^https?:\/\//i', $product['product_image']);
                                $image_src = $is_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']);
                                ?>
                    <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                        class="product-image" onerror="this.src='../assets/images/default-product.jpg'">
                    <?php else: ?>
                    <div class="product-image"
                        style="display: flex; align-items: center; justify-content: center; background: #f7fafc; color: #cbd5e0;">
                        <img src="../assets/images/default-product.jpg" alt="Product" class="product-image">
                    </div>
                    <?php endif; ?>
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>

                        <div>
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <span class="product-stock <?php 
                                        echo $product['stock'] == 0 ? 'out-of-stock' : 
                                             ($product['stock'] < 10 ? 'low-stock' : 'in-stock'); 
                                    ?>">
                                Stock: <?php echo $product['stock']; ?>
                            </span>
                        </div>

                        <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>

                        <?php if ($product['has_details']): ?>
                        <p class="product-details">
                            <?php echo htmlspecialchars(substr($product['details'], 0, 100)); ?>...</p>
                        <?php endif; ?>

                        <div class="product-actions">
                            <button class="btn btn-warning"
                                onclick='openEditModal(<?php echo json_encode($product); ?>)'>
                                Edit
                            </button>
                            <button class="btn btn-danger"
                                onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" required minlength="20" maxlength="80"
                        placeholder="Enter product name">

                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Price (₹) *</label>
                        <input type="number" name="price" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <input type="text" name="category" list="categoryList" required>
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Product Image</label>

                    <!-- Image Source Selector -->
                    <div style="margin-bottom: 15px;">
                        <label
                            style="font-weight: normal; display: inline-flex; align-items: center; margin-right: 20px; cursor: pointer;">
                            <input type="radio" name="image_source" value="file" checked
                                onchange="toggleAddImageInput()" style="margin-right: 8px; cursor: pointer;">
                            <span>📁 Upload File</span>
                        </label>

                        <label style="font-weight: normal; display: inline-flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="image_source" value="url" onchange="toggleAddImageInput()"
                                style="margin-right: 8px; cursor: pointer;">
                            <span>🔗 Use URL</span>
                        </label>
                    </div>

                    <!-- File Upload -->
                    <div id="add-file-section">
                        <input type="file" name="product_image" accept="image/*" style="padding: 8px;">
                    </div>

                    <!-- URL Input -->
                    <div id="add-url-section" style="display: none;">
                        <input type="url" name="product_image_url" placeholder="https://example.com/product.jpg"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            ℹ️ Enter direct image URL (.jpg, .png, .gif, .webp)
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Details (Optional)</label>
                    <textarea name="details" placeholder="Enter product description, features, specifications..."
                        minlength="60" maxlength="200" required>
                     </textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Add Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Product</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="edit_product_id">

                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" id="edit_product_name" required  minlength="20"
    maxlength="80">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Price (₹) *</label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock" id="edit_stock" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <input type="text" name="category" id="edit_category" list="categoryList" required>
                </div>

                <div class="form-group">
                    <label>Product Image (Leave empty to keep current)</label>

                    <!-- Image Source Selector -->
                    <div style="margin-bottom: 15px;">
                        <label
                            style="font-weight: normal; display: inline-flex; align-items: center; margin-right: 20px; cursor: pointer;">
                            <input type="radio" name="image_source" value="file" checked
                                onchange="toggleEditImageInput()" style="margin-right: 8px; cursor: pointer;">
                            <span>📁 Upload File</span>
                        </label>

                        <label style="font-weight: normal; display: inline-flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="image_source" value="url" onchange="toggleEditImageInput()"
                                style="margin-right: 8px; cursor: pointer;">
                            <span>🔗 Use URL</span>
                        </label>
                    </div>

                    <!-- File Upload -->
                    <div id="edit-file-section">
                        <input type="file" name="product_image" accept="image/*" style="padding: 8px;">
                    </div>

                    <!-- URL Input -->
                    <div id="edit-url-section" style="display: none;">
                        <input type="url" name="product_image_url" placeholder="https://example.com/product.jpg"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            ℹ️ Enter direct image URL (.jpg, .png, .gif, .webp)
                        </small>
                    </div>

                    <small id="current_image" style="color: #666; display: block; margin-top: 10px;"></small>
                </div>

                <div class="form-group">
                    <label>Product Details (Optional)</label>
                    <textarea name="details" id="edit_details"
                        placeholder="Enter product description, features, specifications..."  minlength="60"
    maxlength="200"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-warning" style="flex: 1;">Update Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
    }

    function openEditModal(product) {
        document.getElementById('edit_product_id').value = product.product_id;
        document.getElementById('edit_product_name').value = product.product_name;
        document.getElementById('edit_price').value = product.price;
        document.getElementById('edit_stock').value = product.stock;
        document.getElementById('edit_category').value = product.category;
        document.getElementById('edit_details').value = product.details || '';

        if (product.product_image) {
            document.getElementById('current_image').textContent = 'Current: ' + product.product_image;
        } else {
            document.getElementById('current_image').textContent = 'No image currently';
        }

        document.getElementById('editModal').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function deleteProduct(productId, productName) {
        if (confirm('⚠️ Are you sure you want to delete "' + productName + '"?\n\nThis action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="${productId}">
                `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');

        if (event.target === addModal) {
            closeAddModal();
        }
        if (event.target === editModal) {
            closeEditModal();
        }
    }

    // Auto-hide success/error messages after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);


    // Toggle image input for ADD modal
    function toggleAddImageInput() {
        const imageSource = document.querySelector('#addModal input[name="image_source"]:checked').value;
        const fileSection = document.getElementById('add-file-section');
        const urlSection = document.getElementById('add-url-section');

        if (imageSource === 'file') {
            fileSection.style.display = 'block';
            urlSection.style.display = 'none';
            document.querySelector('#add-url-section input').value = '';
        } else {
            fileSection.style.display = 'none';
            urlSection.style.display = 'block';
            document.querySelector('#add-file-section input').value = '';
        }
    }

    // Toggle image input for EDIT modal
    function toggleEditImageInput() {
        const imageSource = document.querySelector('#editModal input[name="image_source"]:checked').value;
        const fileSection = document.getElementById('edit-file-section');
        const urlSection = document.getElementById('edit-url-section');

        if (imageSource === 'file') {
            fileSection.style.display = 'block';
            urlSection.style.display = 'none';
            document.querySelector('#edit-url-section input').value = '';
        } else {
            fileSection.style.display = 'none';
            urlSection.style.display = 'block';
            document.querySelector('#edit-file-section input').value = '';
        }
    }
    </script>
</body>

</html>