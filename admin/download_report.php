<?php
/**
 * ByteShop - Excel Report Generator
 * 
 * Generates Excel reports using PhpSpreadsheet
 * Report Types:
 * - customers: Customer list
 * - market_sales: Market-wise sales
 * - product_sales: Product-wise sales
 * - order_history: Complete order history
 */

require_once '../config/db.php';
require_once '../includes/session.php';

// Require admin access
require_admin();

// Check if PhpSpreadsheet is installed
if (!file_exists('../vendor/autoload.php')) {
    die('Error: PhpSpreadsheet library not found. Please run: composer require phpoffice/phpspreadsheet');
}

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Get parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'customers';
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_market = isset($_GET['market_id']) ? $_GET['market_id'] : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Build WHERE clause for filters
$where_conditions = ["o.order_date BETWEEN :start_date AND :end_date"];
$params = [
    'start_date' => $filter_start_date . ' 00:00:00',
    'end_date' => $filter_end_date . ' 23:59:59'
];

if (!empty($filter_market)) {
    $where_conditions[] = "oi.market_id = :market_id";
    $params['market_id'] = $filter_market;
}

if (!empty($filter_category)) {
    $where_conditions[] = "p.category = :category";
    $params['category'] = $filter_category;
}

$where_clause = implode(' AND ', $where_conditions);

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set creator
$spreadsheet->getProperties()
    ->setCreator('ByteShop Admin')
    ->setTitle('ByteShop Report')
    ->setSubject('Sales Report')
    ->setDescription('Generated report from ByteShop analytics');

// Function to style header row
function styleHeaderRow($sheet, $lastColumn, $row = 1) {
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667EEA']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(25);
}

