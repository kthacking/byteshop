// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Update quantity (increment/decrement)
async function updateQuantity(cartId, change) {
    const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
    const qtyInput = cartItem.querySelector('.qty-input');
    let currentQty = parseInt(qtyInput.value);
    let newQty = currentQty + change;
    
    if (newQty < 1) {
        if (confirm('Remove this item from cart?')) {
            removeItem(cartId);
        }
        return;
    }
    
    // Get max stock from input
    const maxStock = parseInt(qtyInput.max);
    if (newQty > maxStock) {
        showToast(`Only ${maxStock} items available in stock`, 'error');
        return;
    }
    
    try {
        const response = await fetch('../api/cart.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cart_id: cartId,
                quantity: newQty
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            qtyInput.value = newQty;
            
            // Update subtotal
            const subtotalElement = cartItem.querySelector('.item-subtotal .amount');
            subtotalElement.textContent = '₹' + data.subtotal.toFixed(2);
            
            // Recalculate totals
            recalculateTotals();
            
            // Update cart count in header
            updateCartCount(data.cart_count);
            
            showToast('Quantity updated', 'success');
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to update quantity', 'error');
    }
}

// Update quantity directly from input
async function updateQuantityDirect(cartId, newQty) {
    newQty = parseInt(newQty);
    
    if (isNaN(newQty) || newQty < 1) {
        showToast('Invalid quantity', 'error');
        location.reload();
        return;
    }
    
    try {
        const response = await fetch('../api/cart.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cart_id: cartId,
                quantity: newQty
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
            const subtotalElement = cartItem.querySelector('.item-subtotal .amount');
            subtotalElement.textContent = '₹' + data.subtotal.toFixed(2);
            
            recalculateTotals();
            updateCartCount(data.cart_count);
            
            showToast('Quantity updated', 'success');
        } else {
            showToast(data.message, 'error');
            location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to update quantity', 'error');
        location.reload();
    }
}

// Remove item from cart
async function removeItem(cartId) {
    if (!confirm('Are you sure you want to remove this item?')) {
        return;
    }
    
    try {
        const response = await fetch('../api/cart.php?action=remove', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cart_id: cartId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove item from DOM
            const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
            cartItem.style.opacity = '0';
            cartItem.style.transform = 'translateX(50px)';
            
            setTimeout(() => {
                cartItem.remove();
                
                // Check if cart is empty
                const remainingItems = document.querySelectorAll('.cart-item');
                if (remainingItems.length === 0) {
                    location.reload();
                } else {
                    recalculateTotals();
                    updateCartCount(data.cart_count);
                }
            }, 300);
            
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to remove item', 'error');
    }
}

// Clear entire cart
async function clearCart() {
    if (!confirm('Are you sure you want to clear your entire cart?')) {
        return;
    }
    
    try {
        const response = await fetch('../api/cart.php?action=clear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to clear cart', 'error');
    }
}

// Recalculate totals
function recalculateTotals() {
    const cartItems = document.querySelectorAll('.cart-item');
    let totalItems = 0;
    let subtotal = 0;
    
    cartItems.forEach(item => {
        const quantity = parseInt(item.querySelector('.qty-input').value);
        const subtotalText = item.querySelector('.item-subtotal .amount').textContent;
        const itemSubtotal = parseFloat(subtotalText.replace('₹', '').replace(',', ''));
        
        totalItems += quantity;
        subtotal += itemSubtotal;
    });
    
    const deliveryCharge = subtotal > 0 ? 50 : 0;
    const grandTotal = subtotal + deliveryCharge;
    
    // Update summary
    document.querySelector('.summary-row:nth-child(1) span:first-child').textContent = `Items (${totalItems})`;
    document.querySelector('.summary-row:nth-child(1) span:last-child').textContent = '₹' + subtotal.toFixed(2);
    document.querySelector('.summary-row:nth-child(2) span:last-child').textContent = '₹' + deliveryCharge.toFixed(2);
    document.querySelector('.summary-row.total span:last-child').textContent = '₹' + grandTotal.toFixed(2);
    
    // Update header count
    const headerCartCount = document.querySelector('.nav a[href="cart.php"]');
    if (headerCartCount) {
        headerCartCount.innerHTML = `<i class="fas fa-shopping-cart"></i> Cart (${totalItems})`;
    }
}

// Update cart count in header
function updateCartCount(count) {
    const headerCartCount = document.querySelector('.nav a[href="cart.php"]');
    if (headerCartCount) {
        headerCartCount.innerHTML = `<i class="fas fa-shopping-cart"></i> Cart (${count})`;
    }
}

// Add smooth transitions
document.addEventListener('DOMContentLoaded', function() {
    // Add transition styles
    const cartItems = document.querySelectorAll('.cart-item');
    cartItems.forEach(item => {
        item.style.transition = 'all 0.3s ease';
    });
});

// Prevent form submission on Enter key in quantity input
document.addEventListener('DOMContentLoaded', function() {
    const qtyInputs = document.querySelectorAll('.qty-input');
    qtyInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    });
});