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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ===== Global Styles ===== */
        :root {
            --primary: #FF6B35;
            --primary-light: #FFF0EB;
            --secondary: #2D3436;
            --text-color: #636e72;
            --bg-color: #F9FAFB;
            --card-bg: #FFFFFF;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.01);
            --radius-sm: 12px;
            --radius-md: 20px;
            --radius-lg: 30px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--secondary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* ===== Hero Section ===== */
        .hero {
            padding: 80px 0 60px;
            background: linear-gradient(120deg, #FFFFFF 0%, #FFF5F2 100%);
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--primary-light) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero-content {
            text-align: center;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero h2 {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--secondary);
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        .hero h2 span {
            color: var(--primary);
            position: relative;
            display: inline-block;
        }
        
        .hero h2 span::after {
            content: '';
            position: absolute;
            bottom: 8px;
            left: 0;
            width: 100%;
            height: 12px;
            background: rgba(255, 107, 53, 0.15);
            z-index: -1;
            border-radius: 4px;
        }

        .hero p {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 2.5rem;
        }

        /* Search Box */
        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 50px;
            padding: 8px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            border: 1px solid rgba(0,0,0,0.03);
            transition: transform 0.3s ease;
        }

        .search-container:hover {
            transform: translateY(-2px);
        }

        .search-container input {
            flex: 1;
            border: none;
            outline: none;
            padding: 12px 25px;
            font-size: 1rem;
            font-family: inherit;
            background: transparent;
            color: var(--secondary);
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.3);
        }

        .search-btn:hover {
            transform: scale(1.05);
            background: #ff5722;
        }

        /* ===== Filter Chips ===== */
        .filter-scroll {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding: 1rem 0;
            margin-top: 1rem;
            justify-content: center;
            scrollbar-width: none;
        }
        .filter-scroll::-webkit-scrollbar { display: none; }

        .filter-chip {
            padding: 0.6rem 1.2rem;
            background: white;
            border: 1px solid #eee;
            border-radius: 30px;
            font-size: 0.9rem;
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-chip:hover, .filter-chip.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .filter-chip i { font-size: 0.8rem; }

        /* ===== Section Header ===== */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
        }

        .section-title h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
        }

        .section-title p {
            color: var(--text-color);
            font-size: 0.95rem;
            margin-top: 5px;
        }

        /* ===== Markets Grid ===== */
        .markets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .market-card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border: 1px solid rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            text-decoration: none;
        }

        .market-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.1);
        }

        .market-image-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .market-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .market-card:hover .market-image {
            transform: scale(1.1);
        }

        .market-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--secondary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .rating-badge {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--secondary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .rating-badge i { color: #FFD700; }

        .market-details {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .market-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .market-location {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-color);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .market-location i { color: var(--primary); }

        .market-desc {
            color: #888;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .card-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f0f0f0;
            padding-top: 1rem;
        }
        
        .btn-visit {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: gap 0.2s;
        }
        
        .market-card:hover .btn-visit { gap: 12px; }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        .no-results i { font-size: 4rem; color: #ddd; margin-bottom: 1rem; }
        .no-results h3 { font-size: 1.5rem; color: var(--secondary); margin-bottom: 0.5rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h2 { font-size: 2.2rem; }
            .filter-scroll { justify-content: flex-start; padding: 1rem 1rem; }
            .markets-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-content">
            <h2>Find The Best <br><span>Shops Near You</span></h2>
            <p>Order from your favorite local markets with just a few clicks.</p>
            
            <form method="GET" action="" class="search-container">
                <input type="text" name="search" placeholder="What are you looking for?" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>

            <div class="filter-scroll">
                <a href="index.php" class="filter-chip <?php echo ($location_filter == '' && $category_filter == '') ? 'active' : ''; ?>">
                    All Markets
                </a>
                
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?php echo urlencode($cat); ?>" class="filter-chip <?php echo $category_filter == $cat ? 'active' : ''; ?>">
                        <?php 
                            $icon = 'fa-tag';
                            if(stripos($cat, 'elect') !== false) $icon = 'fa-laptop';
                            elseif(stripos($cat, 'fash') !== false) $icon = 'fa-tshirt';
                            elseif(stripos($cat, 'food') !== false) $icon = 'fa-utensils';
                            elseif(stripos($cat, 'groc') !== false) $icon = 'fa-carrot';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Markets Section -->
    <div class="container">
        <div class="section-header">
            <div class="section-title">
                <h3>Popular Markets</h3>
                <p><?php echo count($markets); ?> markets available</p>
            </div>
            
             <form method="GET" class="mobile-hidden">
                <select name="location" onchange="this.form.submit()" style="padding: 10px; border-radius: 10px; border: 1px solid #ddd; outline:none;">
                    <option value="">📍 All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location_filter == $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (count($markets) > 0): ?>
            <div class="markets-grid">
                <?php foreach ($markets as $market): ?>
                    <a href="market.php?id=<?php echo $market['market_id']; ?>" class="market-card">
                        <div class="market-image-wrapper">
                            <?php 
                                $is_url = preg_match('/^https?:\/\//i', $market['market_image']);
                                $image_src = $is_url ? $market['market_image'] : '../uploads/markets/' . $market['market_image'];
                            ?>
                            <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                 alt="<?php echo htmlspecialchars($market['market_name']); ?>" 
                                 class="market-image"
                                 onerror="this.src='../assets/images/default-market.jpg'">
                                 
                            <div class="market-badge">
                                <i class="fas fa-store"></i> <?php echo htmlspecialchars($market['market_category']); ?>
                            </div>
                            
                            <div class="rating-badge">
                                <i class="fas fa-star"></i> <?php echo number_format($market['rating'], 1); ?>
                            </div>
                        </div>
                        
                        <div class="market-details">
                            <div class="market-title"><?php echo htmlspecialchars($market['market_name']); ?></div>
                            <div class="market-location">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($market['location']); ?>
                            </div>
                            
                            <?php if (!empty($market['description'])): ?>
                                <p class="market-desc">
                                    <?php echo htmlspecialchars($market['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="card-footer">
                                <span class="btn-visit">Visit Market <i class="fas fa-arrow-right"></i></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-store-slash"></i>
                <h3>No markets found</h3>
                <p>Try changing your search terms or filters.</p>
                <a href="index.php" style="display:inline-block; margin-top:1rem; padding:10px 20px; background:var(--primary); color:white; text-decoration:none; border-radius:50px;">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Space -->
    <div style="height: 50px;"></div>

</body>
</html>