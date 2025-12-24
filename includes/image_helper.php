<?php
/**
 * ByteShop - Image Helper Functions
 * 
 * Handles both file uploads and URL-based images
 */

/**
 * Process image input (either file upload or URL)
 * 
 * @param array $file - $_FILES['image_file']
 * @param string $url - Image URL from input
 * @param string $upload_dir - Target upload directory
 * @return array - ['success' => bool, 'image_path' => string, 'image_type' => string, 'message' => string]
 */
function process_image_input($file, $url, $upload_dir = 'uploads/') {
    $response = [
        'success' => false,
        'image_path' => null,
        'image_type' => null,
        'message' => ''
    ];
    
    // Check if at least one input is provided
    $has_file = isset($file) && $file['error'] !== UPLOAD_ERR_NO_FILE;
    $has_url = !empty($url);
    
    if (!$has_file && !$has_url) {
        $response['message'] = 'Please provide either an image file or image URL';
        return $response;
    }
    
    // Priority: File upload over URL
    if ($has_file) {
        return handle_file_upload($file, $upload_dir);
    } else {
        return handle_image_url($url);
    }
}

/**
 * Handle file upload
 */
function handle_file_upload($file, $upload_dir) {
    $response = [
        'success' => false,
        'image_path' => null,
        'image_type' => 'upload',
        'message' => ''
    ];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'File upload error occurred';
        return $response;
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $response['message'] = 'Invalid file type. Only JPG, PNG, GIF, and WEBP allowed';
        return $response;
    }
    
    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        $response['message'] = 'File size too large. Maximum 5MB allowed';
        return $response;
    }
    
    // Create upload directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $response['success'] = true;
        $response['image_path'] = $filename; // Store only filename
        $response['message'] = 'File uploaded successfully';
    } else {
        $response['message'] = 'Failed to upload file';
    }
    
    return $response;
}

/**
 * Handle image URL
 */
function handle_image_url($url) {
    $response = [
        'success' => false,
        'image_path' => null,
        'image_type' => 'url',
        'message' => ''
    ];
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $response['message'] = 'Invalid URL format';
        return $response;
    }
    
    // Check if URL is accessible (optional but recommended)
    $headers = @get_headers($url);
    if ($headers === false || strpos($headers[0], '200') === false) {
        $response['message'] = 'Image URL is not accessible';
        return $response;
    }
    
    // Validate if URL points to an image
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $url_extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    
    if (!in_array($url_extension, $image_extensions)) {
        // Check content type from headers
        $is_image = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type: image/') !== false) {
                $is_image = true;
                break;
            }
        }
        
        if (!$is_image) {
            $response['message'] = 'URL does not point to a valid image';
            return $response;
        }
    }
    
    $response['success'] = true;
    $response['image_path'] = $url;
    $response['message'] = 'Image URL validated successfully';
    
    return $response;
}

/**
 * Display image based on type
 * 
 * @param string $image_path - Image filename or URL
 * @param string $image_type - 'upload' or 'url'
 * @param string $upload_dir - Upload directory path
 * @param string $alt - Alt text for image
 * @return string - Complete image path/URL
 */
function get_image_src($image_path, $image_type, $upload_dir = 'uploads/') {
    if (empty($image_path)) {
        return 'assets/images/no-image.png'; // Default placeholder
    }
    
    if ($image_type === 'url') {
        return $image_path;
    } else {
        return $upload_dir . $image_path;
    }
}

/**
 * Delete image file (only for uploaded files)
 */
function delete_image($image_path, $image_type, $upload_dir = 'uploads/') {
    if ($image_type === 'upload' && !empty($image_path)) {
        $filepath = $upload_dir . $image_path;
        if (file_exists($filepath)) {
            unlink($filepath);
            return true;
        }
    }
    return false;
}
?>