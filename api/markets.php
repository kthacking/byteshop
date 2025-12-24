<?php
/**
 * ByteShop - Markets API
 * Handles all market-related operations
 * 
 * Endpoints:
 * - GET: Fetch markets (with filters for customers)
 * - POST: Create/Update market (shop owners)
 * - DELETE: Delete market (shop owners/admin)
 */

session_start();
require_once '../config/db.php';
require_once '../includes/session.php';

// Set JSON header for API responses
header('Content-Type: application/json');

// ============================================
// GET MARKETS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $action = $_GET['action'] ?? 'list';
    
    // ----------------------------------------
    // Get Owner's Market (Shop Owner Only)
    // ----------------------------------------
    if ($action === 'get_my_market') {
        require_shop_owner();
        
        $owner_id = get_user_id();
        
        try {
            $stmt = $pdo->prepare("
                SELECT m.*, u.name as owner_name, u.email as owner_email
                FROM markets m
                JOIN users u ON m.owner_id = u.user_id
                WHERE m.owner_id = ?
                LIMIT 1
            ");
            $stmt->execute([$owner_id]);
            $market = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'message' => $market ? 'Market found' : 'No market created yet',
                'data' => $market
            ]);
            
        } catch(PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    // ----------------------------------------
    // Get Single Market by ID
    // ----------------------------------------
    elseif ($action === 'get_single' && isset($_GET['id'])) {
        $market_id = (int)$_GET['id'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT m.*, u.name as owner_name
                FROM markets m
                JOIN users u ON m.owner_id = u.user_id
                WHERE m.market_id = ? AND m.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$market_id]);
            $market = $stmt->fetch();
            
            if ($market) {
                // Get product count
                $stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM products WHERE market_id = ? AND status = 'active'");
                $stmt->execute([$market_id]);
                $count = $stmt->fetch();
                $market['product_count'] = $count['product_count'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $market
            ]);
            
        } catch(PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
        exit;
    }
    
    // ----------------------------------------
    // List All Markets (with filters)
    // ----------------------------------------
    elseif ($action === 'list') {
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $location = $_GET['location'] ?? '';
        $category = $_GET['category'] ?? '';
        $min_rating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0;
        $sort = $_GET['sort'] ?? 'newest';
        
        // Build query
        $sql = "SELECT m.*, u.name as owner_name,
                (SELECT COUNT(*) FROM products WHERE market_id = m.market_id AND status = 'active') as product_count
                FROM markets m
                JOIN users u ON m.owner_id = u.user_id
                WHERE m.status = 'active'";
        
        $params = [];
        
        // Apply filters
        if (!empty($search)) {
            $sql .= " AND m.market_name LIKE ?";
            $params[] = "%$search%";
        }
        
        if (!empty($location)) {
            $sql .= " AND m.location = ?";
            $params[] = $location;
        }
        
        if (!empty($category)) {
            $sql .= " AND m.market_category = ?";
            $params[] = $category;
        }
        
        if ($min_rating > 0) {
            $sql .= " AND m.rating >= ?";
            $params[] = $min_rating;
        }
        
        // Apply sorting
        switch ($sort) {
            case 'name_asc':
                $sql .= " ORDER BY m.market_name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY m.market_name DESC";
                break;
            case 'rating':
                $sql .= " ORDER BY m.rating DESC";
                break;
            case 'oldest':
                $sql .= " ORDER BY m.created_at ASC";
                break;
            case 'newest':
            default:
                $sql .= " ORDER BY m.created_at DESC";
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $markets = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'count' => count($markets),
                'data' => $markets
            ]);
            
        } catch(PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
        exit;
    }
    
    // ----------------------------------------
    // Get Filter Options (locations, categories)
    // ----------------------------------------
    elseif ($action === 'get_filters') {
        try {
            // Get all unique locations
            $stmt = $pdo->query("SELECT DISTINCT location FROM markets WHERE status = 'active' ORDER BY location");
            $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get all unique categories
            $stmt = $pdo->query("SELECT DISTINCT market_category FROM markets WHERE status = 'active' ORDER BY market_category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'locations' => $locations,
                    'categories' => $categories
                ]
            ]);
            
        } catch(PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
        exit;
    }
}

