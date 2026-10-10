<?php 
// Page Selection
$page = isset($_GET['page']) && $_GET['page'] == 2 ? 2 : 1; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene Tephteph Tapsilogan - Digital Menu</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS Part -->
    <style>
        body {
            background-color: #121212;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .text-gold {
            color: #ffc107;
        }

        .btn-gold {
            background-color: #ffc107;
            color: #000;
            border: none;
            font-weight: bold;
            border-radius: 50px;
            padding: 10px 18px;
            transition: all 0.2s ease-in-out;
        }

        .btn-gold:hover {
            background-color: #e0a800;
            color: #000;
            transform: scale(1.05);
        }

        .btn-gold.active {
            background-color: #ffffff;
            color: #000;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
        }

        /* Navigation Bar Styles */
        .main-navbar {
            background-color: rgba(20, 20, 20, 0.95);
            border: 1px solid #ffc107;
            border-radius: 50px;
            padding: 8px 16px;
            max-width: 900px;
            margin: 0 auto 25px auto;
        }

        .main-navbar .nav-link {
            color: #ffffff;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 25px;
            transition: all 0.2s ease-in-out;
        }

        .main-navbar .nav-link:hover {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.15);
        }

        .main-navbar .nav-link.active {
            color: #000000;
            background-color: #ffc107;
        }

        /* Menu Container */
        .menu-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .menu-board-container {
            position: relative;
            width: 100%;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
        }

        .menu-board-img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Invisible Hotspots */
        .hotspot {
            position: absolute;
            background: transparent;
            border: none;
            outline: none;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* Hover Glow Effect */
        .hotspot:hover, .hotspot:focus {
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.6);
            transform: scale(1.03);
        }

        /* Popup Cards */
        .popover-detail-card {
            display: none;
            position: absolute;
            bottom: 105%;
            left: 50%;
            transform: translateX(-50%);
            width: 220px;
            background-color: rgba(20, 20, 20, 0.95);
            color: #ffffff;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #ffc107;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.7);
            z-index: 20;
            pointer-events: none;
            text-align: center;
        }

        .popover-detail-card h5 {
            font-size: 0.95rem;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 6px;
        }

        .popover-detail-card p {
            font-size: 0.8rem;
            margin-bottom: 0;
            color: #dddddd;
        }

        /* Popup Trigger on Hover or Clcik */
        .hotspot:hover .popover-detail-card,
        .hotspot:focus .popover-detail-card {
            display: block;
        }
    </style>
