<?php
/**
 * ByteShop - Admin Sidebar Include
 * Common sidebar for all admin pages
 */

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get admin stats for sidebar
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$customer_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM markets WHERE status = 'active'");
$market_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'placed'");
$pending_orders = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<style>
    /* Reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
         
        min-height: 100vh;
    }

    /* Layout */
    .admin-layout {
        display: flex;
        min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
        width: 280px;
        background: white;
        box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        transition: transform 0.3s;
    }

    .sidebar-header {
        padding: 2rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-align: center;
    }

    .sidebar-header h2 {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .admin-badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .sidebar-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-stat {
        text-align: center;
        padding: 0.5rem;
    }

    .sidebar-stat-number {
        font-size: 1.2rem;
        font-weight: 800;
        display: block;
    }

    .sidebar-stat-label {
        font-size: 0.65rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sidebar-menu {
        padding: 1.5rem 0;
    }

    .sidebar-menu ul {
        list-style: none;
    }

    .sidebar-menu li {
        margin: 0.3rem 0;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.5rem;
        color: #333;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s;
        position: relative;
        border-left: 4px solid transparent;
    }

    .sidebar-menu a:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        border-left-color: #667eea;
        padding-left: 2rem;
    }

    .sidebar-menu a.active {
        background: linear-gradient(90deg, rgba(102, 126, 234, 0.15) 0%, transparent 100%);
        color: #667eea;
        border-left-color: #667eea;
        font-weight: 700;
    }

    .sidebar-menu a.active::after {
        content: '';
        position: absolute;
        right: 1rem;
        width: 8px;
        height: 8px;
        background: #667eea;
        border-radius: 50%;
        animation: activePulse 2s infinite;
    }

    @keyframes activePulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.3); opacity: 0.7; }
    }

    .menu-icon {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
    }

    .pending-badge {
        background: #ff4757;
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        margin-left: auto;
    }

    .sidebar-footer {
        padding: 1.5rem;
        border-top: 2px solid #f0f0f0;
        margin-top: auto;
    }

    .admin-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 12px;
    }

    .admin-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.2rem;
    }

    .admin-info {
        flex: 1;
    }

    .admin-name {
        font-weight: 700;
        color: #333;
        display: block;
        margin-bottom: 0.2rem;
    }

    .admin-role {
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .logout-link {
        color: #e74c3c !important;
        background: rgba(231, 76, 60, 0.1);
        border-radius: 8px;
        margin: 0.5rem;
    }

    .logout-link:hover {
        background: rgba(231, 76, 60, 0.2) !important;
        color: #c0392b !important;
        border-left-color: #e74c3c !important;
    }

    /* Mobile Toggle */
    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: white;
        border: none;
        padding: 0.8rem 1rem;
        border-radius: 10px;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }

    /* Main Content Area */
    .admin-main-content {
        margin-left: 280px;
        flex: 1;
        padding: 2rem;
        min-height: 100vh;
    }

    /* Navigation Links (Top of Container) */
    .nav-links {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 12px;
        flex-wrap: wrap;
    }

    .nav-links a {
        padding: 0.7rem 1.2rem;
        background: white;
        color: #333;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.9rem;
        border: 2px solid transparent;
    }

    .nav-links a:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .nav-links a.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-toggle {
            display: block;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .admin-main-content {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 260px;
        }

        .admin-main-content {
            padding: 1rem;
        }

        .container {
            padding: 1.5rem;
        }

        .nav-links {
            flex-direction: column;
        }

        .nav-links a {
            width: 100%;
            text-align: center;
        }
    }
</style>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" onclick="toggleAdminSidebar()">☰</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleAdminSidebar()"></div>

<!-- Admin Layout -->
<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h2>
                <span>🛒</span>
                <span>ByteShop</span>
            </h2>
            <div class="admin-badge">Admin Panel</div>
            
            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <span class="sidebar-stat-number"><?php echo $customer_count; ?></span>
                    <span class="sidebar-stat-label">Users</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-number"><?php echo $market_count; ?></span>
                    <span class="sidebar-stat-label">Markets</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-number"><?php echo $pending_orders; ?></span>
                    <span class="sidebar-stat-label">Pending</span>
                </div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <ul>
                <li>
                    <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">👥</span>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="markets.php" class="<?php echo $current_page === 'markets.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">🏪</span>
                        <span>Markets</span>
                    </a>
                </li>
                <li>
                    <a href="products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">📦</span>
                        <span>Products</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php" class="<?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">🛒</span>
                        <span>Orders</span>
                        <?php if ($pending_orders > 0): ?>
                            <span class="pending-badge"><?php echo $pending_orders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="analytics.php" class="<?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>">
                        <span class="menu-icon">📈</span>
                        <span>Analytics & Reports</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr(get_user_name(), 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <span class="admin-name"><?php echo htmlspecialchars(get_user_name()); ?></span>
                    <span class="admin-role">Administrator</span>
                </div>
            </div>
            <ul style="list-style: none; margin-top: 1rem;">
                <li>
                    <a href="../logout.php" class="logout-link">
                        <span class="menu-icon">🚪</span>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main-content">

<script>
    // Toggle sidebar on mobile
    function toggleAdminSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Close sidebar when clicking on a link (mobile)
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) {
                toggleAdminSidebar();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
</script>