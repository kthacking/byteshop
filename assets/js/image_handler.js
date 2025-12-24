/**
 * ByteShop - Image Input Handler
 * Manages file upload and URL input
 */

let selectedImageType = null;

// Handle file selection
function handleFileSelect(input) {
    const urlInput = document.getElementById('image_url');
    const preview = document.getElementById('image_preview');
    const previewImg = document.getElementById('preview_img');
    
    if (input.files && input.files[0]) {
        // Clear URL input
        urlInput.value = '';
        urlInput.disabled = true;
        
        // Validate file
        const file = input.files[0];
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!validTypes.includes(file.type)) {
            alert('Invalid file type. Please select an image file.');
            input.value = '';
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('File size too large. Maximum 5MB allowed.');
            input.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        
        selectedImageType = 'file';
    }
}

// Handle URL input
function handleUrlInput(input) {
    const fileInput = document.getElementById('image_file');
    const preview = document.getElementById('image_preview');
    const previewImg = document.getElementById('preview_img');
    const url = input.value.trim();
    
    if (url) {
        // Clear file input
        fileInput.value = '';
        fileInput.disabled = true;
        
        // Validate URL format
        try {
            new URL(url);
        } catch (e) {
            alert('Invalid URL format');
            input.value = '';
            return;
        }
        
        // Show preview
        previewImg.src = url;
        previewImg.onerror = function() {
            alert('Failed to load image from URL');
            input.value = '';
            preview.style.display = 'none';
            fileInput.disabled = false;
        };
        previewImg.onload = function() {
            preview.style.display = 'block';
        };
        
        selectedImageType = 'url';
    }
}

// Clear image selection
function clearImageSelection() {
    const fileInput = document.getElementById('image_file');
    const urlInput = document.getElementById('image_url');
    const preview = document.getElementById('image_preview');
    
    fileInput.value = '';
    fileInput.disabled = false;
    urlInput.value = '';
    urlInput.disabled = false;
    preview.style.display = 'none';
    selectedImageType = null;
}

// Form validation before submit
function validateImageInput() {
    const fileInput = document.getElementById('image_file');
    const urlInput = document.getElementById('image_url');
    
    const hasFile = fileInput.files.length > 0;
    const hasUrl = urlInput.value.trim() !== '';
    
    if (!hasFile && !hasUrl) {
        alert('Please provide either an image file or image URL');
        return false;
    }
    
    return true;
}