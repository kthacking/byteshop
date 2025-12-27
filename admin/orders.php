<?php
/**
 * ByteShop - Admin Orders Management
 * View and manage all orders across the system
 */

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';
// Require admin access
require_admin();

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $order_id = clean_input($_POST['order_id']);
                $new_status = clean_input($_POST['status']);
                
                $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $stmt->execute([$new_status, $order_id]);
                
                $_SESSION['success'] = "Order status updated successfully!";
                header('Location: orders.php');
                exit;
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
          FROM orders o
          LEFT JOIN users u ON o.customer_id = u.user_id
          WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR o.order_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN order_status = 'placed' THEN 1 ELSE 0 END) as placed_orders,
    SUM(CASE WHEN order_status = 'packed' THEN 1 ELSE 0 END) as packed_orders,
    SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
    SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
FROM orders";
$stats = $pdo->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - ByteShop Admin</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        color: #e0e0e0;
        min-height: 100vh;
    }

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
     .container {
        flex: 1; 
        padding: 27px; /* 90% of 30px */
        max-width: 100%; /* 90% of 1600px */
        margin: 0 auto;
    }


    /* Main Content */
    .main-content {
        flex: 1;
        padding: 27px;
    }

    .header {
        background: rgba(26, 26, 26, 0.6);
        backdrop-filter: blur(10px);
        padding: 25.2px;
        border-radius: 14.4px;
        margin-bottom: 27px;
        box-shadow: 0 7.2px 28.8px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 107, 53, 0.15);
    }

    .header h1 {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 1.98rem;
        font-weight: 700;
        margin-bottom: 7.2px;
    }

    .header p {
        color: #a0a0a0;
        font-size: 0.9rem;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(162px, 1fr));
        gap: 18px;
        margin-bottom: 27px;
    }

    .stat-card {
        background: rgba(26, 26, 26, 0.6);
        backdrop-filter: blur(10px);
        padding: 21.6px;
        border-radius: 14.4px;
        box-shadow: 0 7.2px 28.8px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2.7px;
        background: linear-gradient(90deg, transparent, currentColor, transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-4.5px);
        box-shadow: 0 10.8px 36px rgba(255, 107, 53, 0.3);
        border-color: rgba(255, 107, 53, 0.3);
    }

    .stat-card:hover::before {
        opacity: 1;
    }

    .stat-card h3 {
        color: #a0a0a0;
        font-size: 0.765rem;
        margin-bottom: 10.8px;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        font-weight: 600;
    }

    .stat-card .number {
        font-size: 2.25rem;
        font-weight: 700;
    }

    .stat-card.blue .number {
        color: #4a9eff;
    }

    .stat-card.blue::before {
        color: #4a9eff;
    }

    .stat-card.green .number {
        color: #00d4aa;
    }

    .stat-card.green::before {
        color: #00d4aa;
    }

    .stat-card.orange .number {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.orange::before {
        color: #ff6b35;
    }

    .stat-card.purple .number {
        color: #a55eea;
    }

    .stat-card.purple::before {
        color: #a55eea;
    }

    .stat-card.red .number {
        color: #ff4757;
    }

    .stat-card.red::before {
        color: #ff4757;
    }

    /* Filters */
    .filters {
        background: rgba(26, 26, 26, 0.6);
        backdrop-filter: blur(10px);
        padding: 21.6px;
        border-radius: 14.4px;
        margin-bottom: 22.5px;
        box-shadow: 0 7.2px 28.8px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .filters form {
        display: flex;
        gap: 12.6px;
        flex-wrap: wrap;
        align-items: end;
    }

    .filters .form-group {
        flex: 1;
        min-width: 162px;
    }

    .filters label {
        display: block;
        margin-bottom: 7.2px;
        color: #b0b0b0;
        font-weight: 600;
        font-size: 0.765rem;
        text-transform: uppercase;
        letter-spacing: 0.45px;
    }

    .filters select,
    .filters input {
        width: 100%;
        padding: 10.8px 14.4px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 9px;
        font-size: 0.855rem;
        color: #e0e0e0;
        transition: all 0.3s ease;
    }

    .filters select:focus,
    .filters input:focus {
        outline: none;
        border-color: #ff6b35;
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 2.7px rgba(255, 107, 53, 0.1);
    }

    .filters select option {
        background: #1a1a1a;
        color: #e0e0e0;
    }

    .filters button {
        padding: 10.8px 25.2px;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        color: white;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.855rem;
        transition: all 0.3s ease;
        box-shadow: 0 3.6px 13.5px rgba(255, 107, 53, 0.3);
    }

    .filters button:hover {
        transform: translateY(-1.8px);
        box-shadow: 0 5.4px 22.5px rgba(255, 107, 53, 0.5);
    }

    /* Orders List */
    .orders-list {
        display: flex;
        flex-direction: column;
        gap: 13.5px;
    }

    .order-card {
        background: rgba(26, 26, 26, 0.6);
        backdrop-filter: blur(10px);
        border-radius: 14.4px;
        padding: 18px;
        box-shadow: 0 7.2px 28.8px rgba(0, 0, 0, 0.4);
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .order-card:hover {
        box-shadow: 0 10.8px 36px rgba(255, 107, 53, 0.3);
        border-color: rgba(255, 107, 53, 0.3);
        transform: translateY(-2.7px);
    }

    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 13.5px;
        padding-bottom: 13.5px;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .order-id {
        font-size: 1.62rem;
        font-weight: 700;
        color: #ffffff;
    }

    .order-date {
        color: #a0a0a0;
        font-size: 0.855rem;
    }

    .order-body {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 18px;
        margin-bottom: 13.5px;
    }

    .customer-info h4 {
        color: #ffffff;
        margin-bottom: 7.2px;
        font-size: 1.17rem;
        font-weight: 600;
    }

    .customer-info p {
        color: #a0a0a0;
        font-size: 0.855rem;
        margin: 4.5px 0;
    }

    .order-stats {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .stat-item {
        background: rgba(255, 255, 255, 0.05);
        padding: 10.8px;
        border-radius: 9px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .stat-item .label {
        font-size: 0.72rem;
        color: #a0a0a0;
        text-transform: uppercase;
        letter-spacing: 0.45px;
    }

    .stat-item .value {
        font-size: 1.62rem;
        font-weight: 700;
        color: #ffffff;
        margin-top: 4.5px;
    }

    .order-actions {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    /* Status Badge */
    .status-badge {
        padding: 7.2px 13.5px;
        border-radius: 18px;
        font-size: 0.765rem;
        font-weight: 600;
        display: inline-block;
        border: 1px solid;
        letter-spacing: 0.45px;
    }

    .status-badge.placed {
        background: rgba(74, 158, 255, 0.15);
        color: #4a9eff;
        border-color: rgba(74, 158, 255, 0.3);
    }

    .status-badge.packed {
        background: rgba(247, 147, 30, 0.15);
        color: #f7931e;
        border-color: rgba(247, 147, 30, 0.3);
    }

    .status-badge.shipped {
        background: rgba(165, 94, 234, 0.15);
        color: #a55eea;
        border-color: rgba(165, 94, 234, 0.3);
    }

    .status-badge.delivered {
        background: rgba(0, 212, 170, 0.15);
        color: #00d4aa;
        border-color: rgba(0, 212, 170, 0.3);
    }

    .status-badge.cancelled {
        background: rgba(255, 71, 87, 0.15);
        color: #ff4757;
        border-color: rgba(255, 71, 87, 0.3);
    }

    /* Buttons */
    .btn {
        padding: 9px 13.5px;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        font-size: 0.855rem;
        text-align: center;
        width: 100%;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .btn-primary {
        background: rgba(74, 158, 255, 0.2);
        color: #4a9eff;
        border-color: rgba(74, 158, 255, 0.3);
    }

    .btn-primary:hover {
        background: rgba(74, 158, 255, 0.3);
        transform: translateY(-1.8px);
        box-shadow: 0 3.6px 13.5px rgba(74, 158, 255, 0.3);
    }

    .btn-success {
        background: rgba(0, 212, 170, 0.2);
        color: #00d4aa;
        border-color: rgba(0, 212, 170, 0.3);
    }

    .btn-success:hover {
        background: rgba(0, 212, 170, 0.3);
        transform: translateY(-1.8px);
        box-shadow: 0 3.6px 13.5px rgba(0, 212, 170, 0.3);
    }

    .btn-info {
        background: rgba(74, 158, 255, 0.2);
        color: #4a9eff;
        border-color: rgba(74, 158, 255, 0.3);
    }

    .btn-info:hover {
        background: rgba(74, 158, 255, 0.3);
        transform: translateY(-1.8px);
        box-shadow: 0 3.6px 13.5px rgba(74, 158, 255, 0.3);
    }

    /* Status Dropdown */
    select.status-select {
        padding: 9px 12.6px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 9px;
        cursor: pointer;
        color: #e0e0e0;
        font-size: 0.855rem;
        width: 100%;
        transition: all 0.3s ease;
    }

    select.status-select:focus {
        outline: none;
        border-color: #ff6b35;
        background: rgba(255, 255, 255, 0.08);
    }

    select.status-select option {
        background: #1a1a1a;
        color: #e0e0e0;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(8px);
        z-index: 1000;
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: rgba(26, 26, 26, 0.95);
        backdrop-filter: blur(10px);
        padding: 31.5px;
        border-radius: 18px;
        max-width: 540px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        border: 1px solid rgba(255, 107, 53, 0.3);
        box-shadow: 0 18px 54px rgba(0, 0, 0, 0.6);
    }

    .modal-content h3 {
        margin-bottom: 18px;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 1.44rem;
        font-weight: 700;
    }

    .modal-close {
        float: right;
        font-size: 25.2px;
        cursor: pointer;
        color: #a0a0a0;
        transition: color 0.3s ease;
    }

    .modal-close:hover {
        color: #ff4757;
    }

    .order-item {
        padding: 13.5px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 9px;
        margin: 9px 0;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .order-item h4 {
        color: #ffffff;
        margin-bottom: 4.5px;
        font-size: 1.08rem;
    }

    .order-item p {
        color: #a0a0a0;
        font-size: 0.855rem;
        margin: 2.7px 0;
    }

    /* Alerts */
    .alert {
        padding: 13.5px 19.8px;
        margin-bottom: 21.6px;
        border-radius: 10.8px;
        font-weight: 500;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(0, 212, 170, 0.15);
        color: #00d4aa;
        border-color: rgba(0, 212, 170, 0.3);
    }

    .no-orders {
        text-align: center;
        padding: 72px 18px;
        color: #a0a0a0;
        font-size: 1.08rem;
        background: rgba(26, 26, 26, 0.6);
        backdrop-filter: blur(10px);
        border-radius: 14.4px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .no-orders h2 {
        color: #ffffff;
        margin-bottom: 10.8px;
        font-size: 1.62rem;
    }

    /* Order Details Modal Styles */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(9px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .od-modal-body {
        font-family: 'Inter', -apple-system, sans-serif;
        background-color: rgba(26, 26, 26, 0.95);
        color: #e0e0e0;
        padding: 22.5px;
        animation: fadeIn 0.3s ease-out;
        border-radius: 14.4px;
    }

    .od-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 22.5px;
    }

    .od-title-group h2 {
        margin: 0;
        font-size: 1.44rem;
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 700;
    }

    .od-title-group span {
        font-size: 0.81rem;
        color: #a0a0a0;
    }

    .od-actions .btn-action {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 7.2px 13.5px;
        border-radius: 7.2px;
        color: #e0e0e0;
        cursor: pointer;
        font-size: 0.765rem;
        font-weight: 600;
        transition: all 0.2s;
        margin-left: 7.2px;
    }

    .od-actions .btn-action:hover {
        background: rgba(255, 107, 53, 0.2);
        border-color: rgba(255, 107, 53, 0.3);
    }

    /* Timeline */
    .od-timeline {
        display: flex;
        justify-content: space-between;
        margin: 27px 0 36px 0;
        position: relative;
        padding: 0 18px;
    }

    .od-timeline::before {
        content: '';
        position: absolute;
        top: 12.6px;
        left: 36px;
        right: 36px;
        height: 2.7px;
        background: rgba(255, 255, 255, 0.15);
        z-index: 0;
    }

    .od-step {
        position: relative;
        z-index: 1;
        text-align: center;
        width: 25%;
    }

    .od-step-circle {
        width: 28.8px;
        height: 28.8px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 50%;
        margin: 0 auto 7.2px auto;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 12.6px;
        transition: background 0.3s;
    }

    .od-step-label {
        font-size: 0.675rem;
        font-weight: 600;
        color: #a0a0a0;
        text-transform: uppercase;
    }

    .od-step.active .od-step-circle {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        box-shadow: 0 0 0 3.6px rgba(255, 107, 53, 0.2);
    }

    .od-step.active .od-step-label {
        color: #ff6b35;
    }

    .od-step.completed .od-step-circle {
        background: #00d4aa;
    }

    /* Content Cards */
    .od-content-wrapper {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10.8px;
        box-shadow: 0 1.8px 7.2px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .od-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .od-box {
        padding: 22.5px;
    }

    .od-box:first-child {
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .od-subtitle {
        font-size: 0.675rem;
        text-transform: uppercase;
        color: #a0a0a0;
        font-weight: 700;
        margin-bottom: 13.5px;
        letter-spacing: 0.45px;
    }

    .od-data-point {
        margin-bottom: 7.2px;
        display: flex;
        align-items: flex-start;
        gap: 9px;
        font-size: 0.855rem;
        color: #e0e0e0;
    }

    /* Product Table */
    .od-products-table {
        width: 100%;
        border-collapse: collapse;
    }

    .od-products-table th {
        text-align: left;
        padding: 13.5px 22.5px;
        background: rgba(255, 255, 255, 0.05);
        color: #a0a0a0;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .od-products-table td {
        padding: 18px 22.5px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: middle;
    }

    .od-product-flex {
        display: flex;
        align-items: center;
        gap: 13.5px;
    }

    .od-thumb {
        width: 45px;
        height: 45px;
        border-radius: 7.2px;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Summary */
    .od-summary-row {
        display: flex;
        justify-content: flex-end;
        padding: 9px 22.5px;
    }

    .od-summary-row span:first-child {
        width: 135px;
        text-align: right;
        color: #a0a0a0;
        margin-right: 18px;
    }

    .od-summary-row span:last-child {
        width: 90px;
        text-align: right;
        font-weight: 600;
        color: #ffffff;
    }

    .od-summary-total {
        background: rgba(255, 255, 255, 0.05);
        padding: 18px 22.5px;
        margin-top: 9px;
        border-top: 1px solid rgba(255, 255, 255, 0.15);
    }

    .od-summary-total span:last-child {
        background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 1.08rem;
        font-weight: 800;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="markets.php">Markets</a>
            <a href="products.php">Products</a>
            <a href="orders.php" class="active">Orders</a>
            <a href="analytics.php">Analytics & Reports</a>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Orders Management</h1>
                <p>View and manage all customer orders</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
            </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <h3>Total Orders</h3>
                    <div class="number"><?php echo $stats['total_orders']; ?></div>
                </div>
                <div class="stat-card green">
                    <?php
function format_indian_short($num) {
    $num = (float)$num; // Ensure it's a number

    if ($num >= 10000000) {
        // 1 Crore = 1,00,00,000
        return '₹' . round($num / 10000000, 2) . 'Cr';
    } elseif ($num >= 100000) {
        // 1 Lakh = 1,00,000
        return '₹' . round($num / 100000, 2) . 'L';
    } elseif ($num >= 1000) {
        // 1 Thousand = 1,000
        return '₹' . round($num / 1000, 2) . 'K';
    }
    
    // Default (Less than 1k)
    return '₹' . number_format($num, 0);
}
?>
                    <h3>Total Revenue</h3>
                    <div class="number">
                        <?php echo format_indian_short($stats['total_revenue'] ?? 0); ?>
                    </div>
                </div>
                <div class="stat-card orange">
                    <h3>Placed</h3>
                    <div class="number"><?php echo $stats['placed_orders']; ?></div>
                </div>
                <div class="stat-card purple">
                    <h3>Shipped</h3>
                    <div class="number"><?php echo $stats['shipped_orders']; ?></div>
                </div>
                <div class="stat-card green">
                    <h3>Delivered</h3>
                    <div class="number"><?php echo $stats['delivered_orders']; ?></div>
                </div>
                <div class="stat-card red">
                    <h3>Cancelled</h3>
                    <div class="number"><?php echo $stats['cancelled_orders']; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Order ID / Customer"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="placed" <?php echo $status_filter === 'placed' ? 'selected' : ''; ?>>Placed
                            </option>
                            <option value="packed" <?php echo $status_filter === 'packed' ? 'selected' : ''; ?>>Packed
                            </option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>
                                Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>
                                Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>
                                Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit">Filter</button>
                    </div>
                </form>
            </div>

            <!-- Orders List -->
            <?php if (count($orders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                            <div class="order-date">📅
                                <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
                        </div>
                        <span class="status-badge <?php echo $order['order_status']; ?>">
                            <?php echo strtoupper($order['order_status']); ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="customer-info">
                            <h4>👤 <?php echo htmlspecialchars($order['customer_name']); ?></h4>
                            <p>📧 <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <p>📱 <?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
                            <p style="margin-top: 10px;">
                                <strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                            </p>
                        </div>

                        <div class="order-stats">
                            <div class="stat-item">
                                <div class="label">Total Amount</div>
                                <div class="value">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="label">Items</div>
                                <div class="value"><?php echo $order['item_count']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="label">Payment</div>
                                <div class="value" style="font-size: 12.6px;"><?php echo $order['payment_method']; ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-actions">
                            <button class="btn btn-info" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                👁 View Details
                            </button>

                            <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <select name="status" class="status-select"
                                    onchange="if(confirm('Update order status?')) this.form.submit();">
                                    <option value="">Change Status</option>
                                    <?php if ($order['order_status'] === 'placed'): ?>
                                    <option value="packed">Mark as Packed</option>
                                    <?php endif; ?>
                                    <?php if ($order['order_status'] === 'packed'): ?>
                                    <option value="shipped">Mark as Shipped</option>
                                    <?php endif; ?>
                                    <?php if ($order['order_status'] === 'shipped'): ?>
                                    <option value="delivered">Mark as Delivered</option>
                                    <?php endif; ?>
                                    <option value="cancelled">Cancel Order</option>
                                </select>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-orders">
                <h2>No Orders Found</h2>
                <p>Try adjusting your filters or search terms</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeOrderModal()">&times;</span>
            <h3>Order Details</h3>
            <div id="orderDetailsContent">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    function viewOrderDetails(orderId) {
        const modal = document.getElementById('orderModal');
        const contentDiv = document.getElementById('orderDetailsContent');

        modal.classList.add('show');
        contentDiv.innerHTML =
            '<div style="padding:50px; text-align:center;"><div class="spinner-border text-primary"></div></div>';

        fetch(`../api/get_order_details.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order || data.data.order;
                    const items = data.items || data.data.items;

                    const steps = ['pending', 'processing', 'shipped', 'delivered'];
                    const currentStatus = order.order_status.toLowerCase();
                    let activeIndex = steps.indexOf(currentStatus);
                    if (activeIndex === -1 && currentStatus === 'completed') activeIndex = 3;

                    const renderTimeline = () => {
                        if (currentStatus === 'cancelled') {
                            return `<div class="alert alert-danger" style="margin:20px 0; text-align:center;">🚫 This order has been Cancelled</div>`;
                        }

                        let timelineHtml = '<div class="od-timeline">';
                        const labels = ['Order Placed', 'Processing', 'Out for Delivery', 'Delivered'];

                        steps.forEach((step, index) => {
                            let className = 'od-step';
                            if (index < activeIndex) className += ' completed';
                            if (index === activeIndex) className += ' active';

                            timelineHtml += `
                        <div class="${className}">
                            <div class="od-step-circle">${index + 1}</div>
                            <div class="od-step-label">${labels[index]}</div>
                        </div>`;
                        });
                        timelineHtml += '</div>';
                        return timelineHtml;
                    };

                    const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                    const grandTotal = parseFloat(order.total_amount);
                    const shippingCost = grandTotal > subtotal ? (grandTotal - subtotal) : 0;

                    let html = `
            <div class="od-modal-body">
                <div class="od-top-bar">
                    <div class="od-title-group">
                        <h2>Order #${order.order_id}</h2>
                        <span>Placed on ${new Date(order.order_date).toLocaleDateString()} at ${new Date(order.order_date).toLocaleTimeString()}</span>
                    </div>
                    <div class="od-actions">
                        <button class="btn-action" onclick="printOrderDetails()">🖨️ Print</button>
                        <button class="btn-action">⬇️ Invoice</button>
                    </div>
                </div>

                ${renderTimeline()}

                <div class="od-content-wrapper">
                    <div class="od-details-grid">
                        <div class="od-box">
                            <div class="od-subtitle">Customer Details</div>
                            <div class="od-data-point"><strong>${order.customer_name}</strong></div>
                            <div class="od-data-point">📧 ${order.customer_email}</div>
                            <div class="od-data-point">📞 ${order.customer_phone || '--'}</div>
                        </div>
                        <div class="od-box">
                            <div class="od-subtitle">Shipping To</div>
                            <div class="od-data-point">📍 ${order.delivery_address.replace(/\n/g, '<br>')}</div>
                            <div style="margin-top:15px;" class="od-subtitle">Payment Method</div>
                            <div class="od-data-point">💳 ${order.payment_method}</div>
                        </div>
                    </div>

                    <table class="od-products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th style="text-align:right;">Cost</th>
                            </tr>
                        </thead>
                        <tbody>`;

                    items.forEach(item => {
                        let img = item.product_image ?
                            (item.product_image.startsWith('http') ? item.product_image :
                                `../uploads/products/${item.product_image}`) :
                            '../assets/images/default-product.jpg';

                        html += `
                    <tr>
                        <td>
                            <div class="od-product-flex">
                                <img src="${img}" class="od-thumb" onerror="this.src='../assets/images/default-product.jpg'">
                                <div>
                                    <div style="font-weight:600; color:#ffffff;">${item.product_name}</div>
                                    <div style="font-size:0.72rem; color:#a0a0a0;">Seller: ${item.market_name}</div>
                                </div>
                            </div>
                        </td>
                        <td style="color:#a0a0a0;">${item.category}</td>
                        <td style="text-align:right;">
                            <div style="color:#ffffff; font-weight:600;">₹${parseFloat(item.subtotal).toFixed(2)}</div>
                            <div style="font-size:0.675rem; color:#a0a0a0;">${item.quantity} x ₹${parseFloat(item.price).toFixed(2)}</div>
                        </td>
                    </tr>`;
                    });

                    html += `
                        </tbody>
                    </table>

                    <div style="padding: 18px 0;">
                        <div class="od-summary-row">
                            <span>Subtotal</span>
                            <span>₹${subtotal.toFixed(2)}</span>
                        </div>
                        <div class="od-summary-row">
                            <span>Shipping & Handling</span>
                            <span>${shippingCost > 0 ? '₹'+shippingCost.toFixed(2) : 'Free'}</span>
                        </div>
                        <div class="od-summary-row od-summary-total">
                            <span>Grand Total</span>
                            <span>₹${grandTotal.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>`;

                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<div class="alert alert-danger m-4">${data.message}</div>`;
                }
            })
            .catch(err => console.error(err));
    }

    function printOrderDetails() {
        const printContent = document.getElementById('orderDetailsContent').innerHTML;
        const originalContent = document.body.innerHTML;

        document.body.innerHTML = `
        <html>
            <head>
                <title>Print Order</title>
                <style>
                    @media print {
                        @page {
                            size: A4;
                            margin: 13.5mm;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            font-family: Arial, sans-serif;
                        }
                        .od-actions {
                            display: none !important;
                        }
                        .od-modal-body {
                            box-shadow: none !important;
                        }
                    }
                    ${getOrderModalStyles()}
                </style>
            </head>
            <body>
                ${printContent}
            </body>
        </html>
    `;

        window.print();
        document.body.innerHTML = originalContent;
        window.location.reload();
    }

    function getOrderModalStyles() {
        return `
        .od-modal-body { max-width: 810px; margin: 0 auto; }
        .od-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 13.5px; border-bottom: 1.8px solid #e0e0e0; }
        .od-title-group h2 { margin: 0; color: #2c3e50; font-size: 1.62rem; }
        .od-title-group span { color: #7f8c8d; font-size: 0.81rem; }
        .od-actions { display: flex; gap: 9px; }
        .btn-action { padding: 7.2px 14.4px; border: 1px solid #ddd; background: white; border-radius: 4.5px; cursor: pointer; font-size: 0.81rem; }
        .od-timeline { display: flex; justify-content: space-between; margin: 27px 0; position: relative; }
        .od-timeline::before { content: ''; position: absolute; top: 18px; left: 0; right: 0; height: 1.8px; background: #e0e0e0; z-index: 0; }
        .od-step { flex: 1; text-align: center; position: relative; z-index: 1; }
        .od-step-circle { width: 36px; height: 36px; border-radius: 50%; background: #e0e0e0; color: #999; display: flex; align-items: center; justify-content: center; margin: 0 auto 9px; font-weight: bold; transition: all 0.3s; }
        .od-step.completed .od-step-circle { background: #27ae60; color: white; }
        .od-step.active .od-step-circle { background: #3498db; color: white; box-shadow: 0 0 0 3.6px rgba(52, 152, 219, 0.2); }
        .od-step-label { font-size: 0.765rem; color: #7f8c8d; font-weight: 500; }
        .od-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 27px 0; }
        .od-box { background: #f8f9fa; padding: 18px; border-radius: 7.2px; }
        .od-subtitle { font-weight: 700; color: #2c3e50; margin-bottom: 10.8px; font-size: 0.855rem; text-transform: uppercase; letter-spacing: 0.45px; }
        .od-data-point { margin: 7.2px 0; color: #555; line-height: 1.6; }
        .od-products-table { width: 100%; border-collapse: collapse; margin: 18px 0; }
        .od-products-table thead { background: #34495e; color: white; }
        .od-products-table th, .od-products-table td { padding: 13.5px; text-align: left; border-bottom: 0.9px solid #ecf0f1; }
        .od-product-flex { display: flex; align-items: center; gap: 13.5px; }
        .od-thumb { width: 54px; height: 54px; object-fit: cover; border-radius: 7.2px; border: 0.9px solid #ddd; }
        .od-summary-row { display: flex; justify-content: space-between; padding: 10.8px 0; border-bottom: 0.9px solid #ecf0f1; font-size: 0.855rem; }
        .od-summary-total { font-weight: 700; font-size: 1.08rem; color: #2c3e50; border-top: 1.8px solid #34495e; margin-top: 9px; padding-top: 13.5px; }
    `;
    }

    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('show');
    }

    document.getElementById('orderModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });
    </script>

</body>

</html>