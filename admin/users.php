<?php
/**
 * ByteShop - Admin Users Management
 * Manage all users (Admin, Shop Owners, Customers)
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Require admin access
require_admin();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $user_id = clean_input($_POST['user_id']);
                $new_status = clean_input($_POST['status']);
                
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                $stmt->execute([$new_status, $user_id]);
                
                $_SESSION['success'] = "User status updated successfully!";
                header('Location: users.php');
                exit;
                break;
                
            case 'delete_user':
                $user_id = clean_input($_POST['user_id']);
                
                // Don't allow deleting yourself
                if ($user_id == get_user_id()) {
                    $_SESSION['error'] = "You cannot delete your own account!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $_SESSION['success'] = "User deleted successfully!";
                }
                
                header('Location: users.php');
                exit;
                break;
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
    SUM(CASE WHEN role = 'shop_owner' THEN 1 ELSE 0 END) as total_owners,
    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as total_customers,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users
FROM users";
$stats = $pdo->query($stats_query)->fetch();
?>

<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - ByteShop Admin</title>
    <style>
        /* CSS reset and fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary: #FF4B2B;
            --primary-dark: #cc3a20;
            --bg-light: #F9FAFB;
            --text-dark: #1F2937;
            --text-gray: #6B7280;
            --border-color: #e5e7eb;
            --card-radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            /* Grid Pattern */
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar h1 span { color: var(--primary); }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .logout-btn {
            background: #fff;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #FFF5F5;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Nav Pills */
        .nav-links {
            display: inline-flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 0.5rem;
            background: #fff;
            border-radius: 50px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 0.5rem 1.2rem;
            color: var(--text-gray);
            text-decoration: none;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .nav-links a:hover {
            color: var(--text-dark);
            background: #f3f4f6;
        }

        .nav-links a.active {
            background: #000;
            color: #fff;
        }

        /* Section Title */
        .header {
            margin-bottom: 2rem;
        }

        .header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--text-gray);
            font-size: 0.95rem;
        }

        /* Stats Grid - Vibrant Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 120px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        /* Gradients */
        .bg-orange { background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); }
        .bg-purple { background: linear-gradient(135deg, #A855F7 0%, #FF6B35 100%); }
        .bg-blue { background: linear-gradient(135deg, #3B82F6 0%, #2DD4BF 100%); }
        .bg-dark { background: linear-gradient(135deg, #1F2937 0%, #111827 100%); }

        .stat-card h3 {
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            z-index: 2;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -1px;
            z-index: 2;
        }

        .stat-card .icon-overlay {
            position: absolute;
            right: -20px;
            bottom: -20px;
            font-size: 8rem;
            opacity: 0.1;
            transform: rotate(-15deg);
        }

        /* Filters */
        .filters {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .filters form {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .filters label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .filters input, .filters select {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            background: #f9fafb;
            color: var(--text-dark);
            transition: all 0.2s;
        }

        .filters input:focus, .filters select:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 75, 43, 0.1);
        }

        .filters button {
            padding: 0.6rem 1.5rem;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            height: 42px;
        }

        .filters button:hover {
            background: var(--primary);
            transform: translateY(-1px);
        }

        /* Table */
        .table-container {
            background: #fff;
            border-radius: var(--card-radius);
            border: 1px solid var(--border-color);
            overflow-x: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-gray);
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fafb; }

        /* Actions */
        .btn {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 0.5rem;
        }

        .btn-warning { background: #fee2e2; color: #991b1b; } /* Deactivate style */
        .btn-warning:hover { background: #fecaca; }

        .btn-success { background: #dcfce7; color: #166534; } /* Activate style */
        .btn-success:hover { background: #bbf7d0; }

        .btn-danger { background: #f3f4f6; color: #1f2937; } /* Delete style (subtle) */
        .btn-danger:hover { background: #fee2e2; color: #ef4444; }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge.admin { background: #FEF3C7; color: #D97706; }
        .badge.shop_owner { background: #E0F2FE; color: #0284C7; }
        .badge.customer { background: #F3E8FF; color: #9333EA; }
        
        .badge.active { background: #ECFDF5; color: #059669; }
        .badge.inactive { background: #F3F4F6; color: #6B7280; }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filters form { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>🛒 Market<span>X</span> Admin</h1>
        <div class="user-info">
            <span>👋 <?php echo htmlspecialchars(get_user_name()); ?></span>
            <a href="../logout.php" class="logout-btn">Log Output</a>
        </div>
    </nav>

    <div class="container">
        <!-- Nav Pills -->
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="users.php" class="active">Users</a>
            <a href="markets.php">Markets</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="analytics.php">Reports</a>
        </div>

        <div class="header">
            <h2>Users Management</h2>
            <p>Manage access and accounts for all system users.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                ✅ &nbsp; <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                ❌ &nbsp; <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics (Vibrant Cards) -->
        <div class="stats-grid">
            <div class="stat-card bg-blue">
                <div class="icon-overlay">👥</div>
                <h3>Total Users</h3>
                <div class="number"><?php echo number_format($stats['total_users']); ?></div>
            </div>
            
            <div class="stat-card bg-orange">
                <div class="icon-overlay">⚡</div>
                <h3>Active Users</h3>
                <div class="number"><?php echo number_format($stats['active_users']); ?></div>
            </div>
            
            <div class="stat-card bg-dark">
                <div class="icon-overlay">🏪</div>
                <h3>Shop Owners</h3>
                <div class="number"><?php echo number_format($stats['total_owners']); ?></div>
            </div>
            
            <div class="stat-card bg-purple">
                <div class="icon-overlay">🛍️</div>
                <h3>Customers</h3>
                <div class="number"><?php echo number_format($stats['total_customers']); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Name or Email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="shop_owner" <?php echo $role_filter === 'shop_owner' ? 'selected' : ''; ?>>Shop Owner</option>
                        <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 0;">
                    <button type="submit">Filter Users</button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>#<?php echo $user['user_id']; ?></td>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <?php if($user['phone']): ?>
                                        <div style="font-size:0.8rem; color:#6B7280;"><?php echo htmlspecialchars($user['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge <?php echo $user['role']; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span></td>
                                <td><span class="badge <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['user_id'] != get_user_id()): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="btn <?php echo $user['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge" style="background:#F3F4F6; color:#9CA3AF;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: #6B7280;">
                                No users found matching your filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>