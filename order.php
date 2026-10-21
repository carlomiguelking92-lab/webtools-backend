<?php
session_start();
require_once 'config/db.php';

// --- AJAX / POST HANDLERS FOR AUTHENTICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. Sign Up Handler
    if ($_POST['action'] === 'signup') {
        try {
            global $pdo;
            $fullname = trim($_POST['fullname'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($fullname) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
                exit;
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            $_SESSION['user_id']  = $pdo->lastInsertId();
            $_SESSION['fullname'] = $fullname;

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 2. Login Handler
    if ($_POST['action'] === 'login') {
        try {
            global $pdo;
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Please fill in both email and password.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // 3. Logout Handler
    if ($_POST['action'] === 'logout') {
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
}

$is_logged_in = isset($_SESSION['user_id']);

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
            background-color: #f8f9fa;
            color: #121212;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }

        /* TOP-LEFT FIXED LOGIN BUTTON */
        .top-left-login-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1050;
            background-color: #212529;
            color: #ffc107;
            border: 2px solid #212529;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: bold;
            font-size: 0.88rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        .top-left-login-btn:hover {
            background-color: #ffc107;
            color: #212529;
            border-color: #212529;
            transform: translateY(-2px);
        }

        /* Large Outer Border Box */
        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 1200px;
            margin: 40px auto 0 auto;
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
            height: auto;
            padding: 6px 0;
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
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .nav-link-custom.active, .nav-link-custom.text-warning {
            color: #d4a000 !important;
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

        /* Map and Location Card Styles */
        .map-responsive {
            overflow: hidden;
            padding-bottom: 70%;
            position: relative;
            height: 0;
            border-radius: 12px;
            border: 2px solid #ffc107;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .map-responsive iframe {
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            position: absolute;
        }

        .location-card {
            background-color: #ffffff;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.04);
        }

        .form-select-gold {
            background-color: #ffffff;
            color: #121212;
            border: 2px solid #ffc107;
            font-weight: bold;
            border-radius: 8px;
        }

        .form-select-gold:focus {
            background-color: #ffffff;
            color: #121212;
            border-color: #d4a000;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.4);
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
            margin-top: 25px;
        }

        @media (max-width: 992px) {
            .top-banner-spot {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .banner-text-content { padding: 0; }
            .banner-badges { justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- TOP-LEFT CORNER LOGIN / LOGOUT BUTTON -->
    <?php if ($is_logged_in): ?>
        <button type="button" class="top-left-login-btn" onclick="confirmLogout()">
            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout (<?= htmlspecialchars($_SESSION['fullname']) ?>)
        </button>
    <?php else: ?>
        <button type="button" class="top-left-login-btn" onclick="openAuthModal('login')">
            <i class="fa-solid fa-user me-2"></i>Login / Register
        </button>
    <?php endif; ?>

    <!-- Large Outer Wireframe Frame -->
    <div class="page-outer-frame">

        <!-- 1. TOP FEATURED BANNER SPOT -->
        <section class="top-banner-spot">
            <div class="banner-logo-container">
                <img src="images/Logo Website Draft✨    Ashiee - Reyn (1).png" alt="Sairene TephTeph Logo">
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
            <nav class="d-flex gap-3 flex-wrap justify-content-center align-items-center">
                <a href="Home_Page.php" id="homeNavLink" class="nav-link-custom">HOME</a>
                <a href="menu.php" id="menuNavLink" class="nav-link-custom">MENU</a>
                <a href="food_packs.php" id="foodPacksNavLink" class="nav-link-custom">FOOD PACKS</a>
                <a href="order.php" id="orderNavLink" class="nav-link-custom text-warning active">ORDER NOW</a>
                <a href="tambay_caps.php" id="tambayNavLink" class="nav-link-custom">TAMBAY CAPS</a>
            </nav>
        </div>

        <!-- 3. Main Content Container (Map + Delivery Area Selector) -->
        <div class="my-4">
            <section class="text-center mb-4">
                <h2 class="h4 fw-bold text-dark text-uppercase">Delivery Location & Store Map</h2>
                <p class="text-muted small">Select your delivery zone to check coverage and store distance.</p>
            </section>

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
                        <h5 class="text-dark fw-bold mb-3 border-bottom border-warning pb-2"><i class="fa-solid fa-map-pin text-warning me-2"></i>Choose Delivery Area</h5>
                        
                        <div class="mb-3">
                            <label for="locationSelect" class="form-label text-dark small fw-bold">Select Location / Branch:</label>
                            <select id="locationSelect" class="form-select form-select-gold py-2" onchange="updateMapLocation(this.value)">
                                <option value="mrb_c5" selected>MRB Condominium C5, Taguig (Main Store / Pickup)</option>
                                <option value="taguig_proper">Taguig City Center / Lower Bicutan</option>
                                <option value="bgc_mckinley">BGC / Bonifacio Global City / McKinley</option>
                                <option value="pateros_pasig">Pateros / San Joaquin, Pasig Area</option>
                            </select>
                        </div>

                        <!-- Dynamic Zone Info Container -->
                        <div class="p-3 rounded bg-light border border-warning mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-secondary small">Estimated Delivery Fee:</span>
                                <span id="feeBadge" class="badge bg-warning text-dark fw-bold fs-6">₱30 - ₱50</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-secondary small">Est. Delivery Time:</span>
                                <span id="timeBadge" class="text-dark fw-bold small">20 - 35 mins</span>
                            </div>
                        </div>

                        <!-- Store Info & GCash Payment -->
                        <div class="border-top border-warning pt-3 mb-3">
                            <h6 class="text-dark fw-bold mb-2 small"><i class="fa-solid fa-clock text-warning me-2"></i>24/7 Operations</h6>
                            <p class="small text-muted mb-2">Open all day & late nights for shift workers.</p>

                            <h6 class="text-dark fw-bold mb-2 small"><i class="fa-solid fa-wallet text-warning me-2"></i>GCash Details</h6>
                            <p class="small text-muted mb-1"><strong>Number:</strong> 09270466231</p>
                            <p class="small text-muted mb-0"><strong>Account Name:</strong> Sarippudin Aming</p>
                        </div>

                        <!-- Proceed Button -->
                        <a href="menu.php" class="btn btn-warning w-100 fw-bold py-2 text-dark rounded-pill shadow-sm">
                            <i class="fa-solid fa-utensils me-2"></i>Browse Menu & Order Food
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Bottom Full-Width Divider Line -->
        <div class="bottom-full-line">
            <span>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan &bull; All Rights Reserved</span>
        </div>

    </div>

    <!-- LOGIN & SIGNUP POPUP MODAL -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-3 border-warning bg-dark text-white">
                <div class="modal-header border-warning">
                    <h5 class="modal-title fw-bold text-warning" id="authModalTitle">Customer Login</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Login Form -->
                    <form id="loginForm" onsubmit="handleAuthSubmit(event, 'login')">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Email Address</label>
                            <input type="email" id="loginEmail" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Password</label>
                            <input type="password" id="loginPassword" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark mb-3">Login</button>
                        <p class="small text-center mb-0">Don't have an account? <button type="button" onclick="toggleAuthForm('signup')" class="btn btn-link text-warning fw-bold p-0 text-decoration-none">Sign Up</button></p>
                    </form>

                    <!-- Signup Form -->
                    <form id="signupForm" class="d-none" onsubmit="handleAuthSubmit(event, 'signup')">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Full Name</label>
                            <input type="text" id="signupName" class="form-control" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Email Address</label>
                            <input type="email" id="signupEmail" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-white">Password</label>
                            <input type="password" id="signupPassword" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark mb-3">Create Account</button>
                        <p class="small text-center mb-0">Already have an account? <button type="button" onclick="toggleAuthForm('login')" class="btn btn-link text-warning fw-bold p-0 text-decoration-none">Login</button></p>
                    </form>
                </div>
            </div>
        </div>
    </div>

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

        function openAuthModal(type) {
            toggleAuthForm(type);
            var modalEl = document.getElementById('authModal');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }

        function toggleAuthForm(type) {
            let loginForm = document.getElementById('loginForm');
            let signupForm = document.getElementById('signupForm');
            let modalTitle = document.getElementById('authModalTitle');

            if (type === 'signup') {
                loginForm.classList.add('d-none');
                signupForm.classList.remove('d-none');
                modalTitle.innerText = 'Create Account';
            } else {
                signupForm.classList.add('d-none');
                loginForm.classList.remove('d-none');
                modalTitle.innerText = 'Customer Login';
            }
        }

        function handleAuthSubmit(e, action) {
            e.preventDefault();
            let formData = new FormData();
            formData.append('action', action);

            if (action === 'login') {
                formData.append('email', document.getElementById('loginEmail').value);
                formData.append('password', document.getElementById('loginPassword').value);
            } else if (action === 'signup') {
                formData.append('fullname', document.getElementById('signupName').value);
                formData.append('email', document.getElementById('signupEmail').value);
                formData.append('password', document.getElementById('signupPassword').value);
            }

            fetch('order.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'An error occurred during authentication.');
                }
            })
            .catch(err => console.error(err));
        }

        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                let formData = new FormData();
                formData.append('action', 'logout');
                fetch('order.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                });
            }
        }
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>