// ============================================
// CREATE OR UPDATE MARKET
// ============================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Only shop owners can create/update markets
    require_shop_owner();
    
    $owner_id = get_user_id();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_market' || $action === 'update_market') {
        
        // Get form data
        $market_name = clean_input($_POST['market_name'] ?? '');
        $location = clean_input($_POST['location'] ?? '');
        $market_category = clean_input($_POST['market_category'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($market_name)) {
            $errors[] = 'Market name is required';
        } elseif (strlen($market_name) < 3) {
            $errors[] = 'Market name must be at least 3 characters';
        }
        
        if (empty($location)) {
            $errors[] = 'Location is required';
        }
        
        if (empty($market_category)) {
            $errors[] = 'Category is required';
        }
        
        if (!empty($errors)) {
            header('Location: ../shop_owner/my_market.php?error=' . urlencode(implode(', ', $errors)));
            exit;
        }
        
        // Handle image upload
        $image_filename = null;
        
        if (isset($_FILES['market_image']) && $_FILES['market_image']['error'] === UPLOAD_ERR_OK) {
            
            $file = $_FILES['market_image'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed extensions
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed)) {
                header('Location: ../shop_owner/my_market.php?error=invalid_image_type');
                exit;
            }
            
            // Check file size (max 5MB)
            if ($file_size > 5242880) {
                header('Location: ../shop_owner/my_market.php?error=image_too_large');
                exit;
            }
            
            // Generate unique filename
            $image_filename = 'market_' . $owner_id . '_' . time() . '.' . $file_ext;
            $upload_dir = '../uploads/markets/';
            $upload_path = $upload_dir . $image_filename;
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    header('Location: ../shop_owner/my_market.php?error=folder_creation_failed');
                    exit;
                }
            }
            
            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                header('Location: ../shop_owner/my_market.php?error=folder_not_writable');
                exit;
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                header('Location: ../shop_owner/my_market.php?error=upload_failed');
                exit;
            }
            
        } elseif (isset($_FILES['market_image']) && $_FILES['market_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'file_too_large_ini',
                UPLOAD_ERR_FORM_SIZE => 'file_too_large_form',
                UPLOAD_ERR_PARTIAL => 'partial_upload',
                UPLOAD_ERR_NO_TMP_DIR => 'no_tmp_dir',
                UPLOAD_ERR_CANT_WRITE => 'cant_write',
                UPLOAD_ERR_EXTENSION => 'extension_error'
            ];
            
            $error_code = $_FILES['market_image']['error'];
            $error_msg = $upload_errors[$error_code] ?? 'upload_unknown_error';
            
            header('Location: ../shop_owner/my_market.php?error=' . $error_msg);
            exit;
        }
        
        try {
            // Check if market already exists for this owner
            $stmt = $pdo->prepare("SELECT market_id, market_image FROM markets WHERE owner_id = ? LIMIT 1");
            $stmt->execute([$owner_id]);
            $existing_market = $stmt->fetch();
            
            // UPDATE existing market
            if ($existing_market) {
                
                // If new image uploaded, delete old image
                if ($image_filename && !empty($existing_market['market_image'])) {
                    $old_image_path = '../uploads/markets/' . $existing_market['market_image'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                
                // Build update query
                if ($image_filename) {
                    // Update with new image
                    $stmt = $pdo->prepare("
                        UPDATE markets 
                        SET market_name = ?, 
                            location = ?, 
                            market_category = ?, 
                            description = ?,
                            market_image = ?,
                            updated_at = NOW()
                        WHERE owner_id = ?
                    ");
                    $stmt->execute([
                        $market_name, 
                        $location, 
                        $market_category, 
                        $description,
                        $image_filename,
                        $owner_id
                    ]);
                } else {
                    // Update without changing image
                    $stmt = $pdo->prepare("
                        UPDATE markets 
                        SET market_name = ?, 
                            location = ?, 
                            market_category = ?, 
                            description = ?,
                            updated_at = NOW()
                        WHERE owner_id = ?
                    ");
                    $stmt->execute([
                        $market_name, 
                        $location, 
                        $market_category, 
                        $description,
                        $owner_id
                    ]);
                }
                
                header('Location: ../shop_owner/my_market.php?success=updated');
                exit;
            }
            
            // CREATE new market
            else {
                
                // Market image is required for new market
                if (!$image_filename) {
                    header('Location: ../shop_owner/my_market.php?error=image_required');
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO markets 
                    (owner_id, market_name, location, market_category, description, market_image, rating, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 0.0, 'active', NOW())
                ");
                
                $stmt->execute([
                    $owner_id,
                    $market_name,
                    $location,
                    $market_category,
                    $description,
                    $image_filename
                ]);
                
                header('Location: ../shop_owner/my_market.php?success=created');
                exit;
            }
            
        } catch(PDOException $e) {
            error_log("Market creation/update error: " . $e->getMessage());
            
            // If image was uploaded, delete it on error
            if ($image_filename) {
                $upload_path = '../uploads/markets/' . $image_filename;
                if (file_exists($upload_path)) {
                    unlink($upload_path);
                }
            }
            
            header('Location: ../shop_owner/my_market.php?error=database_error');
            exit;
        }
    }
}

// ============================================
// DELETE MARKET
// ============================================
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    
    // Parse DELETE request body
    parse_str(file_get_contents("php://input"), $_DELETE);
    
    $market_id = isset($_DELETE['market_id']) ? (int)$_DELETE['market_id'] : 0;
    
    // Check authentication
    if (!is_logged_in()) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }
    
    $user_id = get_user_id();
    $user_role = get_user_role();
    
    try {
        // Get market details
        $stmt = $pdo->prepare("SELECT owner_id, market_image FROM markets WHERE market_id = ?");
        $stmt->execute([$market_id]);
        $market = $stmt->fetch();
        
        if (!$market) {
            echo json_encode([
                'success' => false,
                'message' => 'Market not found'
            ]);
            exit;
        }
        
        // Authorization check: Only owner or admin can delete
        if ($user_role !== 'admin' && $market['owner_id'] != $user_id) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to delete this market'
            ]);
            exit;
        }
        
        // Delete market image
        if ($market['market_image']) {
            $image_path = '../uploads/markets/' . $market['market_image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        // Delete market (this will cascade delete products due to foreign key)
        $stmt = $pdo->prepare("DELETE FROM markets WHERE market_id = ?");
        $stmt->execute([$market_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Market deleted successfully'
        ]);
        
    } catch(PDOException $e) {
        error_log("Market deletion error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }
    exit;
}

// ============================================
// INVALID REQUEST METHOD
// ============================================
else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}
?>