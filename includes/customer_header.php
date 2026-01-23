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
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .customer-header-content {
        max-width: 1260px;
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
        font-size: 1.5rem;
        font-weight: 800;
        color: #111;
        letter-spacing: -0.5px;
        transition: transform 0.3s ease;
    }

    .customer-logo span:first-child {
        font-size: 1.8rem;
        background: linear-gradient(135deg, #FF6B35 0%, #FF8F6B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .customer-logo:hover {
        transform: scale(1.02);
    }

    .customer-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .customer-nav-link {
        position: relative;
        color: #666;
        text-decoration: none;
        padding: 0.6rem 1.2rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .customer-nav-link:hover {
        background: #f8f8f8;
        color: #111;
    }

    .customer-nav-link.active {
        background: #111;
        color: white;
    }

    .customer-cart-link {
        position: relative;
        background: #f1f1f1;
        color: #111 !important;
        padding: 0.6rem 1.4rem !important;
    }

    .customer-cart-link:hover {
        background: #e5e5e5 !important;
    }
    
    .customer-cart-link.active {
        background: #FF6B35 !important;
        color: white !important;
    }

    .customer-cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #FF4757;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(255, 71, 87, 0.3);
    }

    .customer-user-info {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.4rem 0.6rem 0.4rem 0.4rem;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 50px;
        font-weight: 600;
        color: #333;
        margin-left: 0.5rem;
        font-size: 0.9rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }

    .customer-user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #666;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .customer-logout-link {
        color: #ff4757 !important;
        padding: 0.6rem !important;
    }

    .customer-logout-link:hover {
        background: #fff0f1 !important;
    }

    /* Mobile Menu Toggle */
    .customer-menu-toggle {
        display: none;
        background: transparent;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #333;
        padding: 0.5rem;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .customer-header-content {
            padding: 1rem;
        }
        .customer-nav-link {
            padding: 0.5rem 0.9rem;
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-150%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            gap: 0.5rem;
            z-index: 999;
        }

        .customer-nav.active {
            transform: translateY(0);
        }

        .customer-nav-link {
            width: 100%;
            justify-content: center;
            padding: 1rem;
            background: #f9f9f9;
        }
        
        .customer-nav-link:hover {
            background: #f0f0f0;
        }

        .customer-user-info {
            width: 100%;
            justify-content: center;
            margin: 0;
            border: none;
            box-shadow: none;
            background: transparent;
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
            <i class="fas fa-bars"></i>
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

            <a href="../logout.php" class="customer-nav-link customer-logout-link" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </nav>
    </div>
</header>

<script>
function toggleCustomerMenu() {
    const nav = document.getElementById('customerNav');
    nav.classList.toggle('active');
    
    // Icon toggle
    const icon = document.querySelector('.customer-menu-toggle i');
    if (nav.classList.contains('active')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
    } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const nav = document.getElementById('customerNav');
    const toggle = document.querySelector('.customer-menu-toggle');
    
    if (window.innerWidth <= 768) {
        if (!nav.contains(event.target) && !toggle.contains(event.target)) {
            nav.classList.remove('active');
            const icon = document.querySelector('.customer-menu-toggle i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});
</script>
