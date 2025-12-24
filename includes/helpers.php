<?php
/**
 * Get proper image source (URL or local path)
 * 
 * @param string $image_name - Image filename or URL from database
 * @param string $type - 'market' or 'product'
 * @return string - Full image URL/path
 */
function get_image_src($image_name, $type = 'product') {
    if (empty($image_name)) {
        return '../assets/images/default-' . $type . '.jpg';
    }
    
    // Check if it's already a URL
    if (preg_match('/^https?:\/\//i', $image_name)) {
        return htmlspecialchars($image_name);
    }
    
    // It's a local file
    $base_path = ($type === 'market') ? '../uploads/markets/' : '../uploads/products/';
    return $base_path . htmlspecialchars($image_name);
}
?>