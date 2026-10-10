<?php
require_once 'config/db.php';

// Fetch menu items from your existing 'menu' table
$featured_items = [];
try {
    $stmt = $pdo->query("SELECT * FROM menu LIMIT 3");
    $featured_items = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fallback if table is empty
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene TephTeph Tapsilogan - Home</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Paint Layout CSS (Dark & Gold Theme) -->
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }

        /* Top Header Container */
        .top-nav-bar {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            margin-bottom: -25px; /* Creates the overlapping circle effect from your drawing */
            z-index: 10;
        }

        /* Circle Logo (Top-Left in Paint Wireframe) */
        .logo-circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3px solid #ffc107;
            background-color: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.7);
            margin-left: 10px;
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* 4 Navigation Rectangle Buttons (Top-Right in Paint Wireframe) */
        .nav-buttons-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-rect {
            color: #ffc107;
            background-color: #1a1a1a;
            border: 2px solid #ffc107;
            padding: 8px 18px;
            border-radius: 6px; /* Rectangular style like your drawing */
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-rect:hover {
            background-color: #ffc107;
            color: #000000;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.6);
        }

        .btn-rect.active {
            background-color: #ffc107;
            color: #000000;
        }

        /* Large Outer Container (Main Content Box in Paint Wireframe) */
        .main-wireframe-box {
            border: 2px solid #ffc107;
            border-radius: 12px;
            background-color: #1a1a1a;
            padding: 40px 30px 30px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
            min-height: 550px;
        }

        /* Section Formatting Inside Box */
        .hero-title {
            color: #ffc107;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .history-card {
            background-color: #121212;
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 10px;
            padding: 25px;
            color: #dddddd;
            line-height: 1.8;
        }

        .history-card strong {
            color: #ffc107;
        }

        footer {
            text-align: center;
            color: #777777;
            font-size: 0.85rem;
            margin-top: 25px;
        }
    </style>
</head>
<body>

    <!-- TOP HEADER LAYER: Overlapping Circle Logo + 4 Right-aligned Buttons -->
    <div class="top-nav-bar">
        <!-- Circle Logo -->
        <div class="logo-circle">
            <a href="index.php">
                <img src="Logo Website Draft✨    Ashiee - Reyn (1).png" alt="STT Logo">
            </a>
        </div>

        <!-- 4 Rectangular Nav Buttons -->
        <div class="nav-buttons-group">
            <a href="index.php" class="btn-rect active">Home</a>
            <a href="menu.php" class="btn-rect">Menu</a>
            <a href="order.php" class="btn-rect">Order Now</a>
            <a href="tambay_caps.php" target="_blank" class="btn-rect">Tambay Caps</a>
        </div>
    </div>

    <!-- MAIN CONTAINER BOX (Matching your inner Paint rectangle) -->
    <main class="main-wireframe-box">
        
        <!-- Hero Title Header -->
        <section class="text-center my-4">
            <h1 class="display-5 hero-title">Sairene TephTeph Tapsilogan</h1>
            <p class="lead text-muted">Your Favorite Neighborhood Tapsilogan & Tambayan</p>
        </section>

        <hr class="border-warning opacity-25 my-4">

        <!-- History Content Area -->
        <section id="history" class="my-4">
            <h3 class="text-center text-gold mb-3 fw-bold">Brief History of the Tapsilogan</h3>
            <div class="history-card max-w-3xl mx-auto">
                <p class="mb-3">
                    At <strong>Sairene TephTeph Tapsilogan</strong>, we built our spot for one main reason: to feed hungry people delicious, authentic Pinoy comfort food whenever they need it most. We're proud to be the trusted go-to haven for late-night workers, night shift crews, and friends looking for a satisfying meal to wrap up the day. No matter how late it gets, if you're hungry, we're ready to serve you hot, flavorful silog meals made to fuel your night.
                </p>
                <p class="mb-0">
                    Specializing in classic Pinoy <em>silog</em> meals—from savory marinated beef <strong>Tapa</strong> to crispy <strong>Tocino</strong> and juicy <strong>Longganisa</strong> paired with garlic fried rice and perfectly cooked eggs—we take pride in serving high-quality, delicious meals at affordable prices.
                </p>
            </div>
        </section>

        <!-- Experience Section -->
        <section class="text-center my-5">
            <h4 class="text-gold fw-bold">The Tapsilogan Experience</h4>
            <p class="text-muted max-w-xl mx-auto mt-2">
                More than just a place to eat, <strong>Sairene TephTeph Tapsilogan</strong> is designed as a cozy community hub. Whether you're catching up with friends, taking a study break, or grabbing a late-night bite, our doors are always open.
            </p>
        </section>

    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan. All Rights Reserved.</p>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>
</body>
</html>