// Function to auto-size columns
function autoSizeColumns($sheet, $lastColumn) {
    foreach(range('A', $lastColumn) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Generate report based on type
switch($report_type) {
    
    case 'customers':
        // CUSTOMER LIST REPORT
        $sheet->setTitle('Customer List');
        
        // Set headers
        $sheet->setCellValue('A1', 'Customer ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'Phone');
        $sheet->setCellValue('E1', 'Total Orders');
        $sheet->setCellValue('F1', 'Total Spent');
        $sheet->setCellValue('G1', 'Registration Date');
        $sheet->setCellValue('H1', 'Status');
        
        styleHeaderRow($sheet, 'H');
        
        // Fetch customer data
        $query = "
            SELECT 
                u.user_id,
                u.name,
                u.email,
                u.phone,
                COUNT(DISTINCT o.order_id) as total_orders,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                u.created_at,
                u.status
            FROM users u
            LEFT JOIN orders o ON u.user_id = o.customer_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE u.role = 'customer' AND $where_clause
            GROUP BY u.user_id
            ORDER BY total_spent DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        
        // Fill data
        $row = 2;
        foreach($customers as $customer) {
            $sheet->setCellValue("A{$row}", $customer['user_id']);
            $sheet->setCellValue("B{$row}", $customer['name']);
            $sheet->setCellValue("C{$row}", $customer['email']);
            $sheet->setCellValue("D{$row}", $customer['phone'] ?? 'N/A');
            $sheet->setCellValue("E{$row}", $customer['total_orders']);
            $sheet->setCellValue("F{$row}", '₹' . number_format($customer['total_spent'], 2));
            $sheet->setCellValue("G{$row}", date('d-M-Y', strtotime($customer['created_at'])));
            $sheet->setCellValue("H{$row}", ucfirst($customer['status']));
            $row++;
        }
        
        autoSizeColumns($sheet, 'H');
        $filename = 'ByteShop_Customers_' . date('Y-m-d') . '.xlsx';
        break;
    
    case 'market_sales':
        // MARKET-WISE SALES REPORT
        $sheet->setTitle('Market Sales');
        
        // Set headers
        $sheet->setCellValue('A1', 'Market ID');
        $sheet->setCellValue('B1', 'Market Name');
        $sheet->setCellValue('C1', 'Owner Name');
        $sheet->setCellValue('D1', 'Location');
        $sheet->setCellValue('E1', 'Category');
        $sheet->setCellValue('F1', 'Total Orders');
        $sheet->setCellValue('G1', 'Items Sold');
        $sheet->setCellValue('H1', 'Total Revenue');
        $sheet->setCellValue('I1', 'Rating');
        
        styleHeaderRow($sheet, 'I');
        
        // Fetch market sales data
        $query = "
            SELECT 
                m.market_id,
                m.market_name,
                u.name as owner_name,
                m.location,
                m.market_category,
                COUNT(DISTINCT oi.order_id) as total_orders,
                SUM(oi.quantity) as total_items_sold,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                m.rating
            FROM markets m
            LEFT JOIN users u ON m.owner_id = u.user_id
            LEFT JOIN order_items oi ON m.market_id = oi.market_id
            LEFT JOIN orders o ON oi.order_id = o.order_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE $where_clause
            GROUP BY m.market_id
            ORDER BY total_revenue DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $markets = $stmt->fetchAll();
        
        // Fill data
        $row = 2;
        foreach($markets as $market) {
            $sheet->setCellValue("A{$row}", $market['market_id']);
            $sheet->setCellValue("B{$row}", $market['market_name']);
            $sheet->setCellValue("C{$row}", $market['owner_name']);
            $sheet->setCellValue("D{$row}", $market['location']);
            $sheet->setCellValue("E{$row}", $market['market_category']);
            $sheet->setCellValue("F{$row}", $market['total_orders']);
            $sheet->setCellValue("G{$row}", $market['total_items_sold'] ?? 0);
            $sheet->setCellValue("H{$row}", '₹' . number_format($market['total_revenue'], 2));
            $sheet->setCellValue("I{$row}", $market['rating']);
            $row++;
        }
        
        autoSizeColumns($sheet, 'I');
        $filename = 'ByteShop_Market_Sales_' . date('Y-m-d') . '.xlsx';
        break;
    
    case 'product_sales':
        // PRODUCT-WISE SALES REPORT
        $sheet->setTitle('Product Sales');
        
        // Set headers
        $sheet->setCellValue('A1', 'Product ID');
        $sheet->setCellValue('B1', 'Product Name');
        $sheet->setCellValue('C1', 'Category');
        $sheet->setCellValue('D1', 'Market Name');
        $sheet->setCellValue('E1', 'Price');
        $sheet->setCellValue('F1', 'Current Stock');
        $sheet->setCellValue('G1', 'Quantity Sold');
        $sheet->setCellValue('H1', 'Total Orders');
        $sheet->setCellValue('I1', 'Total Revenue');
        $sheet->setCellValue('J1', 'Rating');
        
        styleHeaderRow($sheet, 'J');
        
        // Fetch product sales data
        $query = "
            SELECT 
                p.product_id,
                p.product_name,
                p.category,
                m.market_name,
                p.price,
                p.stock,
                COALESCE(SUM(oi.quantity), 0) as total_quantity_sold,
                COUNT(DISTINCT oi.order_id) as order_count,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                p.rating
            FROM products p
            LEFT JOIN markets m ON p.market_id = m.market_id
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.order_id
            WHERE p.status = 'active' AND $where_clause
            GROUP BY p.product_id
            ORDER BY total_revenue DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Fill data
        $row = 2;
        foreach($products as $product) {
            $sheet->setCellValue("A{$row}", $product['product_id']);
            $sheet->setCellValue("B{$row}", $product['product_name']);
            $sheet->setCellValue("C{$row}", $product['category']);
            $sheet->setCellValue("D{$row}", $product['market_name']);
            $sheet->setCellValue("E{$row}", '₹' . number_format($product['price'], 2));
            $sheet->setCellValue("F{$row}", $product['stock']);
            $sheet->setCellValue("G{$row}", $product['total_quantity_sold']);
            $sheet->setCellValue("H{$row}", $product['order_count']);
            $sheet->setCellValue("I{$row}", '₹' . number_format($product['total_revenue'], 2));
            $sheet->setCellValue("J{$row}", $product['rating']);
            $row++;
        }
        
        autoSizeColumns($sheet, 'J');
        $filename = 'ByteShop_Product_Sales_' . date('Y-m-d') . '.xlsx';
        break;
    
    case 'order_history':
        // ORDER HISTORY REPORT
        $sheet->setTitle('Order History');
        
        // Set headers
        $sheet->setCellValue('A1', 'Order ID');
        $sheet->setCellValue('B1', 'Customer Name');
        $sheet->setCellValue('C1', 'Customer Email');
        $sheet->setCellValue('D1', 'Total Amount');
        $sheet->setCellValue('E1', 'Order Status');
        $sheet->setCellValue('F1', 'Payment Method');
        $sheet->setCellValue('G1', 'Order Date');
        $sheet->setCellValue('H1', 'Delivery Address');
        
        styleHeaderRow($sheet, 'H');
        
        // Fetch order history
        $query = "
            SELECT 
                o.order_id,
                u.name as customer_name,
                u.email,
                o.total_amount,
                o.order_status,
                o.payment_method,
                o.order_date,
                o.delivery_address
            FROM orders o
            JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE $where_clause
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Fill data
        $row = 2;
        foreach($orders as $order) {
            $sheet->setCellValue("A{$row}", $order['order_id']);
            $sheet->setCellValue("B{$row}", $order['customer_name']);
            $sheet->setCellValue("C{$row}", $order['email']);
            $sheet->setCellValue("D{$row}", '₹' . number_format($order['total_amount'], 2));
            $sheet->setCellValue("E{$row}", ucfirst($order['order_status']));
            $sheet->setCellValue("F{$row}", $order['payment_method']);
            $sheet->setCellValue("G{$row}", date('d-M-Y h:i A', strtotime($order['order_date'])));
            $sheet->setCellValue("H{$row}", $order['delivery_address']);
            $row++;
        }
        
        autoSizeColumns($sheet, 'H');
        $filename = 'ByteShop_Order_History_' . date('Y-m-d') . '.xlsx';
        break;
    
    default:
        die('Invalid report type');
}

// Add report generation info
$lastRow = $sheet->getHighestRow() + 2;
$sheet->setCellValue("A{$lastRow}", "Report Generated: " . date('d-M-Y h:i A'));
$sheet->setCellValue("A" . ($lastRow + 1), "Date Range: {$filter_start_date} to {$filter_end_date}");
$sheet->getStyle("A{$lastRow}:A" . ($lastRow + 1))->getFont()->setItalic(true)->setSize(10);

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Write file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>