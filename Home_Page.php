<?php
require_once 'config/db.php';

// Page selection for interactive menu inside home page
$menu_page = isset($_GET['menu_page']) && $_GET['menu_page'] == 2 ? 2 : 1;
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

    <style>
        body {
            background-color: #f8f9fa;
            color: #121212;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }

        /* Large Outer Border Box */
        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 1200px;
            margin: 0 auto;
        }

        /* TOP FEATURED BANNER SPOT */
        .top-banner-spot {
            position: relative;
            border: 2px solid #ffc107;
            border-radius: 10px;
            background: linear-gradient(135deg, #ffffff 0%, #fffdf0 100%);
            padding: 25px 30px;
            margin-bottom: 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: inset 0 0 15px rgba(255, 193, 7, 0.1);
        }

        .banner-logo-container {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 3px solid #ffc107;
            background-color: #ffffff;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .banner-logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .banner-text-content {
            flex-grow: 1;
            padding: 0 25px;
        }

        .banner-text-content h1 {
            color: #d4a000;
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .banner-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .banner-badge {
            background-color: #ffc107;
            color: #121212;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
        }

        /* Double Sub-header Lines */
        .sub-header-line-1, .sub-header-line-2 {
            border: 1px solid #ffc107;
            background-color: #ffffff;
            height: 36px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sub-header-line-1 {
            margin-bottom: 8px;
        }

        .sub-header-line-2 {
            margin-bottom: 20px;
        }

        .nav-link-custom {
            color: #121212;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.85rem;
            padding: 0 18px;
            transition: color 0.2s ease;
        }

        .nav-link-custom:hover {
            color: #d4a000;
        }

        /* Split Section Containers */
        .split-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Left Menu Box */
        .left-menu-box {
            flex: 2;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background-color: #ffffff;
            position: relative;
            padding: 15px;
            min-height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .interactive-board-wrapper {
            position: relative;
            width: 100%;
        }

        .menu-img {
            width: 100%;
            height: auto;
            border-radius: 6px;
            display: block;
        }

        /* Static Hotspot Bubbles */
        .hotspot-circle {
            position: absolute;
            border: 2px solid #ffc107;
            border-radius: 50%;
            background: rgba(255, 193, 7, 0.25);
            pointer-events: none;
        }

        /* Left & Right Control Arrows */
        .arrow-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: #ffffff;
            border: 2px solid #ffc107;
            color: #121212;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: bold;
            z-index: 20;
            transition: all 0.2s ease;
        }

        .arrow-btn:hover {
            background-color: #ffc107;
            color: #121212;
        }

        .arrow-left {
            left: 10px;
        }

        .arrow-right {
            right: 10px;
        }

        /* Right Split Box */
        .right-info-box {
            flex: 1;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background-color: #ffffff;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Bottom Full Width Line */
        .bottom-full-line {
            border: 1px solid #ffc107;
            background-color: #ffffff;
            height: 36px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555555;
            font-size: 0.85rem;
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .top-banner-spot {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .banner-text-content {
                padding: 0;
            }
            .banner-badges {
                justify-content: center;
            }
            .split-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <!-- Large Outer Wireframe Frame -->
    <div class="page-outer-frame">

        <!-- 1. TOP FEATURED BANNER SPOT -->
        <section class="top-banner-spot">
            <div class="banner-logo-container">
                <img src="Logo Website Draft✨    Ashiee - Reyn (1).png" alt="Sairene TephTeph Logo">
            </div>

            <div class="banner-text-content">
                <h1>Sairene TephTeph Tapsilogan</h1>
                <p class="text-dark small mb-2 fw-semibold">Your Favorite Neighborhood Tapsilogan & Tambayan • Fresh Silog Meals Everyday</p>
                
                <div class="banner-badges">
                    <span class="banner-badge"><i class="fa-solid fa-clock me-1"></i> Open 24/7</span>
                    <span class="banner-badge"><i class="fa-solid fa-location-dot me-1"></i> MRB Condominium C5, Taguig</span>
                    <span class="banner-badge"><i class="fa-solid fa-wallet me-1"></i> GCash Ready</span>
                </div>
            </div>

            <div>
                <a href="order.php" class="btn btn-warning fw-bold px-4 py-2 text-dark shadow-sm rounded-pill text-nowrap">
                    <i class="fa-solid fa-cart-shopping me-1"></i> Order Now
                </a>
            </div>
        </section>

        <!-- 2. Two Thin Sub-Header Lines -->
        <div class="sub-header-line-1">
            <span class="text-dark small fw-bold"><i class="fa-solid fa-star text-warning me-1"></i> A Taste of Comfort • Hot & Fresh Silog Meals <i class="fa-solid fa-star text-warning ms-1"></i></span>
        </div>

        <div class="sub-header-line-2">
            <nav class="d-flex gap-3">
                <a href="Home_Page.php" class="nav-link-custom text-warning">HOME</a>
                <a href="menu.php" class="nav-link-custom">MENU</a>
                <a href="order.php" class="nav-link-custom">ORDER NOW</a>
                <a href="tambay_caps.php" target="_blank" class="nav-link-custom">TAMBAY CAPS</a>
            </nav>
        </div>

        <!-- 3. Split Container (Left Interactive Box + Right Info Box) -->
        <div class="split-container">

            <!-- LEFT BOX: Menu Board with Static Circles & Side Arrows -->
            <div class="left-menu-box">
                
                <!-- Left Arrow Control -->
                <a href="Home_Page.php?menu_page=1" class="arrow-btn arrow-left" title="Previous Menu Page">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>

                <div class="interactive-board-wrapper">
                    <?php if ($menu_page == 1): ?>
                        <img src="menu 1.png" alt="Silog Meals Menu" class="menu-img">

                        <!-- Static Circle Hotspots -->
                        <div class="hotspot-circle" style="top: 38%; left: 10%; width: 50px; height: 50px;"></div>
                        <div class="hotspot-circle" style="top: 58%; left: 10%; width: 50px; height: 50px;"></div>
                        <div class="hotspot-circle" style="top: 38%; left: 40%; width: 50px; height: 50px;"></div>
                        <div class="hotspot-circle" style="top: 58%; left: 40%; width: 50px; height: 50px;"></div>

                    <?php else: ?>
                        <img src="menu 2.png" alt="Combos and Ala Carte" class="menu-img">

                        <div class="hotspot-circle" style="top: 15%; left: 10%; width: 50px; height: 50px;"></div>
                        <div class="hotspot-circle" style="top: 15%; left: 40%; width: 50px; height: 50px;"></div>
                    <?php endif; ?>
                </div>

                <!-- Right Arrow Control -->
                <a href="Home_Page.php?menu_page=2" class="arrow-btn arrow-right" title="Next Menu Page">
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>

            <!-- RIGHT BOX: History & Quick Action Info -->
            <div class="right-info-box">
                <div>
                    <h5 class="text-dark fw-bold mb-3 border-bottom border-warning pb-2">
                        <i class="fa-solid fa-utensils text-warning me-2"></i>About Our Spot
                    </h5>
                    <p class="small text-dark leading-relaxed mb-3">
                        At <strong>Sairene TephTeph Tapsilogan</strong>, we built our spot to feed hungry shift workers, late-night crews, and friends craving authentic Pinoy silog comfort food.
                    </p>
                    <p class="small text-muted mb-0">
                        Open 24/7 at MRB Condominium C5, Taguig City.
                    </p>
                </div>

                <div class="mt-4 pt-3 border-top border-warning">
                    <a href="order.php" class="btn btn-warning w-100 fw-bold py-2 text-dark mb-2">
                        <i class="fa-solid fa-motorcycle me-2"></i>Order Online Now
                    </a>
                    <a href="menu.php" class="btn btn-outline-dark w-100 btn-sm fw-bold">
                        Full Menu View
                    </a>
                </div>
            </div>

        </div>

        <!-- 4. Bottom Full-Width Divider Line -->
        <div class="bottom-full-line">
            <span>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan • All Rights Reserved</span>
        </div>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>
</body>
</html>