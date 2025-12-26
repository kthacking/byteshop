<?php
session_start();
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : '';

// Build query with filters
$query = "SELECT * FROM markets WHERE status = 'active'";
$params = [];

if (!empty($search)) {
    $query .= " AND market_name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($location_filter)) {
    $query .= " AND location = ?";
    $params[] = $location_filter;
}

if (!empty($category_filter)) {
    $query .= " AND market_category = ?";
    $params[] = $category_filter;
}

if (!empty($rating_filter)) {
    $query .= " AND rating >= ?";
    $params[] = $rating_filter;
}

$query .= " ORDER BY rating DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$markets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique locations for filter dropdown
$locations_query = "SELECT DISTINCT location FROM markets WHERE status = 'active' ORDER BY location";
$locations_stmt = $pdo->query($locations_query);
$locations = $locations_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique categories for filter dropdown
$categories_query = "SELECT DISTINCT market_category FROM markets WHERE status = 'active' ORDER BY market_category";
$categories_stmt = $pdo->query($categories_query);
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ByteShop - Explore Markets</title>
    <link rel="stylesheet" href="../assets/css/customer-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ===== Global Styles ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f7fa;
    color: #333;
    line-height: 1.6;
}

.container {
    max-width: 100%;
    margin: 0 auto;
    padding: 20px 20px;
}

/* ===== Navigation Bar ===== */
/* .navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.5rem;
    font-weight: bold;
}

.nav-brand i {
    font-size: 1.8rem;
}

.nav-links {
    display: flex;
    gap: 30px;
    align-items: center;
}

.nav-links a {
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 8px 15px;
    border-radius: 5px;
}

.nav-links a:hover,
.nav-links a.active {
    background-color: rgba(255,255,255,0.2);
} */

/* ===== Hero Section ===== */
.hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    text-align: center;
}

