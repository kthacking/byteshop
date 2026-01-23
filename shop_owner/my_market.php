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

// Fetch available locations and categories for dropdowns
$locations = $pdo->query("SELECT DISTINCT location FROM markets ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$categories = $pdo->query("SELECT DISTINCT market_category FROM markets ORDER BY market_category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Market - MarketX</title>
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

        .btn { padding: 0.8rem 1.5rem; border-radius: 8px; font-size: 1rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #111; color: #fff; }
        .btn-primary:hover { background: var(--primary); transform: translateY(-2px); }

        /* Form Card */
        .card { background: #fff; border-radius: var(--card-radius); padding: 2.5rem; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.95rem; transition: border-color 0.2s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); ring: 2px solid rgba(255, 75, 43, 0.1); }
        .info-text { font-size: 0.8rem; color: var(--text-gray); margin-top: 0.5rem; }

        /* File Upload */
        .file-upload-wrapper { border: 2px dashed #d1d5db; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s; background: #f9fafb; }
        .file-upload-wrapper:hover { border-color: var(--primary); background: #fff; }
        .file-upload-input { display: none; }
        .file-upload-label { cursor: pointer; color: var(--text-gray); font-size: 0.9rem; }
        .file-name { margin-top: 0.5rem; font-weight: 600; color: var(--primary); }

        .current-image { max-width: 200px; border-radius: 8px; margin-top: 1rem; border: 1px solid #e5e7eb; padding: 0.5rem; background: #fff; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>🛒 Market<span>X</span> Owner</h1>
        <div class="user-info">
            <span>👋 <?php echo htmlspecialchars($user_id); ?></span>
            <a href="../logout.php" class="logout-btn">Log Output</a>
        </div>
    </nav>

    <div class="container">
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="my_market.php" class="active">My Market</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
        </div>

        <div style="text-align:center; margin-bottom:2rem;">
            <h2 style="font-size:2rem; font-weight:800; color:#111; margin-bottom:0.5rem;">
                <?php echo $market ? 'Edit Your Market' : 'Create Your Market'; ?>
            </h2>
            <p style="color:#6B7280;">Manage your market details and appearance.</p>
        </div>

        <div class="card">
            <?php if ($success_message): ?>
                <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error">❌ <?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Market Name</label>
                        <input type="text" name="market_name" placeholder="e.g. Best Shop" value="<?php echo $market ? htmlspecialchars($market['market_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" list="location-list" placeholder="e.g. Mumbai" value="<?php echo $market ? htmlspecialchars($market['location']) : ''; ?>" required>
                        <datalist id="location-list">
                            <?php foreach ($locations as $loc): ?><option value="<?php echo htmlspecialchars($loc); ?>"><?php endforeach; ?>
                            <option value="Mumbai"><option value="Delhi"><option value="Bangalore">
                        </datalist>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="market_category" list="category-list" placeholder="e.g. Electronics" value="<?php echo $market ? htmlspecialchars($market['market_category']) : ''; ?>" required>
                    <datalist id="category-list">
                        <?php foreach ($categories as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"><?php endforeach; ?>
                        <option value="Electronics"><option value="Fashion"><option value="Groceries">
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" placeholder="Tell customers about your shop..."><?php echo $market ? htmlspecialchars($market['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Market Image</label>
                    <div style="margin-bottom:1rem; display:flex; gap:1.5rem;">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:0.5rem;">
                            <input type="radio" name="image_source" value="file" checked onchange="toggleImageInput()"> 📁 Upload File
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:0.5rem;">
                            <input type="radio" name="image_source" value="url" onchange="toggleImageInput()"> 🔗 Image URL
                        </label>
                    </div>

                    <div id="file-upload-section">
                        <label class="file-upload-wrapper">
                            <input type="file" name="market_image" id="market_image" accept="image/*" class="file-upload-input" onchange="displayFileName(this)">
                            <div class="file-upload-label">Click to upload image (Max 5MB)</div>
                        </label>
                        <div id="file-name" class="file-name"></div>
                    </div>

                    <div id="url-input-section" style="display:none;">
                        <input type="url" name="market_image_url" id="market_image_url" placeholder="https://example.com/shop.jpg">
                        <p class="info-text">Enter a direct link to a JPG/PNG image.</p>
                    </div>

                    <?php if ($market && $market['market_image']): ?>
                        <div style="margin-top:1rem;">
                            <p style="font-size:0.85rem; font-weight:600; color:#374151;">Current Image:</p>
                            <?php 
                                $is_url = preg_match('/^https?:\/\//i', $market['market_image']);
                                $image_src = $is_url ? htmlspecialchars($market['market_image']) : '../uploads/markets/' . htmlspecialchars($market['market_image']);
                            ?>
                            <img src="<?php echo $image_src; ?>" class="current-image" onerror="this.src='../assets/images/default-market.jpg'">
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:2rem;">
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <?php echo $market ? 'Save Changes' : 'Create Market'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function displayFileName(input) {
            const fileNameDiv = document.getElementById('file-name');
            if (input.files && input.files[0]) {
                fileNameDiv.textContent = 'Selected: ' + input.files[0].name;
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
                urlInput.value = '';
                urlInput.removeAttribute('required');
            } else {
                fileSection.style.display = 'none';
                urlSection.style.display = 'block';
                fileInput.value = '';
                document.getElementById('file-name').textContent = '';
                fileInput.removeAttribute('required');
            }
        }

        // Validate on submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const imageSource = document.querySelector('input[name="image_source"]:checked').value;
            const urlInput = document.getElementById('market_image_url');

            if (imageSource === 'url') {
                const urlValue = urlInput.value.trim();
                if (urlValue === '') {
                    e.preventDefault();
                    alert('Please enter an image URL.');
                    return false;
                }
                if (!urlValue.match(/^https?:\/\/.+/i)) {
                    e.preventDefault();
                    alert('URL must start with http:// or https://');
                    return false;
                }
            }
        });
    </script>
</body>
</html>