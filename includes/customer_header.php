<?php
/**
 * ByteShop - Customer Header Include
 * Common header/navbar for all customer pages
 */

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE customer_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_result = $stmt->fetch();
    $cart_count = $cart_result['count'];
}
?>

<!DOCTYPE html>
<style>
    /* Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Header Styles */
    .customer-header {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 2px solid transparent;
        border-image: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-image-slice: 1;
    }

    .customer-header-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .customer-logo {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-size: 1.8rem;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        transition: transform 0.3s;
    }

    .customer-logo:hover {
        transform: scale(1.05);
    }

    .customer-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .customer-nav-link {
        position: relative;
        color: #333;
        text-decoration: none;
        padding: 0.7rem 1.2rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .customer-nav-link:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        transform: translateY(-2px);
    }

    .customer-nav-link.active {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        color: #667eea;
    }

    .customer-nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 30px;
        height: 3px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px 10px 0 0;
    }

    .customer-cart-link {
        position: relative;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white !important;
        padding: 0.7rem 1.5rem !important;
        border-radius: 25px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .customer-cart-link:hover {
        background: linear-gradient(135deg, #5568d3 0%, #653a8e 100%) !important;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .customer-cart-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        border: 3px solid white;
        animation: customerPulse 2s infinite;
    }

    @keyframes customerPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .customer-user-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.2rem;
        background: #f8f9fa;
        border-radius: 25px;
        font-weight: 600;
        color: #333;
        margin-left: 0.5rem;
    }

    .customer-user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .customer-logout-link {
        color: #e74c3c !important;
        font-weight: 700;
    }

    .customer-logout-link:hover {
        background: rgba(231, 76, 60, 0.1) !important;
        color: #c0392b !important;
    }

    /* Mobile Menu Toggle */
    .customer-menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #333;
        padding: 0.5rem;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .customer-header-content {
            padding: 1rem 1.5rem;
        }

        .customer-nav {
            gap: 0.3rem;
        }

        .customer-nav-link {
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 768px) {
        .customer-menu-toggle {
            display: block;
        }

        .customer-nav {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            background: white;
            flex-direction: column;
            padding: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transform: translateY(-120%);
            transition: transform 0.3s;
            gap: 0.5rem;
        }

        .customer-nav.active {
            transform: translateY(0);
        }

        .customer-nav-link {
            width: 100%;
            justify-content: center;
            padding: 1rem;
        }

        .customer-user-info {
            width: 100%;
            justify-content: center;
            margin-left: 0;
        }

        .customer-header-content {
            padding: 1rem;
        }
    }
</style>

<header class="customer-header">
    <div class="customer-header-content">
        <a href="index.php" class="customer-logo">
            <span>🛒</span>
            <span>ByteShop</span>
        </a>

        <button class="customer-menu-toggle" onclick="toggleCustomerMenu()">
            ☰
        </button>

        <nav class="customer-nav" id="customerNav">
            <a href="index.php" class="customer-nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <span>🏪</span>
                <span>Markets</span>
            </a>

            <a href="cart.php" class="customer-nav-link customer-cart-link <?php echo $current_page === 'cart.php' ? 'active' : ''; ?>">
                <span>🛒</span>
                <span>Cart</span>
                <?php if ($cart_count > 0): ?>
                    <span class="customer-cart-badge" id="customerCartCount"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="orders.php" class="customer-nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                <span>📦</span>
                <span>My Orders</span>
            </a>

            <div class="customer-user-info">
                <div class="customer-user-avatar">
                    <?php echo strtoupper(substr(get_user_name(), 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars(get_user_name()); ?></span>
            </div>

            <a href="../logout.php" class="customer-nav-link customer-logout-link">
                <span>🚪</span>
                <span>Logout</span>
            </a>
        </nav>
    </div>
</header>

<script>
    // Mobile menu toggle
    function toggleCustomerMenu() {
        const nav = document.getElementById('customerNav');
        nav.classList.toggle('active');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const nav = document.getElementById('customerNav');
        const toggle = document.querySelector('.customer-menu-toggle');
        
        if (window.innerWidth <= 768) {
            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                nav.classList.remove('active');
            }
        }
    });

    // Update cart count dynamically
    function updateCustomerCartCount() {
        fetch('../api/cart.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('customerCartCount');
                    if (badge) {
                        badge.textContent = data.data.count;
                        if (data.data.count > 0) {
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error updating cart count:', error));
    }

    // Update cart count on page load
    window.addEventListener('DOMContentLoaded', updateCustomerCartCount);

    // Update cart count every 30 seconds
    setInterval(updateCustomerCartCount, 30000);
</script>