.hero h2 {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.hero p {
    font-size: 1.2rem;
    opacity: 0.9;
}

/* ===== Filter Section ===== */
.filter-section {
    background: white;
    padding: 30px 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.search-box {
    display: flex;
    max-width: 600px;
    margin: 0 auto;
    width: 100%;
}

.search-box input {
    flex: 1;
    padding: 12px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 50px 0 0 50px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s;
}

.search-box input:focus {
    border-color: #667eea;
}

.search-box button {
    padding: 12px 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 0 50px 50px 0;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s;
}

.search-box button:hover {
    transform: scale(1.05);
}

.filters {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.filters select {
    padding: 10px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.95rem;
    outline: none;
    cursor: pointer;
    background-color: white;
    transition: all 0.3s;
}

.filters select:hover,
.filters select:focus {
    border-color: #667eea;
}

.clear-filters {
    padding: 10px 20px;
    background-color: #f44336;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s;
    display: inline-block;
}

.clear-filters:hover {
    background-color: #d32f2f;
}

/* ===== Markets Section ===== */
.markets-section {
    padding: 50px 0;
}

.markets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
}

.market-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.market-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.market-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.market-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.market-card:hover .market-image img {
    transform: scale(1.1);
}

.market-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.market-content {
    padding: 20px;
}

.market-content h3 {
    font-size: 1.4rem;
    color: #333;
    margin-bottom: 10px;
}

.market-location {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.market-location i {
    color: #667eea;
}

.market-rating {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 15px;
}

.market-rating i {
    color: #ffc107;
    font-size: 1rem;
}

.rating-value {
    margin-left: 8px;
    font-weight: 600;
    color: #333;
}

.market-description {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
    line-height: 1.5;
}

.btn-explore {
    display: inline-block;
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-explore:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-explore i {
    margin-left: 8px;
}

/* ===== No Results ===== */
.no-results {
    text-align: center;
    padding: 80px 20px;
}

.no-results i {
    font-size: 4rem;
    color: #ccc;
    margin-bottom: 20px;
}

.no-results h3 {
    font-size: 1.8rem;
    color: #333;
    margin-bottom: 10px;
}

.no-results p {
    color: #666;
    margin-bottom: 30px;
}

.btn-primary {
    display: inline-block;
    padding: 12px 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

/* ===== Footer ===== */
.footer {
    background-color: #2c3e50;
    color: white;
    padding: 30px 0;
    text-align: center;
    margin-top: 50px;
}

/* ===== Responsive Design ===== */
/* @media (max-width: 768px) {
    .navbar .container {
        flex-direction: column;
        gap: 15px;
    }

    .nav-links {
        gap: 15px;
    }

    .hero h2 {
        font-size: 2rem;
    }

    .hero p {
        font-size: 1rem;
    }

    .markets-grid {
        grid-template-columns: 1fr;
    }

    .filters {
        flex-direction: column;
    }

    .filters select,
    .clear-filters {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .hero h2 {
        font-size: 1.5rem;
    }

    .market-content h3 {
        font-size: 1.2rem;
    }
} */
</style>
</head>
<body>
    <!-- Navigation
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <i class="fas fa-store"></i>
                <h1>ByteShop</h1>
            </div>
            <div class="nav-links">
                <a href="index.php" class="active">Markets</a>
                <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a href="orders.php"><i class="fas fa-box"></i> Orders</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav> -->
    <?php include '../includes/customer_header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        
        <div class="container">
            <h2>Discover Amazing Markets</h2>
            <p>Shop from multiple vendors, all in one place</p>
        </div>
    </section>

    <!-- Search & Filter Section -->
    <section class="filter-section">
        <div class="container">
            <form method="GET" action="" class="filter-form">
                <!-- Search Bar -->
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search markets..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>

                <!-- Filters -->
                <div class="filters">
                    <select name="location" onchange="this.form.submit()">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>" 
                                    <?php echo $location_filter == $location ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="category" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                    <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="rating" onchange="this.form.submit()">
                        <option value="">All Ratings</option>
                        <option value="4.5" <?php echo $rating_filter == '4.5' ? 'selected' : ''; ?>>4.5+ Stars</option>
                        <option value="4.0" <?php echo $rating_filter == '4.0' ? 'selected' : ''; ?>>4.0+ Stars</option>
                        <option value="3.5" <?php echo $rating_filter == '3.5' ? 'selected' : ''; ?>>3.5+ Stars</option>
                        <option value="3.0" <?php echo $rating_filter == '3.0' ? 'selected' : ''; ?>>3.0+ Stars</option>
                    </select>

                    <?php if ($search || $location_filter || $category_filter || $rating_filter): ?>
                        <a href="index.php" class="clear-filters">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- Markets Grid -->
    <section class="markets-section">
        <div class="container">
            <?php if (count($markets) > 0): ?>
                <div class="markets-grid">
                    <?php foreach ($markets as $market): ?>
                        <div class="market-card">
                            <div class="market-image">
                                <?php if (!empty($market['market_image'])): ?>
                                    <?php
        // Detect if image is URL or local file
        $is_url = preg_match('/^https?:\/\//i', $market['market_image']);
        $image_src = $is_url ? $market['market_image'] : '../uploads/markets/' . $market['market_image'];
        ?>

     <img src="<?php echo htmlspecialchars($image_src); ?>" 
     alt="<?php echo htmlspecialchars($market['market_name']); ?>"
     onerror="this.src='../assets/images/placeholder.jpg'">
                                <?php else: ?>
                                    <img src="../assets/images/default-market.jpg" alt="Default Market">
                                <?php endif; ?>
                                <div class="market-badge"><?php echo htmlspecialchars($market['market_category']); ?></div>
                            </div>
                            
                            <div class="market-content">
                                <h3><?php echo htmlspecialchars($market['market_name']); ?></h3>
                                
                                <div class="market-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($market['location']); ?>
                                </div>
                                
                                <div class="market-rating">
                                    <?php 
                                    $rating = $market['rating'];
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= floor($rating)) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i - 0.5 <= $rating) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                                </div>

                                <?php if (!empty($market['description'])): ?>
                                    <p class="market-description">
                                        <?php echo htmlspecialchars(substr($market['description'], 0, 100)); ?>
                                        <?php echo strlen($market['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>

                                <a href="market.php?id=<?php echo $market['market_id']; ?>" class="btn-explore">
                                    Explore Market <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No markets found</h3>
                    <p>Try adjusting your search or filters</p>
                    <a href="index.php" class="btn-primary">View All Markets</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 ByteShop. All rights reserved.</p>
        </div>
    </footer>

<script src="../assets/js/customer.js"></script>


</body>
</html>