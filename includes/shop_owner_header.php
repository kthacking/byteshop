<?php
/**
 * ByteShop - Shop Owner Header Include
 * Common header/navbar for all shop owner pages
 */

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get owner info
$owner_id = get_user_id();
$owner_name = get_user_name();

// Get owner's market info for quick stats
$market_info = null;
try {
    $stmt = $pdo->prepare("
        SELECT m.market_id, m.market_name, m.location, m.rating,
               COUNT(DISTINCT p.product_id) as product_count,
               COUNT(DISTINCT oi.order_id) as pending_orders
        FROM markets m
        LEFT JOIN products p ON m.market_id = p.market_id AND p.status = 'active'
        LEFT JOIN order_items oi ON m.market_id = oi.market_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND o.order_status IN ('placed', 'packed')
        WHERE m.owner_id = ? AND m.status = 'active'
        GROUP BY m.market_id
        LIMIT 1
    ");
    $stmt->execute([$owner_id]);
    $market_info = $stmt->fetch();
} catch(PDOException $e) {
    // Silently fail
}
?>

<!DOCTYPE html>
<style>
    /* CSS Variables for dark theme */
    :root {
        --so-bg: rgba(26, 26, 26, 0.8);
        --so-backdrop: blur(16px);
        --so-border: 1px solid rgba(255, 107, 53, 0.2);
        --so-primary: #ff6b35;
        --so-primary-dark: #f7931e;
        --so-text-main: #ffffff;
        --so-text-muted: #a0a0a0;
        --so-danger: #ff4757;
        --so-shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
        --so-radius: 11px;
        --so-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* Shop Owner Header */
    .shop-owner-header {
        background: var(--so-bg);
        backdrop-filter: var(--so-backdrop);
        -webkit-backdrop-filter: var(--so-backdrop);
        border-bottom: 1px solid rgba(255, 107, 53, 0.15);
        padding: 0.72rem 0;
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: var(--so-transition);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .shop-owner-header:hover {
        box-shadow: 0 6px 30px rgba(255, 107, 53, 0.2);
    }

    .shop-owner-header-content {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 1.8rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.35rem;
    }

    /* Logo Styling */
    .shop-owner-logo {
        display: flex;
        align-items: center;
        gap: 0.54rem;
        font-size: 1.26rem;
        font-weight: 800;
        text-decoration: none;
        color: var(--so-text-main);
        letter-spacing: -0.5px;
        transition: var(--so-transition);
    }

    .shop-owner-logo:hover {
        transform: translateY(-1px);
    }

    .shop-owner-logo-icon {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 1.44rem;
        filter: drop-shadow(0 2px 6px rgba(255, 107, 53, 0.4));
    }

    /* Navigation */
    .shop-owner-nav {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        background: rgba(255, 255, 255, 0.05);
        padding: 0.27rem;
        border-radius: 90px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .shop-owner-nav-link {
        position: relative;
        color: var(--so-text-muted);
        text-decoration: none;
        padding: 0.54rem 1.26rem;
        border-radius: 90px;
        font-weight: 600;
        font-size: 0.81rem;
        transition: var(--so-transition);
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .shop-owner-nav-link:hover {
        color: var(--so-primary);
        background: rgba(255, 107, 53, 0.1);
        box-shadow: 0 2px 8px rgba(255, 107, 53, 0.2);
    }

    .shop-owner-nav-link.active {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(255, 107, 53, 0.4);
    }

    .shop-owner-nav-link.active::after {
        display: none;
    }

    /* Badges */
    .shop-owner-badge {
        background: var(--so-danger);
        color: white;
        padding: 0.14rem 0.45rem;
        border-radius: 5.4px;
        font-size: 0.59rem;
        font-weight: 700;
        box-shadow: 0 2px 5px rgba(255, 71, 87, 0.4);
    }

    /* User Section */
    .shop-owner-user-section {
        display: flex;
        align-items: center;
        gap: 1.35rem;
    }

    /* Market Badge */
    .shop-owner-market-badge {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        color: var(--so-text-main);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        padding-right: 1.35rem;
    }

    .shop-owner-market-name {
        font-weight: 700;
        font-size: 0.81rem;
        color: var(--so-text-main);
    }

    .shop-owner-market-stats {
        font-size: 0.68rem;
        color: var(--so-text-muted);
        font-weight: 500;
        background: rgba(0, 212, 170, 0.15);
        color: #00d4aa;
        padding: 2px 5.4px;
        border-radius: 3.6px;
        margin-top: 1.8px;
        border: 1px solid rgba(0, 212, 170, 0.3);
    }

    /* User Info */
    .shop-owner-user-info {
        display: flex;
        align-items: center;
        gap: 0.72rem;
        background: transparent;
        padding: 0;
        box-shadow: none;
    }

    .shop-owner-avatar {
        width: 37.8px;
        height: 37.8px;
        border-radius: 10.8px;
        background: linear-gradient(135deg, rgba(255, 107, 53, 0.2) 0%, rgba(247, 147, 30, 0.2) 100%);
        color: #ff6b35;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.99rem;
        border: 2px solid rgba(255, 107, 53, 0.3);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        transition: var(--so-transition);
    }

    .shop-owner-avatar:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 15px rgba(255, 107, 53, 0.4);
    }

    .shop-owner-user-details {
        display: flex;
        flex-direction: column;
    }

    .shop-owner-user-name {
        font-weight: 700;
        font-size: 0.81rem;
        color: var(--so-text-main);
    }

    .shop-owner-user-role {
        font-size: 0.63rem;
        color: var(--so-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Logout Button */
    .shop-owner-logout-link {
        color: var(--so-text-muted);
        text-decoration: none;
        padding: 0.54rem 0.9rem;
        border-radius: 7.2px;
        transition: var(--so-transition);
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        font-size: 0.81rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.36rem;
    }

    .shop-owner-logout-link:hover {
        background: rgba(255, 71, 87, 0.15);
        color: var(--so-danger);
        border-color: rgba(255, 71, 87, 0.3);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
    }

    /* Mobile Menu Toggle */
    .shop-owner-menu-toggle {
        display: none;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: var(--so-text-main);
        font-size: 1.08rem;
        padding: 0.45rem;
        border-radius: 7.2px;
        cursor: pointer;
        transition: var(--so-transition);
    }

    .shop-owner-menu-toggle:hover {
        background: rgba(255, 107, 53, 0.15);
        border-color: rgba(255, 107, 53, 0.3);
    }

    /* Alert for no market */
    .shop-owner-no-market-alert {
        background: rgba(247, 147, 30, 0.15);
        border: 1px solid rgba(247, 147, 30, 0.3);
        color: #f7931e;
        padding: 0.54rem 0.9rem;
        border-radius: 7.2px;
        font-size: 0.77rem;
        font-weight: 600;
    }

    .shop-owner-no-market-alert a {
        color: #ffffff;
        text-decoration: none;
        border-bottom: 1px dashed #ffffff;
        margin-left: 4.5px;
        transition: var(--so-transition);
    }

    .shop-owner-no-market-alert a:hover {
        border-bottom-style: solid;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .shop-owner-menu-toggle {
            display: block;
        }

        .shop-owner-nav {
            position: fixed;
            top: 67.5px;
            left: 0.9rem;
            right: 0.9rem;
            background: rgba(26, 26, 26, 0.98);
            backdrop-filter: blur(20px);
            flex-direction: column;
            padding: 0.9rem;
            border-radius: 14.4px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            transform: translateY(-10px) scale(0.95);
            opacity: 0;
            pointer-events: none;
            transition: var(--so-transition);
            z-index: 999;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .shop-owner-nav.active {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: all;
        }

        .shop-owner-nav-link {
            width: 100%;
            justify-content: center;
            padding: 0.72rem;
            color: var(--so-text-main);
        }

        .shop-owner-nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .shop-owner-market-badge,
        .shop-owner-user-info {
            display: none;
        }

        .shop-owner-header-content {
            padding: 0 1.35rem;
        }

        .shop-owner-logo {
            font-size: 1.08rem;
        }

        .shop-owner-logo-icon {
            font-size: 1.26rem;
        }
    }

    @media (max-width: 768px) {
        .shop-owner-header-content {
            padding: 0 0.9rem;
        }

        .shop-owner-user-section {
            gap: 0.9rem;
        }

        .shop-owner-logout-link {
            padding: 0.45rem 0.72rem;
            font-size: 0.72rem;
        }
    }
</style>
<header class="shop-owner-header">
    <div class="shop-owner-header-content">
        <a href="index.php" class="shop-owner-logo">
            <span class="shop-owner-logo-icon">🛒</span>
            <span>ByteShop</span>
            <span style="font-size: 0.72rem; opacity: 0.9;">| Shop Owner</span>
        </a>

        <button class="shop-owner-menu-toggle" onclick="toggleShopOwnerMenu()">
            ☰
        </button>

        <nav class="shop-owner-nav" id="shopOwnerNav">
            <a href="index.php" class="shop-owner-nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <span>📊</span>
                <span>Dashboard</span>
            </a>

            <a href="my_market.php" class="shop-owner-nav-link <?php echo $current_page === 'my_market.php' ? 'active' : ''; ?>">
                <span>🏪</span>
                <span>My Market</span>
            </a>

            <a href="products.php" class="shop-owner-nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <span>📦</span>
                <span>Products</span>
                <?php if ($market_info && $market_info['product_count'] > 0): ?>
                    <span class="shop-owner-badge"><?php echo $market_info['product_count']; ?></span>
                <?php endif; ?>
            </a>

            <a href="orders.php" class="shop-owner-nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                <span>📋</span>
                <span>Orders</span>
                <?php if ($market_info && $market_info['pending_orders'] > 0): ?>
                    <span class="shop-owner-badge"><?php echo $market_info['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <div class="shop-owner-user-section">
            <?php if ($market_info): ?>
                <div class="shop-owner-market-badge">
                    <div class="shop-owner-market-name">
                        <span>🏪</span>
                        <span><?php echo htmlspecialchars($market_info['market_name']); ?></span>
                    </div>
                    <div class="shop-owner-market-stats">
                        📍 <?php echo htmlspecialchars($market_info['location']); ?> | 
                        ⭐ <?php echo number_format($market_info['rating'], 1); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="shop-owner-no-market-alert">
                    <span>⚠️</span>
                    <span>No market yet! <a href="my_market.php">Create one</a></span>
                </div>
            <?php endif; ?>

            <div class="shop-owner-user-info">
                <div class="shop-owner-avatar">
                    <?php echo strtoupper(substr($owner_name, 0, 1)); ?>
                </div>
                <div class="shop-owner-user-details">
                    <span class="shop-owner-user-name"><?php echo htmlspecialchars($owner_name); ?></span>
                    <span class="shop-owner-user-role">Shop Owner</span>
                </div>
            </div>

            <a href="../logout.php" class="shop-owner-logout-link">
                <span>🚪</span> <span>Logout</span>
            </a>
        </div>
    </div>
</header>

<script>
    // Toggle mobile menu
    function toggleShopOwnerMenu() {
        const nav = document.getElementById('shopOwnerNav');
        nav.classList.toggle('active');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const nav = document.getElementById('shopOwnerNav');
        const toggle = document.querySelector('.shop-owner-menu-toggle');
        
        if (window.innerWidth <= 1024) {
            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                nav.classList.remove('active');
            }
        }
    });

    // Close mobile menu on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            document.getElementById('shopOwnerNav').classList.remove('active');
        }
    });

    // Close mobile menu when clicking on a link
    document.querySelectorAll('.shop-owner-nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                document.getElementById('shopOwnerNav').classList.remove('active');
            }
        });
    });
</script>