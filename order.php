<?php
require_once 'config/db.php';

// Optional: Fetch delivery zones from 'delivery_zones' table if available
$delivery_zones = [];
try {
    $stmt = $pdo->query("SELECT * FROM delivery_zones");
    $delivery_zones = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback handled gracefully in HTML dropdown
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Now - Sairene TephTeph Tapsilogan</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

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
            margin-bottom: -25px;
            z-index: 10;
        }

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
            border-radius: 6px;
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

        /* Main Outer Box */
        .main-wireframe-box {
            border: 2px solid #ffc107;
            border-radius: 12px;
            background-color: #1a1a1a;
            padding: 40px 30px 30px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
            min-height: 550px;
        }

        .text-gold {
            color: #ffc107 !important;
        }

        .map-responsive {
            overflow: hidden;
            padding-bottom: 60%;
            position: relative;
            height: 0;
            border-radius: 12px;
            border: 2px solid #ffc107;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.6);
        }

        .map-responsive iframe {
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            position: absolute;
        }

        .location-card {
            background-color: #121212;
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 12px;
            padding: 20px;
        }

        .form-select-gold {
            background-color: #1a1a1a;
            color: #ffc107;
            border: 2px solid #ffc107;
            font-weight: bold;
            border-radius: 8px;
        }

        .form-select-gold:focus {
            background-color: #1a1a1a;
            color: #ffc107;
            border-color: #ffc107;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
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

    <!-- TOP HEADER -->
    <div class="top-nav-bar">
        <div class="logo-circle">
            <a href="home_page.php">
                <img src="images/Logo Website Draft✨    Ashiee - Reyn (1).png" alt="STT Logo">
            </a>
        </div>

        <div class="nav-buttons-group">
            <a href="home_page.php" class="btn-rect">Home</a>
            <a href="menu.php" class="btn-rect">Menu</a>
            <a href="order.php" class="btn-rect active">Order Now</a>
            <a href="tambay_caps.php" target="_blank" class="btn-rect">Tambay Caps</a>
        </div>
    </div>

    <!-- MAIN CONTAINER BOX -->
    <main class="main-wireframe-box">
        
        <section class="text-center my-3">
            <h1 class="display-6 fw-bold text-gold text-uppercase">Delivery Location & Store Map</h1>
            <p class="text-muted">Select your delivery zone to check coverage and store distance.</p>
        </section>

        <hr class="border-warning opacity-25 my-4">

        <div class="row g-4">
            <!-- Left Side: Interactive Map -->
            <div class="col-lg-7">
                <div class="map-responsive">
                    <iframe 
                        id="locationMap"
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.270724816027!2d121.0560!3d14.5290!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8c3683f80ad%3A0x89e2c69460db8b00!2sMRB%20Condominium%2C%20Taguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>

            <!-- Right Side: Location Selector & Delivery Details -->
            <div class="col-lg-5">
                <div class="location-card">
                    <h5 class="text-gold fw-bold mb-3"><i class="fa-solid fa-map-pin me-2"></i>Choose Delivery Area</h5>
                    
                    <div class="mb-4">
                        <label for="locationSelect" class="form-label text-light small fw-bold">Select Location / Branch:</label>
                        <select id="locationSelect" class="form-select form-select-gold py-2" onchange="updateMapLocation(this.value)">
                            <option value="mrb_c5" selected>MRB Condominium C5, Taguig (Main Store / Pickup)</option>
                            <option value="taguig_proper">Taguig City Center / Lower Bicutan</option>
                            <option value="bgc_mckinley">BGC / Bonifacio Global City / McKinley</option>
                            <option value="pateros_pasig">Pateros / San Joaquin, Pasig Area</option>
                        </select>
                    </div>

                    <!-- Dynamic Zone Info Container -->
                    <div class="p-3 rounded bg-dark border border-secondary border-opacity-25 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Estimated Delivery Fee:</span>
                            <span id="feeBadge" class="badge bg-warning text-dark fw-bold fs-6">₱30 - ₱50</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Est. Delivery Time:</span>
                            <span id="timeBadge" class="text-gold fw-bold small">20 - 35 mins</span>
                        </div>
                    </div>

                    <!-- Store Info & GCash Payment -->
                    <div class="border-top border-secondary border-opacity-25 pt-3 mb-4">
                        <h6 class="text-gold fw-bold mb-2"><i class="fa-solid fa-clock me-2"></i>24/7 Operations</h6>
                        <p class="small text-white mb-2">Open all day & late nights for shift workers.</p>

                        <h6 class="text-gold fw-bold mb-2"><i class="fa-solid fa-wallet me-2"></i>GCash Details</h6>
                        <p class="small text-light mb-0"><strong>Number:</strong> 09270466231</p>
                        <p class="small text-white"><strong>Account Name:</strong> Sarippudin Aming</p>
                    </div>

                    <!-- Proceed Button -->
                    <a href="Menu.php" class="btn btn-warning w-100 fw-bold py-2 text-dark rounded-pill">
                        <i class="fa-solid fa-utensils me-2"></i>Browse Menu & Order Food
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan. All Rights Reserved.</p>
    </footer>

    <!-- JavaScript to Swap Map & Fees dynamically based on user selection -->
    <script>
        function updateMapLocation(locationKey) {
            const mapIframe = document.getElementById('locationMap');
            const feeBadge = document.getElementById('feeBadge');
            const timeBadge = document.getElementById('timeBadge');

            if (locationKey === 'mrb_c5') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.270724816027!2d121.0560!3d14.5290!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8c3683f80ad%3A0x89e2c69460db8b00!2sMRB%20Condominium%2C%20Taguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱30 - ₱50";
                timeBadge.innerText = "20 - 35 mins";
            } else if (locationKey === 'taguig_proper') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d61803.74312015822!2d121.0185121406606!3d14.51268689531525!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c88e332a688b%3A0xc345da58409ee42d!2sTaguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱50 - ₱70";
                timeBadge.innerText = "30 - 45 mins";
            } else if (locationKey === 'bgc_mckinley') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15446.73507519967!2d121.04278479782522!3d14.545802280208166!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8ed284c16a7%3A0xc6cb19283e3eb6f0!2sBonifacio%20Global%20City%2C%20Taguig%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱60 - ₱80";
                timeBadge.innerText = "25 - 40 mins";
            } else if (locationKey === 'pateros_pasig') {
                mapIframe.src = "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15446.21639097728!2d121.06014454782635!3d14.548726530188092!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397c8b0907e1cb5%3A0x7d6a5e1eb29ee0ff!2sPateros%2C%20Metro%20Manila!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph";
                feeBadge.innerText = "₱70 - ₱90";
                timeBadge.innerText = "35 - 50 mins";
            }
        }
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/bootstrap.bundle.min.js"></script>
</body>
</html>