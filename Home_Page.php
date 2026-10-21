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

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
                exit;
            }

            // Hash password and insert user into database
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            // Auto-login upon successful sign up
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

// Check logged in session status
$is_logged_in = isset($_SESSION['user_id']);
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
            /* Full landscape background banner */
            background-image: linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35)), url('images/Best Seller (Landscape) (1) (1).png');
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
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

        /* Large Outer Border Box with Frosted Glass Effect */
        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.22); /* Highly transparent glass */
            backdrop-filter: blur(8px); /* Frosted glass blur effect */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            max-width: 1200px;
            margin: 40px auto 0 auto;
        }

        /* TOP FEATURED BANNER SPOT (Transparent Glass) */
        .top-banner-spot {
            position: relative;
            border: 2px solid #ffc107;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 253, 240, 0.3) 100%);
            backdrop-filter: blur(4px);
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
            color: #ffd700; /* Brighter gold for better contrast on background */
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
        }

        .banner-text-content p {
            color: #ffffff !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
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

        /* Double Sub-header Lines (Transparent Glass) */
        .sub-header-line-1, .sub-header-line-2 {
            border: 1px solid #ffc107;
            background-color: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(4px);
            height: 36px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sub-header-line-1 span {
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
        }

        .sub-header-line-1 {
            margin-bottom: 8px;
        }

        .sub-header-line-2 {
            margin-bottom: 20px;
        }

        /* Interactive Navigation Links with Hover Banners */
        .nav-link-custom {
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
            text-decoration: none;
            font-weight: bold;
            font-size: 0.85rem;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1px solid transparent;
            transition: all 0.25s ease;
        }

        .nav-link-custom.active {
            color: #ffc107;
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

        /* 3. Order Now Button Hover Preview */
        #orderNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/.png');
            background-size: cover;
            background-position: center;
            color: #ffc107 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            border: 1px solid #ffc107;
        }

        /* 4. Food Packs Button Hover Preview */
        #foodPacksNavLink:hover {
            background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('images/.png');
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

        /* Split Section Containers */
        .split-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-start;
        }

        /* Left Menu Box (Flush Portrait Banner) */
        .left-menu-box {
            flex: 2;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.2);
            position: relative;
            padding: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .portrait-banner-img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 6px;
        }

        /* Right Split Box (Transparent Glass) */
        .right-info-box {
            flex: 1;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(6px);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .right-info-box h5 {
            color: #ffc107 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
        }

        .right-info-box p {
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
        }

        /* Bottom Full Width Line (Transparent Glass) */
        .bottom-full-line {
            border: 1px solid #ffc107;
            background-color: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(4px);
            height: 36px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
            font-size: 0.85rem;
            font-weight: 600;
        }

        @media (max-width: 992px) {
            .top-banner-spot {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .banner-text-content { padding: 0; }
            .banner-badges { justify-content: center; }
            .split-container { flex-direction: column; }
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
                <p class="small mb-2 fw-semibold">Your Favorite Neighborhood Tapsilogan & Tambayan • Fresh Silog Meals Everyday</p>
                
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
            <span class="small fw-bold"><i class="fa-solid fa-star text-warning me-1"></i> A Taste of Comfort • Hot & Fresh Silog Meals <i class="fa-solid fa-star text-warning ms-1"></i></span>
        </div>

        <div class="sub-header-line-2">
            <nav class="d-flex gap-3 align-items-center">
                <a href="Home_Page.php" id="homeNavLink" class="nav-link-custom active">HOME</a>
                <a href="menu.php" id="menuNavLink" class="nav-link-custom">MENU</a>
                <a href="order.php" id="orderNavLink" class="nav-link-custom">ORDER NOW</a>
                <a href="food_packs.php" id="foodPacksNavLink" class="nav-link-custom">FOOD PACKS</a>
                <a href="tambay_caps.php" id="tambayNavLink" class="nav-link-custom">TAMBAY CAPS</a>
            </nav>
        </div>

        <!-- 3. Split Container (Left Portrait Banner Holder + Right Info Box) -->
        <div class="split-container">

            <!-- LEFT BOX: Portrait Banner Display -->
            <div class="left-menu-box">
                <img src="images/bannerko.png" alt="Sairene Tapsilogan Portrait Banner" class="portrait-banner-img">
            </div>

            <!-- RIGHT BOX: History & Quick Action Info -->
            <div class="right-info-box">
                <div>
                    <h5 class="fw-bold mb-3 border-bottom border-warning pb-2">
                        <i class="fa-solid fa-utensils text-warning me-2"></i>About Our Spot
                    </h5>
                    <p class="small leading-relaxed mb-3">
                        At <strong>Sairene TephTeph Tapsilogan</strong>, we built our spot to feed hungry shift workers, late-night crews, and friends craving authentic Pinoy silog comfort food.
                    </p>
                    <p class="small text-white-50 mb-0">
                        Open 24/7 at MRB Condominium C5, Taguig City.
                    </p>
                </div>

                <div class="mt-4 pt-3 border-top border-warning">
                    <a href="order.php" class="btn btn-warning w-100 fw-bold py-2 text-dark mb-2">
                        <i class="fa-solid fa-motorcycle me-2"></i>Order Online Now
                    </a>
                    <a href="menu.php" class="btn btn-outline-light w-100 btn-sm fw-bold">
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

    <!-- BOOTSTRAP 5 JS BUNDLE -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- AUTHENTICATION JAVASCRIPT HANDLERS -->
    <script>
        function openAuthModal(type) {
            toggleAuthForm(type);
            var modalEl = document.getElementById('authModal');
            if (typeof bootstrap !== 'undefined') {
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            } else {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
            }
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

            fetch('Home_Page.php', {
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
                fetch('Home_Page.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                });
            }
        }
    </script>
</body>
</html>