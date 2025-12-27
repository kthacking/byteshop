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
    /* CSS Variables for easy theming */
    :root {
        --so-bg: rgba(255, 255, 255, 0.85);
        --so-backdrop: blur(16px);
        --so-border: 1px solid rgba(255, 255, 255, 0.6);
        --so-primary: #3b82f6; /* Modern Blue */
        --so-primary-dark: #2563eb;
        --so-text-main: #1e293b; /* Slate 800 */
        --so-text-muted: #64748b; /* Slate 500 */
        --so-danger: #ef4444;
        --so-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        --so-radius: 12px;
        --so-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* Shop Owner Header */
    .shop-owner-header {
        background: var(--so-bg);
        backdrop-filter: var(--so-backdrop);
        -webkit-backdrop-filter: var(--so-backdrop);
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 0.8rem 0;
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: var(--so-transition);
    }

    /* Add a subtle shadow on scroll (optional conceptual state) */
    .shop-owner-header:hover {
        box-shadow: var(--so-shadow);
    }

    .shop-owner-header-content {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
    }

    /* Logo Styling */
    .shop-owner-logo {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 1.4rem;
        font-weight: 800;
        text-decoration: none;
        color: var(--so-text-main);
        letter-spacing: -0.5px;
    }

    .shop-owner-logo-icon {
        color: var(--so-primary);
        font-size: 1.6rem;
        filter: drop-shadow(0 2px 4px rgba(59, 130, 246, 0.3));
    }

    /* Navigation */
    .shop-owner-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(241, 245, 249, 0.5); /* Subtle pill background */
        padding: 0.3rem;
        border-radius: 99px; /* Pill shape container */
        border: 1px solid rgba(0,0,0,0.02);
    }

    .shop-owner-nav-link {
        position: relative;
        color: var(--so-text-muted);
        text-decoration: none;
        padding: 0.6rem 1.4rem;
        border-radius: 99px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: var(--so-transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .shop-owner-nav-link:hover {
        color: var(--so-primary);
        background: rgba(255, 255, 255, 0.8);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .shop-owner-nav-link.active {
        background: var(--so-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* Remove the old underline effect for the new pill design */
    .shop-owner-nav-link.active::after {
        display: none;
    }

    /* Badges */
    .shop-owner-badge {
        background: var(--so-danger);
        color: white;
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 700;
        box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
    }

    /* User Section */
    .shop-owner-user-section {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    /* Market Badge */
    .shop-owner-market-badge {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        color: var(--so-text-main);
        border-right: 1px solid rgba(0,0,0,0.08);
        padding-right: 1.5rem;
    }

    .shop-owner-market-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--so-text-main);
    }

    .shop-owner-market-stats {
        font-size: 0.75rem;
        color: var(--so-text-muted);
        font-weight: 500;
        background: rgba(16, 185, 129, 0.1); /* Green tint */
        color: #059669;
        padding: 2px 6px;
        border-radius: 4px;
        margin-top: 2px;
    }

    /* User Info */
    .shop-owner-user-info {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        background: transparent;
        padding: 0;
        box-shadow: none;
    }

    .shop-owner-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px; /* Soft square instead of circle */
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        color: var(--so-primary-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    }

    .shop-owner-user-details {
        display: flex;
        flex-direction: column;
    }

    .shop-owner-user-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--so-text-main);
    }

    .shop-owner-user-role {
        font-size: 0.7rem;
        color: var(--so-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Logout Button */
    .shop-owner-logout-link {
        color: var(--so-text-muted);
        text-decoration: none;
        padding: 0.6rem;
        border-radius: 8px;
        transition: var(--so-transition);
        border: 1px solid rgba(0,0,0,0.08);
        background: white;
    }

    .shop-owner-logout-link:hover {
        background: #fef2f2;
        color: var(--so-danger);
        border-color: #fee2e2;
        transform: translateY(-1px);
    }

    /* Mobile Menu Toggle */
    .shop-owner-menu-toggle {
        display: none;
        background: transparent;
        border: 1px solid rgba(0,0,0,0.1);
        color: var(--so-text-main);
        font-size: 1.2rem;
        padding: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
    }

    /* Alert for no market */
    .shop-owner-no-market-alert {
        background: #fffbeb;
        border: 1px solid #fcd34d;
        color: #b45309;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .shop-owner-no-market-alert a {
        color: #92400e;
        text-decoration: none;
        border-bottom: 1px dashed #92400e;
        margin-left: 5px;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .shop-owner-menu-toggle {
            display: block;
        }

        .shop-owner-nav {
            position: fixed;
            top: 75px;
            left: 1rem;
            right: 1rem;
            background: white;
            flex-direction: column;
            padding: 1rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transform: translateY(-10px) scale(0.95);
            opacity: 0;
            pointer-events: none;
            transition: var(--so-transition);
            z-index: 999;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .shop-owner-nav.active {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: all;
        }

        .shop-owner-nav-link {
            width: 100%;
            justify-content: center;
            padding: 0.8rem;
            color: var(--so-text-main);
        }

        .shop-owner-nav-link:hover {
            background: #f8fafc;
        }

        .shop-owner-market-badge,
        .shop-owner-user-info {
            display: none;
        }
    }
</style>
<header class="shop-owner-header">
    <div class="shop-owner-header-content">
        <a href="index.php" class="shop-owner-logo">
            <span class="shop-owner-logo-icon">🛒</span>
            <span>ByteShop</span>
            <span style="font-size: 0.8rem; opacity: 0.9;">| Shop Owner</span>
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
                <span>🚪</span> Logout
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