</head>
<body class="bg-dark text-light">

    <div class="container py-4">
        <!-- Navigation Bar -->
        <nav class="main-navbar">
            <ul class="nav justify-content-center gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="home_page.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="menu.php">Menu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="milktea.php">Milk Tea</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tambay_caps.php">Tambay Caps</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="food_packs.php">Food Packs</a>
                </li>
            </ul>
        </nav>

        <!-- Header -->
        <header class="text-center mb-4">
            <h1 class="display-5 fw-bold text-gold">SAIRENE TEPHTEPH TAPSILOGAN</h1>
            <p class="lead text-muted">A Taste of Comfort • Interactive Menu</p>
        </header>

        <!-- Arrow Buttons -->
        <div class="d-flex justify-content-center align-items-center gap-3 mb-4">
            <a href="menu.php?page=1" class="btn btn-gold <?php echo $page == 1 ? 'active' : ''; ?>" aria-label="Previous Page">
                <i class="fa-solid fa-chevron-left fs-5"></i>
            </a>
            
            <a href="menu.php?page=2" class="btn btn-gold <?php echo $page == 2 ? 'active' : ''; ?>" aria-label="Next Page">
                <i class="fa-solid fa-chevron-right fs-5"></i>
            </a>
        </div>

        <!-- Menu Container -->
        <div class="menu-wrapper">
            <?php if ($page == 1): ?>
                <!-- PAGE 1: SILOG MEALS -->
                <div id="menu-page-1" class="menu-board-container">
                    <img src="images/menu_page_1.png" alt="Sairene Tapsilogan Silog Meals" class="img-fluid menu-board-img">

                    <!-- Row 1 -->
                    <div class="hotspot" style="top: 40.85%; left: 9.03%; width: 16.97%; height: 11.99%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Tapsilog (₱90)</h5>
                            <p>Marinated Beef Tapa, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 40.85%; left: 39.81%; width: 16.76%; height: 11.97%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Malingsilog (₱90)</h5>
                            <p>Fried Luncheon Meat, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 40.80%; left: 70.27%; width: 17.08%; height: 12.08%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Liemposilog (₱90)</h5>
                            <p>Grilled Pork Belly, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="hotspot" style="top: 60.00%; left: 9.19%; width: 16.70%; height: 11.84%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Bagnetsilog (₱90)</h5>
                            <p>Crispy Pork Bagnet, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 59.94%; left: 39.48%; width: 16.92%; height: 12.04%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Tocilog (₱90)</h5>
                            <p>Sweet Pork Tocino, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 59.89%; left: 72.10%; width: 16.76%; height: 12.03%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Hungariansilog (₱90)</h5>
                            <p>Hungarian Sausage, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div class="hotspot" style="top: 75.44%; left: 24.74%; width: 16.76%; height: 12%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Hotdogsilog (₱80)</h5>
                            <p>Juicy Hotdog, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 76.01%; left: 56.57%; width: 16.76%; height: 12%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Sisigsilog (₱90)</h5>
                            <p>Savory Pork Sisig, Garlic Rice, Fried Egg.</p>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <!-- PAGE 2: COMBO MEALS & ALA CARTE -->
                <div id="menu-page-2" class="menu-board-container">
                    <img src="images/menu_page_2.png" alt="Sairene Tapsilogan Combos and Ala Carte" class="img-fluid menu-board-img">

                    <!-- Combo Meals Row 1 -->
                    <div class="hotspot" style="top: 14.25%; left: 7.41%; width: 19.02%; height: 13.45%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Bagnet + Hungarian (₱140)</h5>
                            <p>Crispy Bagnet, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 13.79%; left: 36.66%; width: 19.34%; height: 13.68%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Tapa + Hungarian (₱140)</h5>
                            <p>Beef Tapa, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 13.39%; left: 66.80%; width: 19.18%; height: 13.56%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Tapa + Bagnet Silog (₱140)</h5>
                            <p>Beef Tapa, Crispy Bagnet, Rice, Egg.</p>
                        </div>
                    </div>

                    <!-- Combo Meals Row 2 -->
                    <div class="hotspot" style="top: 32.02%; left: 19.82%; width: 20.79%; height: 14.70%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Liempo + Hungarian (₱140)</h5>
                            <p>Grilled Liempo, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 32.99%; left: 53.99%; width: 20.31%; height: 14.36%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Sisig + Hungarian (₱140)</h5>
                            <p>Pork Sisig, Hungarian Sausage, Rice, Egg.</p>
                        </div>
                    </div>

                    <!-- Ala Carte Row 1 -->
                    <div class="hotspot" style="top: 56.30%; left: 7.74%; width: 17.41%; height: 12.31%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Special Tapa (₱130)</h5>
                            <p>Ala Carte Portion Marinated Beef Tapa.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 56.58%; left: 34.73%; width: 17.57%; height: 12.42%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Special Bagnet (₱130)</h5>
                            <p>Ala Carte Serving Crispy Pork Bagnet.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 57.21%; left: 67.85%; width: 17.41%; height: 12.31%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Special Sisig (₱130)</h5>
                            <p>Ala Carte Serving Savory Pork Sisig.</p>
                        </div>
                    </div>

                    <!-- Ala Carte Row 2 -->
                    <div class="hotspot" style="top: 75.33%; left: 8.14%; width: 16.92%; height: 11.97%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Hungarian Sausage (₱130)</h5>
                            <p>Single Hungarian Sausage Portion.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 75.84%; left: 35.29%; width: 16.60%; height: 11.74%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Plain Rice (₱15)</h5>
                            <p>Steamed White Rice.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 75.38%; left: 58.66%; width: 16.76%; height: 11.85%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Garlic Rice (₱20)</h5>
                            <p>Garlic Fried Rice.</p>
                        </div>
                    </div>

                    <div class="hotspot" style="top: 75.21%; left: 78.97%; width: 16.92%; height: 11.97%;" tabindex="0">
                        <div class="popover-detail-card">
                            <h5>Extra Egg (₱15)</h5>
                            <p>Fried Egg (Sunny Side Up / Well Done / Scrambled).</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="text-center mt-4 text-muted small">
            <p class="mb-1">MRB Condominium C5, Taguig City</p>
            <p class="mb-0">GCash Payments Accepted: 09270466231 (Sarippudin Aming)</p>
        </footer>
    </div>

</body>
</html>