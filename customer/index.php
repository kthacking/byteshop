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
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
    color: #e0e0e0;
    line-height: 1.6;
    min-height: 100vh;
}

.container {
    max-width: 100%;
    margin: 0 auto;
    padding: 18px 18px;
}

/* ===== Hero Section ===== */
.hero {
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    padding: 54px 0;
    text-align: center;
    box-shadow: 0 4px 20px rgba(255, 107, 53, 0.3);
}

.hero h2 {
    font-size: 2.25rem;
    margin-bottom: 9px;
    font-weight: 700;
}

.hero p {
    font-size: 1.08rem;
    opacity: 0.95;
}

/* ===== Filter Section ===== */
.filter-section {
    background: rgba(26, 26, 26, 0.6);
    backdrop-filter: blur(10px);
    padding: 27px 0;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    border-top: 1px solid rgba(255, 107, 53, 0.15);
    border-bottom: 1px solid rgba(255, 107, 53, 0.15);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.search-box {
    display: flex;
    max-width: 540px;
    margin: 0 auto;
    width: 100%;
}

.search-box input {
    flex: 1;
    padding: 10.8px 18px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 45px 0 0 45px;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.3s ease;
    color: #e0e0e0;
}

.search-box input::placeholder {
    color: #777;
}

.search-box input:focus {
    border-color: #ff6b35;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
}

.search-box button {
    padding: 10.8px 27px;
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    border: none;
    border-radius: 0 45px 45px 0;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
}

.search-box button:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 18px rgba(255, 107, 53, 0.5);
}

.filters {
    display: flex;
    gap: 13.5px;
    justify-content: center;
    flex-wrap: wrap;
}

.filters select {
    padding: 9px 18px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 7.2px;
    font-size: 0.86rem;
    outline: none;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #e0e0e0;
}

.filters select option {
    background: #1a1a1a;
    color: #e0e0e0;
}

.filters select:hover,
.filters select:focus {
    border-color: #ff6b35;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
}

.clear-filters {
    padding: 9px 18px;
    background: rgba(255, 71, 87, 0.2);
    color: #ff4757;
    text-decoration: none;
    border-radius: 7.2px;
    font-size: 0.86rem;
    transition: all 0.3s ease;
    display: inline-block;
    font-weight: 600;
    border: 1px solid rgba(255, 71, 87, 0.3);
}

.clear-filters:hover {
    background: rgba(255, 71, 87, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
}

/* ===== Markets Section ===== */
.markets-section {
    padding: 45px 0;
}

.markets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(288px, 1fr));
    gap: 27px;
}

.market-card {
    background: rgba(26, 26, 26, 0.6);
    backdrop-filter: blur(10px);
    border-radius: 13.5px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.market-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 12px 40px rgba(255, 107, 53, 0.3);
    border-color: rgba(255, 107, 53, 0.3);
}

.market-image {
    position: relative;
    height: 180px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.05);
}

.market-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.market-card:hover .market-image img {
    transform: scale(1.1);
}

.market-badge {
    position: absolute;
    top: 13.5px;
    right: 13.5px;
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    padding: 4.5px 13.5px;
    border-radius: 18px;
    font-size: 0.77rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(255, 107, 53, 0.5);
}

.market-content {
    padding: 18px;
}

.market-content h3 {
    font-size: 1.26rem;
    color: #ffffff;
    margin-bottom: 9px;
    font-weight: 700;
}

.market-location {
    display: flex;
    align-items: center;
    gap: 7.2px;
    color: #a0a0a0;
    margin-bottom: 9px;
    font-size: 0.86rem;
}

.market-location i {
    color: #ff6b35;
}

.market-rating {
    display: flex;
    align-items: center;
    gap: 4.5px;
    margin-bottom: 13.5px;
}

.market-rating i {
    color: #f7931e;
    font-size: 0.9rem;
}

.rating-value {
    margin-left: 7.2px;
    font-weight: 600;
    color: #ffffff;
    font-size: 0.9rem;
}

.market-description {
    color: #a0a0a0;
    font-size: 0.81rem;
    margin-bottom: 13.5px;
    line-height: 1.5;
}

.btn-explore {
    display: inline-block;
    width: 100%;
    padding: 10.8px;
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 7.2px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
    font-size: 0.9rem;
}

.btn-explore:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(255, 107, 53, 0.5);
}

.btn-explore i {
    margin-left: 7.2px;
}

/* ===== No Results ===== */
.no-results {
    text-align: center;
    padding: 72px 18px;
    background: rgba(26, 26, 26, 0.6);
    backdrop-filter: blur(10px);
    border-radius: 13.5px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.no-results i {
    font-size: 3.6rem;
    color: #444;
    margin-bottom: 18px;
}

.no-results h3 {
    font-size: 1.62rem;
    color: #ffffff;
    margin-bottom: 9px;
    font-weight: 700;
}

.no-results p {
    color: #a0a0a0;
    margin-bottom: 27px;
    font-size: 0.95rem;
}

.btn-primary {
    display: inline-block;
    padding: 10.8px 27px;
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    text-decoration: none;
    border-radius: 7.2px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
    font-size: 0.9rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(255, 107, 53, 0.5);
}

/* ===== Footer ===== */
.footer {
    background: rgba(26, 26, 26, 0.8);
    backdrop-filter: blur(10px);
    color: white;
    padding: 27px 0;
    text-align: center;
    margin-top: 45px;
    border-top: 1px solid rgba(255, 107, 53, 0.2);
}

.footer p {
    color: #a0a0a0;
    font-size: 0.9rem;
}

/* ===== Responsive Design ===== */
@media (max-width: 768px) {
    .hero h2 {
        font-size: 1.8rem;
    }

    .hero p {
        font-size: 0.9rem;
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

    .container {
        padding: 13.5px 13.5px;
    }
}

@media (max-width: 480px) {
    .hero h2 {
        font-size: 1.35rem;
    }

    .market-content h3 {
        font-size: 1.08rem;
    }

    .hero {
        padding: 36px 0;
    }
}
</style>
</head>
<body>
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