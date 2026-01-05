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
        background: rgba(26, 26, 26, 0.95);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 2px solid transparent;
        border-image: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
        border-image-slice: 1;
    }

    .customer-header-content {
        max-width: 1260px;
        margin: 0 auto;
        padding: 0.9rem 1.8rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .customer-logo {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        text-decoration: none;
        font-size: 1.62rem;
        font-weight: 800;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        transition: transform 0.3s ease;
        filter: drop-shadow(0 2px 6px rgba(255, 107, 53, 0.4));
    }

    .customer-logo:hover {
        transform: scale(1.05);
    }

    .customer-nav {
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .customer-nav-link {
        position: relative;
        color: #e0e0e0;
        text-decoration: none;
        padding: 0.63rem 1.08rem;
        border-radius: 9px;
        font-weight: 600;
        font-size: 0.86rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        border: 1px solid transparent;
    }

    .customer-nav-link:hover {
        background: rgba(255, 107, 53, 0.15);
        color: #ff6b35;
        transform: translateY(-2px);
        border-color: rgba(255, 107, 53, 0.3);
    }

    .customer-nav-link.active {
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.2) 0%, rgba(247, 147, 30, 0.2) 100%);
        color: #ff6b35;
        border-color: rgba(255, 107, 53, 0.3);
    }

    .customer-nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 27px;
        height: 2.7px;
        background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
        border-radius: 9px 9px 0 0;
        box-shadow: 0 2px 8px rgba(255, 107, 53, 0.5);
    }

    .customer-cart-link {
        position: relative;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%) !important;
        color: white !important;
        padding: 0.63rem 1.35rem !important;
        border-radius: 22.5px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(255, 107, 53, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .customer-cart-link:hover {
        background: linear-gradient(135deg, #f7931e 0%, #ff6b35 100%) !important;
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(255, 107, 53, 0.6);
    }

    .customer-cart-badge {
        position: absolute;
        top: -7.2px;
        right: -7.2px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        width: 21.6px;
        height: 21.6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.68rem;
        font-weight: bold;
        border: 2.7px solid rgba(26, 26, 26, 0.95);
        animation: customerPulse 2s infinite;
        box-shadow: 0 2px 8px rgba(255, 71, 87, 0.5);
    }

    @keyframes customerPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .customer-user-info {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.63rem 1.08rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 22.5px;
        font-weight: 600;
        color: #e0e0e0;
        margin-left: 0.45rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .customer-user-info:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 107, 53, 0.3);
    }

    .customer-user-avatar {
        width: 28.8px;
        height: 28.8px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.81rem;
        box-shadow: 0 2px 8px rgba(255, 107, 53, 0.4);
    }

    .customer-logout-link {
        color: #ff4757 !important;
        font-weight: 700;
    }

    .customer-logout-link:hover {
        background: rgba(255, 71, 87, 0.15) !important;
        color: #ff6b81 !important;
        border-color: rgba(255, 71, 87, 0.3) !important;
    }

    /* Mobile Menu Toggle */
    .customer-menu-toggle {
        display: none;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        font-size: 1.35rem;
        cursor: pointer;
        color: #e0e0e0;
        padding: 0.45rem;
        border-radius: 7.2px;
        transition: all 0.3s ease;
    }

    .customer-menu-toggle:hover {
        background: rgba(255, 107, 53, 0.15);
        border-color: rgba(255, 107, 53, 0.3);
        color: #ff6b35;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .customer-header-content {
            padding: 0.9rem 1.35rem;
        }

        .customer-nav {
            gap: 0.27rem;
        }

        .customer-nav-link {
            padding: 0.54rem 0.9rem;
            font-size: 0.81rem;
        }
    }

    @media (max-width: 768px) {
        .customer-menu-toggle {
            display: block;
        }

        .customer-nav {
            position: fixed;
            top: 63px;
            left: 0;
            right: 0;
            background: rgba(26, 26, 26, 0.98);
            backdrop-filter: blur(20px);
            flex-direction: column;
            padding: 0.9rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
            transform: translateY(-120%);
            transition: transform 0.3s ease;
            gap: 0.45rem;
            border-bottom: 1px solid rgba(255, 107, 53, 0.3);
        }

        .customer-nav.active {
            transform: translateY(0);
        }

        .customer-nav-link {
            width: 100%;
            justify-content: center;
            padding: 0.9rem;
        }

        .customer-user-info {
            width: 100%;
            justify-content: center;
            margin-left: 0;
        }

        .customer-header-content {
            padding: 0.9rem;
        }

        .customer-logo {
            font-size: 1.35rem;
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

// Close mobile menu on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('customerNav').classList.remove('active');
    }
});

// Close mobile menu when clicking on a link
document.querySelectorAll('.customer-nav-link').forEach(link => {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            document.getElementById('customerNav').classList.remove('active');
        }
    });
});
</script>
