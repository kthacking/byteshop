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
    <title>Product Management - MarketX</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary: #FF4B2B;
            --primary-dark: #cc3a20;
            --bg-light: #F9FAFB;
            --text-dark: #1F2937;
            --text-gray: #6B7280;
            --border-color: #e5e7eb;
            --card-radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar h1 span { color: var(--primary); }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .logout-btn {
            background: #fff;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .logout-btn:hover { background: #FFF5F5; color: var(--primary); border-color: var(--primary); }

        /* Container & Nav Pill */
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .nav-links {
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 0.5rem;
            background: #fff;
            border-radius: 50px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            flex-wrap: wrap;
        }
        .nav-links a {
            padding: 0.5rem 1.2rem;
            color: var(--text-gray);
            text-decoration: none;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .nav-links a:hover { color: var(--text-dark); background: #f3f4f6; }
        .nav-links a.active { background: #000; color: #fff; }

        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .header h2 { font-size: 1.8rem; font-weight: 800; color: #111; margin-bottom: 0.5rem; }
        .header p { color: var(--text-gray); }

        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #111; color: #fff; }
        .btn-primary:hover { background: var(--primary); transform: translateY(-2px); }
        .btn-secondary { background: #f3f4f6; color: var(--text-dark); }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-danger { background: #fee2e2; color: #b91c1c; }
        .btn-danger:hover { background: #fecaca; }

        /* Stats Bar */
        .stats-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; padding: 1.5rem; border-radius: var(--card-radius); display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid var(--border-color); transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px rgba(0,0,0,0.05); }
        .stat-card .value { font-size: 2rem; font-weight: 800; color: #111; }
        .stat-card .label { font-size: 0.85rem; color: var(--text-gray); font-weight: 600; text-transform: uppercase; }

        /* Products Grid */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        
        .product-card { background: #fff; border-radius: var(--card-radius); overflow: hidden; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s; position: relative; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-color: var(--primary); }
        
        .product-image-container { height: 200px; overflow: hidden; background: #f9fafb; position: relative; }
        .product-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .product-card:hover .product-image { transform: scale(1.05); }
        
        .product-info { padding: 1.25rem; }
        .product-category { font-size: 0.75rem; text-transform: uppercase; color: var(--text-gray); font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.5rem; display: block; }
        .product-name { font-size: 1.1rem; font-weight: 700; color: #111; margin-bottom: 0.5rem; line-height: 1.3; }
        .product-price { font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 1rem; }
        
        .badges { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge.in-stock { background: #ECFDF5; color: #059669; }
        .badge.low-stock { background: #FFFBEB; color: #D97706; }
        .badge.out-stock { background: #FEF2F2; color: #DC2626; }
        
        .card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: auto; }
        .card-actions button { width: 100%; font-size: 0.85rem; padding: 0.6rem; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal.active { display: flex; }
        .modal-content { background: #fff; border-radius: 16px; padding: 2rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f3f4f6; }
        .modal-header h3 { font-size: 1.5rem; font-weight: 800; color: #111; }
        .close-modal { background: none; border: none; font-size: 2rem; cursor: pointer; color: var(--text-gray); }
        .close-modal:hover { color: var(--primary); }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; font-size: 0.95rem; transition: border-color 0.2s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); ring: 2px solid rgba(255, 75, 43, 0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        /* Alert */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>🛒 Market<span>X</span> Owner</h1>
        <div class="user-info">
            <span>👋 <?php echo htmlspecialchars($market['market_name']); ?></span>
            <a href="../logout.php" class="logout-btn">Log Output</a>
        </div>
    </nav>

    <div class="container">
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="my_market.php">My Market</a>
            <a href="products.php" class="active">Products</a>
            <a href="orders.php">Orders</a>
        </div>

        <div class="header">
            <div>
                <h2>Product Manager</h2>
                <p>Manage your catalog, stock, and pricing from here.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">+ Add New Product</button>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="value"><?php echo count($products); ?></div>
                <div class="label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color:#059669;"><?php echo count(array_filter($products, fn($p) => $p['stock'] > 0)); ?></div>
                <div class="label">In Stock</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color:#DC2626;"><?php echo count(array_filter($products, fn($p) => $p['stock'] == 0)); ?></div>
                <div class="label">Out of Stock</div>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div style="text-align:center; padding:4rem; background:#fff; border-radius:16px; border:1px solid #e5e7eb;">
                <div style="font-size:3rem; margin-bottom:1rem;">📦</div>
                <h3 style="font-size:1.5rem; margin-bottom:0.5rem; color:#111;">No products yet</h3>
                <p style="color:#6B7280; margin-bottom:2rem;">Start building your catalog now!</p>
                <button class="btn btn-primary" onclick="openAddModal()">Add First Product</button>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image-container">
                            <?php 
                                $is_url = preg_match('/^https?:\/\//i', $product['product_image']);
                                $image_src = $product['product_image'] 
                                    ? ($is_url ? htmlspecialchars($product['product_image']) : '../uploads/products/' . htmlspecialchars($product['product_image']))
                                    : '../assets/images/default-product.jpg';
                            ?>
                            <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image" onerror="this.src='../assets/images/default-product.jpg'">
                        </div>
                        <div class="product-info">
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                            
                            <div class="badges">
                                <?php if($product['stock'] == 0): ?>
                                    <span class="badge out-stock">Out of Stock</span>
                                <?php elseif($product['stock'] < 10): ?>
                                    <span class="badge low-stock">Low Stock: <?php echo $product['stock']; ?></span>
                                <?php else: ?>
                                    <span class="badge in-stock"><?php echo $product['stock']; ?> in stock</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-actions">
                                <button class="btn btn-secondary" onclick='openEditModal(<?php echo json_encode($product); ?>)'>Edit</button>
                                <button class="btn btn-danger" onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">Delete</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-modal" onclick="closeAddModal()">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" required minlength="2" maxlength="80" placeholder="e.g. Wireless Headphones">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" list="categoryList" required placeholder="Select or type new category">
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Product Image</label>
                    <div style="margin-bottom:0.5rem; display:flex; gap:1rem;">
                        <label style="font-weight:normal; cursor:pointer;">
                            <input type="radio" name="image_source" value="file" checked onchange="toggleAddImageInput()"> Upload File
                        </label>
                        <label style="font-weight:normal; cursor:pointer;">
                            <input type="radio" name="image_source" value="url" onchange="toggleAddImageInput()"> Use URL
                        </label>
                    </div>
                    <div id="add-file-section">
                        <input type="file" name="product_image" accept="image/*">
                    </div>
                    <div id="add-url-section" style="display:none;">
                        <input type="url" name="product_image_url" placeholder="https://example.com/image.jpg">
                    </div>
                </div>

                <div class="form-group">
                    <label>Details</label>
                    <textarea name="details" rows="3" required placeholder="Describe your product..."></textarea>
                </div>

                <div style="display:flex; gap:1rem; margin-top:2rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Add Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()" style="flex:1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Product</h3>
                <button class="close-modal" onclick="closeEditModal()">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="edit_product_id">

                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_product_name" required minlength="2" maxlength="80">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" id="edit_stock" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="edit_category" list="categoryList" required>
                </div>

                <div class="form-group">
                    <label>Product Image</label>
                    <div style="margin-bottom:0.5rem; display:flex; gap:1rem;">
                        <label style="font-weight:normal; cursor:pointer;">
                            <input type="radio" name="image_source" value="file" checked onchange="toggleEditImageInput()"> Upload File
                        </label>
                        <label style="font-weight:normal; cursor:pointer;">
                            <input type="radio" name="image_source" value="url" onchange="toggleEditImageInput()"> Use URL
                        </label>
                    </div>
                    <div id="edit-file-section">
                        <input type="file" name="product_image" accept="image/*">
                    </div>
                    <div id="edit-url-section" style="display:none;">
                        <input type="url" name="product_image_url" placeholder="https://example.com/image.jpg">
                    </div>
                    <small id="current_image" style="color:#6B7280; display:block; margin-top:0.5rem;"></small>
                </div>

                <div class="form-group">
                    <label>Details</label>
                    <textarea name="details" id="edit_details" rows="3" required></textarea>
                </div>

                <div style="display:flex; gap:1rem; margin-top:2rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex:1;">Cancel</button>
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

            const currentImgText = product.product_image ? 'Current: ' + product.product_image : 'No image currently';
            document.getElementById('current_image').textContent = currentImgText;

            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteProduct(productId, productName) {
            if (confirm('⚠️ Delete "' + productName + '"?\n\nThis action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="${productId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) closeAddModal();
            if (event.target === editModal) closeEditModal();
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

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