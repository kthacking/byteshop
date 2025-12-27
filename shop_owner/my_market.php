<?php
/**
 * ByteShop - Shop Owner Market Management
 * 
 * Allows shop owners to create and edit their market
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require shop owner access
require_shop_owner();

$user_id = get_user_id();
$success_message = '';
$error_message = '';

// Fetch owner's market (if exists)
$stmt = $pdo->prepare("SELECT * FROM markets WHERE owner_id = ?");
$stmt->execute([$user_id]);
$market = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $market_name = clean_input($_POST['market_name']);
    $location = clean_input($_POST['location']);
    $market_category = clean_input($_POST['market_category']);
    $description = clean_input($_POST['description']);
    
    // Validation
    if (empty($market_name) || empty($location) || empty($market_category)) {
        $error_message = "Market name, location, and category are required.";
    } else {
        // Handle image upload
        // Handle image upload OR URL
        $image_name = $market ? $market['market_image'] : null; // Keep existing image
        $image_source = isset($_POST['image_source']) ? $_POST['image_source'] : 'file';
        
        // Option 1: File Upload
        if ($image_source === 'file' && isset($_FILES['market_image']) && $_FILES['market_image']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $file_type = $_FILES['market_image']['type'];
            $file_size = $_FILES['market_image']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
            } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
                $error_message = "File size must be less than 5MB.";
            } else {
                // Create upload directory if not exists
                $upload_dir = '../uploads/markets/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['market_image']['name'], PATHINFO_EXTENSION);
                $image_name = 'market_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $image_name;
                
                // Delete old image if exists and is a local file (not URL)
                if ($market && $market['market_image']) {
                    $old_image = $market['market_image'];
                    if (!preg_match('/^https?:\/\//i', $old_image) && file_exists($upload_dir . $old_image)) {
                        unlink($upload_dir . $old_image);
                    }
                }
                
                if (!move_uploaded_file($_FILES['market_image']['tmp_name'], $upload_path)) {
                    $error_message = "Failed to upload image.";
                }
            }
        }
        
        // Option 2: Image URL
        elseif ($image_source === 'url' && !empty($_POST['market_image_url'])) {
            $image_url = trim($_POST['market_image_url']);
            
            // Validate URL format
            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                $error_message = "Invalid URL format.";
            } elseif (!preg_match('/^https?:\/\//i', $image_url)) {
                $error_message = "URL must start with http:// or https://";
            } elseif (!preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $image_url)) {
                $error_message = "URL must point to an image file (jpg, jpeg, png, gif, webp).";
            } else {
                // Sanitize and save URL
                $image_name = filter_var($image_url, FILTER_SANITIZE_URL);
                
                // Delete old local image if exists (not URL)
                if ($market && $market['market_image']) {
                    $old_image = $market['market_image'];
                    if (!preg_match('/^https?:\/\//i', $old_image) && file_exists('../uploads/markets/' . $old_image)) {
                        unlink('../uploads/markets/' . $old_image);
                    }
                }
            }
        }
        
        // Insert or update market
        if (empty($error_message)) {
            try {
                if ($market) {
                    // Update existing market
                    $stmt = $pdo->prepare("
                        UPDATE markets 
                        SET market_name = ?, location = ?, market_category = ?, 
                            description = ?, market_image = ?
                        WHERE market_id = ? AND owner_id = ?
                    ");
                    $stmt->execute([
                        $market_name, 
                        $location, 
                        $market_category, 
                        $description, 
                        $image_name,
                        $market['market_id'],
                        $user_id
                    ]);
                    $success_message = "Market updated successfully!";
                } else {
                    // Create new market
                    $stmt = $pdo->prepare("
                        INSERT INTO markets (owner_id, market_name, location, market_category, description, market_image)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, 
                        $market_name, 
                        $location, 
                        $market_category, 
                        $description, 
                        $image_name
                    ]);
                    $success_message = "Market created successfully!";
                }
                
                // Refresh market data
                $stmt = $pdo->prepare("SELECT * FROM markets WHERE owner_id = ?");
                $stmt->execute([$user_id]);
                $market = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch available locations and categories for dropdowns (from existing data)
$locations = $pdo->query("SELECT DISTINCT location FROM markets ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$categories = $pdo->query("SELECT DISTINCT market_category FROM markets ORDER BY market_category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Market - ByteShop</title>
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
        }

       .container {
            max-width: 100%;
        }


        /* .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
        }

        .nav-links a {
            margin-left: 15px;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }

        .nav-links a:hover {
            background: #764ba2;
        } */

        .card {
            background: white;
            padding: 30px 130px;
            margin-top:0px;
            border-radius: 0px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-input {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }

        .file-upload-label {
            display: inline-block;
            padding: 12px 20px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            width: 100%;
            transition: all 0.3s;
        }

        .file-upload-label:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .file-name {
            margin-top: 10px;
            font-size: 13px;
            color: #666;
            font-style: italic;
        }

        .current-image {
            margin-top: 10px;
            max-width: 200px;
            border-radius: 5px;
            border: 2px solid #ddd;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

       
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <!-- <div class="header">
            <h1>🏪 My Market</h1>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="products.php">Products</a>
                <a href="orders.php">Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div> -->
        <?php include '../includes/shop_owner_header.php'; ?>

        <!-- Market Form Card -->
        <div class="card">
            <h2><?php echo $market ? 'Edit Market' : 'Create Your Market'; ?></h2>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="market_name">Market Name *</label>
                        <input 
                            type="text" 
                            id="market_name" 
                            name="market_name" 
                            placeholder="e.g., TechHub Electronics"
                            value="<?php echo $market ? htmlspecialchars($market['market_name']) : ''; ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input 
                            type="text" 
                            id="location" 
                            name="location" 
                            placeholder="e.g., Mumbai"
                            value="<?php echo $market ? htmlspecialchars($market['location']) : ''; ?>"
                            list="location-list"
                            required
                        >
                        <datalist id="location-list">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>">
                            <?php endforeach; ?>
                            <option value="Mumbai">
                            <option value="Delhi">
                            <option value="Bangalore">
                            <option value="Pune">
                            <option value="Chennai">
                        </datalist>
                    </div>
                </div>

                <div class="form-group">
                    <label for="market_category">Market Category *</label>
                    <input 
                        type="text" 
                        id="market_category" 
                        name="market_category" 
                        placeholder="e.g., Electronics"
                        value="<?php echo $market ? htmlspecialchars($market['market_category']) : ''; ?>"
                        list="category-list"
                        required
                    >
                    <datalist id="category-list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                        <option value="Electronics">
                        <option value="Fashion">
                        <option value="Books">
                        <option value="Home & Kitchen">
                        <option value="Sports">
                        <option value="Toys">
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="description">Market Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        placeholder="Describe your market..."
                    ><?php echo $market ? htmlspecialchars($market['description']) : ''; ?></textarea>
                    <p class="info-text">Tell customers what makes your market special</p>
                </div>

                <div class="form-group">
                    <label>Market Image</label>
                    
                    <!-- Image Source Selector -->
                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: normal; display: inline-flex; align-items: center; margin-right: 20px; cursor: pointer;">
                            <input 
                                type="radio" 
                                name="image_source" 
                                value="file" 
                                checked 
                                onchange="toggleImageInput()"
                                style="margin-right: 8px; cursor: pointer;"
                            >
                            <span>📁 Upload from Device</span>
                        </label>
                        
                        <label style="font-weight: normal; display: inline-flex; align-items: center; cursor: pointer;">
                            <input 
                                type="radio" 
                                name="image_source" 
                                value="url" 
                                onchange="toggleImageInput()"
                                style="margin-right: 8px; cursor: pointer;"
                            >
                            <span>🔗 Use Image URL</span>
                        </label>
                    </div>
                    
                    <!-- File Upload Section -->
                    <div id="file-upload-section">
                        <div class="file-upload-wrapper">
                            <input 
                                type="file" 
                                id="market_image" 
                                name="market_image" 
                                accept="image/*"
                                class="file-upload-input"
                                onchange="displayFileName(this)"
                            >
                            <label for="market_image" class="file-upload-label">
                                📷 Choose Market Image (JPG, PNG, GIF - Max 5MB)
                            </label>
                        </div>
                        <div id="file-name" class="file-name"></div>
                    </div>
                    
                    <!-- URL Input Section (Hidden by default) -->
                    <div id="url-input-section" style="display: none;">
                        <input 
                            type="url" 
                            id="market_image_url" 
                            name="market_image_url" 
                            placeholder="https://example.com/image.jpg"
                            style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
                        >
                        <p class="info-text" style="margin-top: 8px;">
                            ℹ️ Enter direct link to image (must end with .jpg, .png, .gif, or .webp)
                        </p>
                    </div>
                    
                    <!-- Current Image Display -->
                    <?php if ($market && $market['market_image']): ?>
                        <div style="margin-top: 15px;">
                            <p style="font-size: 13px; color: #666; margin-bottom: 5px;">Current Image:</p>
                            <?php 
                            // Check if current image is URL or file
                            $is_url = preg_match('/^https?:\/\//i', $market['market_image']);
                            $image_src = $is_url ? htmlspecialchars($market['market_image']) : '../uploads/markets/' . htmlspecialchars($market['market_image']);
                            ?>
                            <img 
                                src="<?php echo $image_src; ?>" 
                                alt="Current market image" 
                                class="current-image"
                                onerror="this.src='../assets/images/default-market.jpg'; this.onerror=null;"
                            >
                            <?php if ($is_url): ?>
                                <p style="font-size: 11px; color: #999; margin-top: 5px;">🔗 External URL</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                

                <button type="submit" class="btn btn-primary">
                    <?php echo $market ? '💾 Update Market' : '✨ Create Market'; ?>
                </button>
            </form>
        </div>
    </div>

  <script>
        function displayFileName(input) {
            const fileNameDiv = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = '📎 Selected: ' + input.files[0].name;
            } else {
                fileNameDiv.textContent = '';
            }
        }
        
        function toggleImageInput() {
            const imageSource = document.querySelector('input[name="image_source"]:checked').value;
            const fileSection = document.getElementById('file-upload-section');
            const urlSection = document.getElementById('url-input-section');
            const fileInput = document.getElementById('market_image');
            const urlInput = document.getElementById('market_image_url');
            
            if (imageSource === 'file') {
                fileSection.style.display = 'block';
                urlSection.style.display = 'none';
                urlInput.value = ''; // Clear URL input
                urlInput.removeAttribute('required');
                fileInput.removeAttribute('required');
            } else {
                fileSection.style.display = 'none';
                urlSection.style.display = 'block';
                fileInput.value = ''; // Clear file input
                document.getElementById('file-name').textContent = '';
                fileInput.removeAttribute('required');
                urlInput.removeAttribute('required');
            }
        }
        
        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const imageSource = document.querySelector('input[name="image_source"]:checked').value;
            const fileInput = document.getElementById('market_image');
            const urlInput = document.getElementById('market_image_url');
            
            if (imageSource === 'url') {
                const urlValue = urlInput.value.trim();
                
                if (urlValue === '') {
                    e.preventDefault();
                    alert('⚠️ Please enter an image URL or switch to file upload.');
                    return false;
                }
                
                // Validate URL format
                if (!urlValue.match(/^https?:\/\/.+/i)) {
                    e.preventDefault();
                    alert('⚠️ URL must start with http:// or https://');
                    return false;
                }
                
                // Validate image extension
                if (!urlValue.match(/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i)) {
                    e.preventDefault();
                    alert('⚠️ URL must point to an image file (.jpg, .png, .gif, .webp)');
                    return false;
                }
            }
        });
    </script>
</body>
</html>