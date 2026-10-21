<?php
require_once 'config/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Food Packs - Sairene TephTeph Tapsilogan</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
            color: #121212;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Top Navigation Line */
        .sub-header-line {
            border: 1px solid #ffc107;
            background-color: #ffffff;
            height: auto;
            padding: 6px 0;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link-custom {
            color: #121212;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.85rem;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .nav-link-custom.active {
            color: #d4a000;
            border-color: #ffc107;
        }

        /* 1. Home Button Hover Preview */
        #homeNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/Best Seller (Landscape) (1) (1).png');
            background-size: cover;
            background-position: center;
            color: #ffc107 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            border: 1px solid #ffc107;
        }

        /* 2. Menu Button Hover Preview */
        #menuNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/menu_page_1.png');
            background-size: cover;
            background-position: center;
            color: #ffc107 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            border: 1px solid #ffc107;
        }

        /* 3. Food Packs Button Hover Preview */
        #foodPacksNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/bannerko.png');
            background-size: cover;
            background-position: center;
            color: #ffc107 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            border: 1px solid #ffc107;
        }

        /* 4. Order Now Button Hover Preview */
        #orderNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/Best Seller (Landscape) (1) (1).png');
            background-size: cover;
            background-position: center;
            color: #ffc107 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            border: 1px solid #ffc107;
        }

        /* 5. Tambay Caps Button Hover Preview */
        #tambayNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/tambay5.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: #ffc107 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            border: 1px solid #ffc107;
        }

        /* 3-DIVISION CONTAINER GRAPHIC */
        .bento-container {
            border: 4px solid #333333;
            border-radius: 20px;
            background-color: rgba(240, 240, 240, 0.8);
            padding: 18px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
            position: relative;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 15px;
            height: 420px;
        }

        /* Compartment Styling */
        .compartment {
            border: 3px dashed #b0b0b0;
            border-radius: 16px;
            background-color: #ffffff;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        /* Large Compartment Spans 2 Rows */
        .compartment-carbs {
            grid-row: span 2;
            border-color: #ffc107;
            background-color: #fffdf0;
        }

        .compartment-main {
            border-color: #0d6efd;
            background-color: #f0f7ff;
        }

        .compartment-side {
            border-color: #198754;
            background-color: #f0fff4;
        }

        /* Hover Effect for Container Division */
        .compartment:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .compartment-carbs:hover { border-style: solid; border-color: #d4a000; }
        .compartment-main:hover { border-style: solid; border-color: #0a58ca; }
        .compartment-side:hover { border-style: solid; border-color: #146c43; }

        /* Compartment Labels & Badges */
        .compartment-badge {
            position: absolute;
            top: 10px;
            left: 12px;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 50px;
            text-transform: uppercase;
        }

        .badge-carbs { background-color: #ffc107; color: #000; }
        .badge-main { background-color: #0d6efd; color: #fff; }
        .badge-side { background-color: #198754; color: #fff; }

        .item-preview {
            font-weight: bold;
            font-size: 1.1rem;
            color: #121212;
            margin-top: 10px;
        }

        /* Hover Dropdown Overlay */
        .hover-dropdown-panel {
            position: absolute;
            inset: 0;
            background-color: rgba(255, 255, 255, 0.98);
            border-radius: 14px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease-in-out;
            z-index: 10;
        }

        /* Show Panel on Hover */
        .compartment:hover .hover-dropdown-panel,
        .compartment:focus-within .hover-dropdown-panel {
            opacity: 1;
            visibility: visible;
        }

        .form-select-custom {
            border: 2px solid #ffc107;
            font-weight: 600;
            border-radius: 8px;
        }

        /* Summary Sidebar */
        .summary-card {
            border: 2px solid #ffc107;
            border-radius: 12px;
            background-color: #ffffff;
            padding: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .bento-grid {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
                height: auto;
            }
            .compartment-carbs {
                grid-row: span 1;
                min-height: 200px;
            }
            .compartment {
                min-height: 180px;
            }
        }
    </style>
</head>
<body>

    <div class="page-outer-frame">

        <!-- Header Navigation Bar -->
        <div class="sub-header-line">
            <nav class="d-flex gap-3 align-items-center">
                <a href="Home_Page.php" id="homeNavLink" class="nav-link-custom">HOME</a>
                <a href="menu.php" id="menuNavLink" class="nav-link-custom">MENU</a>
                <a href="food_packs.php" id="foodPacksNavLink" class="nav-link-custom active">FOOD PACKS</a>
                <a href="order.php" id="orderNavLink" class="nav-link-custom">ORDER NOW</a>
                <a href="tambay_caps.php" id="tambayNavLink" class="nav-link-custom">TAMBAY CAPS</a>
            </nav>
        </div>

        <div class="text-center mb-4">
            <h2 class="fw-bold text-uppercase" style="color: #d4a000;">Build Your Custom 3-Division Food Pack</h2>
            <p class="text-muted small">Hover over any compartment of the container to choose your rice, main dish, and side dish!</p>
        </div>

        <div class="row g-4">
            <!-- LEFT: Interactive 3-Division Food Pack Container -->
            <div class="col-lg-7">
                <div class="bento-container">
                    <div class="bento-grid">

                        <!-- 1. BIG SIDE (CARBS: RICE / PASTA) -->
                        <div class="compartment compartment-carbs" tabindex="0">
                            <span class="compartment-badge badge-carbs">1. Carbs Division</span>
                            <i class="fa-solid fa-bowl-rice fs-1 text-warning mb-2"></i>
                            <div class="item-preview" id="previewCarbs">Garlic Rice</div>
                            <small class="text-muted mt-1"><i class="fa-solid fa-hand-pointer me-1"></i>Hover to edit</small>

                            <!-- Hover Dropdown Panel -->
                            <div class="hover-dropdown-panel">
                                <label class="fw-bold text-dark mb-1 small">Category:</label>
                                <select id="carbsCategory" class="form-select form-select-custom mb-2" onchange="updateCarbsOptions()">
                                    <option value="Rice" selected>Rice</option>
                                    <option value="Pasta">Pasta</option>
                                </select>

                                <label class="fw-bold text-dark mb-1 small">Select Carbs Item:</label>
                                <select id="carbsItem" class="form-select form-select-custom" onchange="updateSummary()">
                                    <!-- Populated dynamically via JS -->
                                </select>
                            </div>
                        </div>

                        <!-- 2. SMALL SIDE 1 (MAIN DISH) -->
                        <div class="compartment compartment-main" tabindex="0">
                            <span class="compartment-badge badge-main">2. Main Dish</span>
                            <i class="fa-solid fa-drumstick-bite fs-2 text-primary mb-2"></i>
                            <div class="item-preview" id="previewMain">Chicken Adobo</div>
                            <small class="text-muted mt-1"><i class="fa-solid fa-hand-pointer me-1"></i>Hover to edit</small>

                            <!-- Hover Dropdown Panel -->
                            <div class="hover-dropdown-panel">
                                <label class="fw-bold text-dark mb-1 small">Select Main Dish:</label>
                                <select id="mainDishItem" class="form-select form-select-custom" onchange="updateSummary()">
                                    <option value="Chicken Adobo" selected>Chicken Adobo</option>
                                    <option value="Pork Adobo">Pork Adobo</option>
                                    <option value="Chicken Curry">Chicken Curry</option>
                                    <option value="Crispy Bagnet Portion">Crispy Bagnet Portion</option>
                                    <option value="Beef Tapa Portion">Beef Tapa Portion</option>
                                    <option value="Pork Sisig Portion">Pork Sisig Portion</option>
                                </select>
                            </div>
                        </div>

                        <!-- 3. SMALL SIDE 2 (SIDE DISH - OPTIONAL) -->
                        <div class="compartment compartment-side" tabindex="0">
                            <span class="compartment-badge badge-side">3. Side Dish (Optional)</span>
                            <i class="fa-solid fa-egg fs-2 text-success mb-2"></i>
                            <div class="item-preview" id="previewSide">Sunny Side Egg</div>
                            <small class="text-muted mt-1"><i class="fa-solid fa-hand-pointer me-1"></i>Hover to edit</small>

                            <!-- Hover Dropdown Panel -->
                            <div class="hover-dropdown-panel">
                                <label class="fw-bold text-dark mb-1 small">Select Side Dish:</label>
                                <select id="sideDishItem" class="form-select form-select-custom" onchange="updateSummary()">
                                    <option value="None (No Side Dish)">-- None (Skip Side Dish) --</option>
                                    <option value="Sunny Side Egg" selected>Sunny Side Egg</option>
                                    <option value="Scrambled Egg">Scrambled Egg</option>
                                    <option value="Atchara (Pickled Papaya)">Atchara (Pickled Papaya)</option>
                                    <option value="Butter Corn & Peas">Butter Corn & Peas</option>
                                    <option value="Lumpiang Shanghai (2pcs)">Lumpiang Shanghai (2pcs)</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT: Custom Food Pack Order Summary -->
            <div class="col-lg-5">
                <div class="summary-card">
                    <div>
                        <h4 class="fw-bold text-dark mb-3 border-bottom border-warning pb-2">
                            <i class="fa-solid fa-box-open text-warning me-2"></i>Your Food Pack Summary
                        </h4>

                        <div class="bg-light p-3 rounded border mb-3">
                            <div class="mb-2">
                                <span class="text-muted small d-block">Carbs Portion:</span>
                                <strong id="summaryCarbs" class="text-dark">Rice - Garlic Rice</strong>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Main Dish Portion:</span>
                                <strong id="summaryMain" class="text-primary">Chicken Adobo</strong>
                            </div>
                            <div>
                                <span class="text-muted small d-block">Side Dish Portion:</span>
                                <strong id="summarySide" class="text-success">Sunny Side Egg</strong>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-3 bg-warning bg-opacity-10 rounded border border-warning">
                            <span class="fw-bold">Meal Pack Total:</span>
                            <span class="fs-4 fw-bold text-dark" id="summaryPrice">₱120.00</span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" onclick="submitFoodPack()" class="btn btn-warning w-100 fw-bold py-2 text-dark shadow-sm">
                            <i class="fa-solid fa-cart-plus me-1"></i> Proceed to Order
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted small">
            &copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan &bull; Custom Food Pack Container Builder[cite: 12]
        </div>

    </div>

    <script>
        const carbsOptions = {
            "Rice": ["Fried Rice", "Garlic Rice", "Java Rice", "Plain Rice"],
            "Pasta": ["Spaghetti", "Carbonara", "Pesto Pasta"]
        };

        function updateCarbsOptions() {
            const category = document.getElementById("carbsCategory").value;
            const itemSelect = document.getElementById("carbsItem");
            itemSelect.innerHTML = "";

            carbsOptions[category].forEach(item => {
                const opt = document.createElement("option");
                opt.value = item;
                opt.textContent = item;
                if (item === "Garlic Rice" || item === "Spaghetti") opt.selected = true;
                itemSelect.appendChild(opt);
            });

            updateSummary();
        }

        function updateSummary() {
            const carbsCat = document.getElementById("carbsCategory").value;
            const carbsItem = document.getElementById("carbsItem").value || "Garlic Rice";
            const mainDish = document.getElementById("mainDishItem").value;
            const sideDish = document.getElementById("sideDishItem").value;

            // Update previews inside container divisions
            document.getElementById("previewCarbs").innerText = carbsItem;
            document.getElementById("previewMain").innerText = mainDish;
            document.getElementById("previewSide").innerText = sideDish === "None (No Side Dish)" ? "No Side Dish" : sideDish;

            // Update Summary Card
            document.getElementById("summaryCarbs").innerText = `${carbsCat} - ${carbsItem}`;
            document.getElementById("summaryMain").innerText = mainDish;
            document.getElementById("summarySide").innerText = sideDish;

            // Calculate Base Price
            let price = 120.00;
            if (sideDish === "None (No Side Dish)") {
                price = 105.00; // Discount if optional side dish is skipped
            }
            document.getElementById("summaryPrice").innerText = "₱" + price.toFixed(2);
        }

        function submitFoodPack() {
            const carbs = document.getElementById("summaryCarbs").innerText;
            const main = document.getElementById("summaryMain").innerText;
            const side = document.getElementById("summarySide").innerText;
            const price = document.getElementById("summaryPrice").innerText;

            const packDetails = `Custom Food Pack (${carbs} + ${main} + ${side})`;
            window.location.href = `order.php?custom_pack=${encodeURIComponent(packDetails)}&price=${encodeURIComponent(price)}`;
        }

        // Initialize default options
        document.addEventListener("DOMContentLoaded", function() {
            updateCarbsOptions();
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>
</body>
</html>
