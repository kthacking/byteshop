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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #0a0a0a;
            background-image:url("");
            color: #e0e0e0;
            
            font-size: 14.4px; /* 90% of 16px */
        }
        
        /* Main Content */
        .main-content { flex: 1; padding: 27px; /* 90% of 30px */ }
        .header { 
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 1.8rem; /* 90% of 2rem */
            border-radius: 14.4px; /* 90% of 16px */
            margin-bottom: 27px; /* 90% of 30px */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            border: 1px solid #2a2a2a;
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2.7px; /* 90% of 3px */
            background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%);
        }
        .header h1 { 
            color: #e0e0e0;
            margin-bottom: 9px; /* 90% of 10px */
            font-weight: 700;
            font-size: 1.8rem; /* 90% of 2rem */
        }
        .header p {
            color: #909090;
            font-size: 0.9rem; /* 90% of 1rem */
        }
        
        /* Stats Cards */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* 90% of 200px */
            gap: 18px; /* 90% of 20px */
            margin-bottom: 27px; /* 90% of 30px */
        }
        .stat-card { 
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 1.8rem; /* 90% of 2rem */
            border-radius: 14.4px; /* 90% of 16px */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            border: 1px solid #2a2a2a;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2.7px; /* 90% of 3px */
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4.5px); /* 90% of -5px */
            box-shadow: 0 7.2px 21.6px rgba(255, 107, 53, 0.2); /* 90% scale */
        }
        .stat-card h3 { 
            color: #909090;
            font-size: 0.81rem; /* 90% of 0.9rem */
            margin-bottom: 9px; /* 90% of 10px */
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }
        .stat-card .number { 
            font-size: 2.25rem; /* 90% of 2.5rem */
            font-weight: 700;
        }
        .stat-card.blue .number { 
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.blue::before { background: linear-gradient(90deg, #2196f3 0%, #1976d2 100%); }
        .stat-card.blue:hover::before { opacity: 1; }
        
        .stat-card.green .number { 
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.green::before { background: linear-gradient(90deg, #4caf50 0%, #388e3c 100%); }
        .stat-card.green:hover::before { opacity: 1; }
        
        .stat-card.orange .number { 
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.orange::before { background: linear-gradient(90deg, #ff6b35 0%, #f7931e 100%); }
        .stat-card.orange:hover::before { opacity: 1; }
        
        .stat-card.purple .number { 
            background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.purple::before { background: linear-gradient(90deg, #9c27b0 0%, #7b1fa2 100%); }
        .stat-card.purple:hover::before { opacity: 1; }
        
        /* Filters */
        .filters { 
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            padding: 1.8rem; /* 90% of 2rem */
            border-radius: 14.4px; /* 90% of 16px */
            margin-bottom: 18px; /* 90% of 20px */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            border: 1px solid #2a2a2a;
        }
        .filters form { 
            display: flex; 
            gap: 13.5px; /* 90% of 15px */
            flex-wrap: wrap; 
            align-items: end; 
        }
        .filters .form-group { 
            flex: 1; 
            min-width: 180px; /* 90% of 200px */
        }
        .filters label { 
            display: block; 
            margin-bottom: 7.2px; /* 90% of 8px */
            color: #909090;
            font-weight: 600;
            font-size: 0.765rem; /* 90% of 0.85rem */
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }
        .filters select, .filters input { 
            width: 100%; 
            padding: 10.8px; /* 90% of 12px */
            border: 1px solid #2a2a2a;
            border-radius: 7.2px; /* 90% of 8px */
            background: #0f0f0f;
            color: #e0e0e0;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .filters select:focus, .filters input:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.1); /* 90% of 3px */
        }
        .filters button { 
            padding: 10.8px 27px; /* 90% of 12px 30px */
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white; 
            border: none; 
            border-radius: 7.2px; /* 90% of 8px */
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .filters button:hover { 
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 5.4px 18px rgba(255, 107, 53, 0.4); /* 90% scale */
        }
        
        /* Table */
        .table-container { 
            background: linear-gradient(135deg, #1a1a1a 0%, #161616 100%);
            border-radius: 14.4px; /* 90% of 16px */
            box-shadow: 0 3.6px 14.4px rgba(0,0,0,0.4); /* 90% scale */
            overflow: hidden;
            border: 1px solid #2a2a2a;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        thead { 
            background: #0f0f0f;
        }
        th, td { 
            padding: 0.9rem 1.35rem; /* 90% of 1rem 1.5rem */
            text-align: left; 
        }
        th {
            color: #ff6b35;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.765rem; /* 90% of 0.85rem */
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }
        td {
            color: #b0b0b0;
            font-size: 0.9rem;
        }
        tbody tr { 
            border-bottom: 1px solid #2a2a2a;
        }
        tbody tr:hover { 
            background: #1f1f1f;
        }
        
        /* Badges */
        .badge { 
            padding: 0.36rem 0.9rem; /* 90% of 0.4rem 1rem */
            border-radius: 18px; /* 90% of 20px */
            font-size: 0.675rem; /* 90% of 0.75rem */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.45px; /* 90% of 0.5px */
        }
        .badge.admin { 
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        .badge.shop_owner { 
            background: rgba(33, 150, 243, 0.15);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        .badge.customer { 
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .badge.active { 
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .badge.inactive { 
            background: rgba(158, 158, 158, 0.15);
            color: #9e9e9e;
            border: 1px solid rgba(158, 158, 158, 0.3);
        }
        
        /* Buttons */
        .btn { 
            padding: 7.2px 13.5px; /* 90% of 8px 15px */
            border: none; 
            border-radius: 7.2px; /* 90% of 8px */
            cursor: pointer; 
            font-size: 0.765rem; /* 90% of 0.85rem */
            margin: 0 4.5px; /* 90% of 5px */
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-success { 
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            color: white;
        }
        .btn-success:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 3.6px 10.8px rgba(76, 175, 80, 0.3); /* 90% scale */
        }
        .btn-warning { 
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }
        .btn-warning:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 3.6px 10.8px rgba(255, 152, 0, 0.3); /* 90% scale */
        }
        .btn-danger { 
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-1.8px); /* 90% of -2px */
            box-shadow: 0 3.6px 10.8px rgba(244, 67, 54, 0.3); /* 90% scale */
        }
        
        /* Alerts */
        .alert { 
            padding: 0.9rem 1.35rem; /* 90% of 1rem 1.5rem */
            margin-bottom: 18px; /* 90% of 20px */
            border-radius: 10.8px; /* 90% of 12px */
            font-weight: 500;
            font-size: 0.9rem;
        }
        .alert-success { 
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .alert-error { 
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        
     .container {
            flex: 1; 
            padding: 27px; /* 90% of 30px */
            max-width: 1440px; /* 90% of 1600px */
            margin: 0 auto;
        }
        
    /* Navigation Links */
    .nav-links {
        display: flex;
        gap: 0.72rem; /* 90% of 0.8rem */
        margin-bottom: 2.25rem; /* 90% of 2.5rem */
        padding: 1.08rem; /* 90% of 1.2rem */
        background: #161616;
        border-radius: 14.4px; /* 90% of 16px */
        flex-wrap: wrap;
        border: 1px solid #2a2a2a;
    }

    .nav-links a {
        padding: 0.72rem 1.35rem; /* 90% of 0.8rem 1.5rem */
        background: #1f1f1f;
        color: #b0b0b0;
        text-decoration: none;
        border-radius: 9px; /* 90% of 10px */
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.81rem; /* 90% of 0.9rem */
        border: 1px solid #2a2a2a;
    }

    .nav-links a:hover {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        transform: translateY(-1.8px); /* 90% of -2px */
        box-shadow: 0 5.4px 14.4px rgba(255, 107, 53, 0.3); /* 90% scale */
        border-color: transparent;
    }

    .nav-links a.active {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        border-color: transparent;
    }
        
</style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        
         <div class="nav-links">
        <a href="index.php">Dashboard</a>
        <a href="users.php" class="active">Users</a>
        <a href="markets.php">Markets</a>
        <a href="products.php">Products</a>
        <a href="orders.php">Orders</a>
        <a href="analytics.php">Analytics & Reports</a>
      </div>
    
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Users Management</h1>
                <p>Manage all users, shop owners, and customers</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Active Users</h3>
                    <div class="number"><?php echo $stats['active_users']; ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>Shop Owners</h3>
                    <div class="number"><?php echo $stats['total_owners']; ?></div>
                </div>
                <div class="stat-card purple">
                    <h3>Customers</h3>
                    <div class="number"><?php echo $stats['total_customers']; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name or Email" value="<?php echo htmlspecialchars($search); ?>">
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
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit">Filter</button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
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
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
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
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #9e9e9e;">You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 36px; color: #707070;"> <!-- 90% of 40px -->
                                    No users found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</body>
</html>