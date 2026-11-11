<?php
// STEP 1: PHP SESSION INITIALIZATION & DATABASE CONNECTION

// Step 1.1: Start or resume the PHP session to track user authentication status
session_start();

// Step 1.2: Include the database configuration file for MySQL PDO connection access
require_once 'config/db.php';

// STEP 2: AUTOMATIC ADMIN ACCOUNT SEEDING & CREDENTIAL RESET

try {
    // Step 2.1: Verify if the PDO database object is initialized
    if (isset($pdo)) {
        // Step 2.2: Define default system administrator credentials
        $adminEmail = 'admin@sairene.com';
        $adminPass  = 'admin123';
        
        // Step 2.3: Hash the default password using standard password hashing
        $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);

        // Step 2.4: Prepare and execute SQL query to check if the admin user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$adminEmail]);
        $existingAdmin = $stmt->fetch();

        if ($existingAdmin) {
            // Step 2.5: If admin exists, force update password and role to ensure admin123 always works
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
            $updateStmt->execute([$hashedPass, $adminEmail]);
        } else {
            // Step 2.6: If admin does not exist, insert a fresh administrator account
            $insertStmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'admin')");
            $insertStmt->execute(['System Administrator', $adminEmail, $hashedPass]);
        }
    }
} catch (PDOException $e) {
    // Step 2.7: Suppress database errors during auto-creation
}

// STEP 3: BACKEND AJAX AUTHENTICATION HANDLERS (POST REQUESTS)

// Step 3.1: Intercept incoming POST requests containing an 'action' parameter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Step 3.2: Set response content type header to JSON format
    header('Content-Type: application/json');

    // STEP 3.A: CUSTOMER SIGN UP HANDLER
    if ($_POST['action'] === 'signup') {
        try {
            global $pdo;
            
            // Step 3.A.1: Sanitize and clean input fields
            $fullname = trim($_POST['fullname'] ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            // Step 3.A.2: Validate non-empty fields
            if (empty($fullname) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            // Step 3.A.3: Validate email address format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
                exit;
            }

            // Step 3.A.4: Enforce minimum password length (at least 6 characters)
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
                exit;
            }

            // Step 3.A.5: Check if the email address is already in use
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address is already in use.']);
                exit;
            }

            // Step 3.A.6: Hash password and insert user into database with customer role
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            // Step 3.A.7: Auto-login customer upon successful sign up
            session_regenerate_id(true);
            $_SESSION['user_id']  = $pdo->lastInsertId();
            $_SESSION['fullname'] = $fullname;
            $_SESSION['role']     = 'customer';

            // Step 3.A.8: Output success response in JSON
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // STEP 3.B: CUSTOMER & ADMIN LOGIN HANDLER
    if ($_POST['action'] === 'login') {
        try {
            global $pdo;
            
            // Step 3.B.1: Clean input credentials
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            // Step 3.B.2: Validate required fields are filled
            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Please fill in both email and password.']);
                exit;
            }

            // Step 3.B.3: Fetch user record matching the email address
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Step 3.B.4: Verify input password against the hashed password stored in database
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role']     = $user['role'] ?? 'customer';
                echo json_encode(['success' => true, 'role' => $_SESSION['role']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email address or password.']);
            }
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // STEP 3.C: LOGOUT HANDLER
    if ($_POST['action'] === 'logout') {
        // Step 3.C.1: Reset active session array
        $_SESSION = [];

        // Step 3.C.2: Expire session cookie on browser client if active
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Step 3.C.3: Destroy session state on server
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
}

// STEP 4: FRONTEND SESSION CHECKS & AUTOMATIC ADMIN REDIRECT

// Step 4.1: Check logged in session status
$is_logged_in = isset($_SESSION['user_id']);

// Step 4.2: Check if current logged-in user holds admin privileges
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Step 4.3: Automatically redirect logged-in admin users directly to pos.php
if ($is_admin) {
    header("Location: pos.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Step 5: Document Metadata & Page Title Setup -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sairene TephTeph Tapsilogan - Home</title>

    <!-- Step 6: External CDN Stylesheets (Bootstrap 5 & FontAwesome Icons) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- STEP 7: EMBEDDED CSS STYLING RULES WITH STEP-BY-STEP COMMENTS -->
    <style>
        /* Step 7.1: Global Body base styles and dark background setup */
        body {
            background-color: #2b1d0c;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            position: relative;
            min-height: 100vh;
        }

        /* Step 7.2: Fixed fullscreen background pseudo-element with dark wooden texture overlay */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.35)), url('img/wood.jpg') no-repeat center center;
            background-size: cover;
            z-index: -1;
        }

        /* Step 7.3: Top-left fixed floating button for login and logout triggers */
        .top-left-login-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1050;
            background-color: #212529;
            color: #ffc107;
            border: 2px solid #ffc107;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: bold;
            font-size: 0.88rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        /* Step 7.4: Hover transition state for floating top-left button */
        .top-left-login-btn:hover {
            background-color: #ffc107;
            color: #212529;
            border-color: #212529;
            transform: translateY(-2px);
        }

        /* Step 7.5: Large outer glassmorphism frame container */
        .page-outer-frame {
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            max-width: 1200px;
            margin: 40px auto 0 auto;
        }

        /* Step 7.6: Top featured hero banner spot container */
        .top-banner-spot {
            position: relative;
            border: 2px solid #ffc107;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(6px);
            padding: 25px 30px;
            margin-bottom: 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: inset 0 0 15px rgba(255, 193, 7, 0.1);
        }

        /* Step 7.7: Circular logo container inside top banner */
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

        /* Step 7.8: Logo image formatting inside container */
        .banner-logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Step 7.9: Banner text content wrapper */
        .banner-text-content {
            flex-grow: 1;
            padding: 0 25px;
        }

        /* Step 7.10: Main headline style with gold text-shadow effect */
        .banner-text-content h1 {
            color: #ffc107;
            font-weight: 900;
            font-size: 2.2rem;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9), 0 0 12px rgba(0, 0, 0, 0.7);
        }

        /* Step 7.11: Badge row flex wrapper inside banner */
        .banner-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Step 7.12: Pill badge styling for store feature tags */
        .banner-badge {
            background-color: #ffc107;
            color: #121212;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
        }

        /* Step 7.13: First thin sub-header tagline strip */
        .sub-header-line-1 {
            border: 1px solid #ffc107;
            background: rgba(0, 0, 0, 0.4);
            color: #ffffff;
            height: 36px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Step 7.14: Main sub-header navigation bar container */
        .sub-header-line-2 {
            border: 2px solid #ffc107;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            padding: 10px 20px;
            margin-bottom: 20px;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        /* Step 7.15: Custom navigation list flex alignment */
        .nav-links-custom {
            display: flex;
            justify-content: center;
            gap: 25px;
            list-style: none;
            padding: 0;
            margin: 0;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Step 7.16: Navigation menu item link base styling */
        .nav-links-custom a {
            text-decoration: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            transition: all 0.25s ease;
            padding: 6px 16px;
            border-radius: 50px;
            border: 1px solid transparent;
        }

        /* Step 7.17: Hover and active highlight styling for navbar links */
        .nav-links-custom a:hover,
        .nav-links-custom a.active {
            color: #121212;
            background-color: #ffc107;
            border-color: #d4a000;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.4);
        }

        /* Step 7.18: Split section containers layout wrapper */
        .split-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-start;
        }

        /* Step 7.19: Left box container holding the menu board */
        .left-menu-box {
            flex: 2;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            position: relative;
            padding: 15px;
            min-height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Step 7.20: Interactive board image wrapper */
        .interactive-board-wrapper {
            position: relative;
            width: 100%;
        }

        /* Step 7.21: Menu board image default display and transition effect */
        .menu-img {
            width: 100%;
            height: auto;
            border-radius: 6px;
            display: block;
            transition: opacity 0.3s ease;
        }

        /* Step 7.22: Circular control arrow button styling for switching menu pages */
        .arrow-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: #121212;
            border: 2px solid #ffc107;
            color: #ffc107;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            z-index: 20;
            transition: all 0.2s ease;
            cursor: pointer;
            outline: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Step 7.23: Arrow control button hover animation state */
        .arrow-btn:hover {
            background-color: #ffc107;
            color: #121212;
            transform: translateY(-50%) scale(1.08);
        }

        /* Step 7.24: Left arrow absolute placement */
        .arrow-left { left: 10px; }

        /* Step 7.25: Right arrow absolute placement */
        .arrow-right { right: 10px; }

        /* Step 7.26: Right split box for store details & actions */
        .right-info-box {
            flex: 1;
            border: 2px solid #ffc107;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 20px;
            align-self: flex-start;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 200px;
            max-height: 400px;
            height: 400px;
        }

        /* Step 7.27: Bottom full-width footer line styling */
        .bottom-full-line {
            border: 1px solid #ffc107;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            height: 36px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Step 7.28: Mobile and tablet responsive breakpoint adjustments */
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

    <!-- STEP 8: TOP-LEFT CORNER FLOATING LOGIN / LOGOUT BUTTON -->
    <?php if ($is_logged_in): ?>
        <!-- Step 8.1: Display Logout button if session is currently active -->
        <button type="button" class="top-left-login-btn" onclick="confirmLogout()">
            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout (<?= htmlspecialchars($_SESSION['fullname']) ?>)
        </button>
    <?php else: ?>
        <!-- Step 8.2: Display Login/Register button if guest -->
        <button type="button" class="top-left-login-btn" onclick="openAuthModal('login')">
            <i class="fa-solid fa-user me-2"></i>Login / Register
        </button>
    <?php endif; ?>

    <!-- STEP 9: LARGE OUTER WIREFRAME FRAME -->
    <div class="page-outer-frame">

        <!-- Step 9.1: Top Featured Hero Banner Spot -->
        <section class="top-banner-spot">
            <div class="banner-logo-container">
                <img src="img/logo.png" alt="Sairene TephTeph Logo">
            </div>

            <div class="banner-text-content">
                <h1>Sairene TephTeph Tapsilogan</h1>
                <p class="text-white small mb-2 fw-semibold">Your Favorite Neighborhood Tapsilogan & Tambayan • Fresh Silog Meals Everyday</p>
               
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

        <!-- Step 9.2: Sub-Header Tagline Strip Line 1 -->
        <div class="sub-header-line-1">
            <span class="text-white small fw-bold"><i class="fa-solid fa-star text-warning me-1"></i> A Taste of Comfort • Hot & Fresh Silog Meals <i class="fa-solid fa-star text-warning ms-1"></i></span>
        </div>

        <!-- Step 9.3: Navigation Sub-Header Bar Line 2 -->
        <div class="sub-header-line-2">
            <ul class="nav-links-custom">
                <li><a href="homepage.php" class="active">HOME</a></li>
                <li><a href="menu.php">MENU</a></li>
                <li><a href="order.php">ORDER NOW</a></li>
                <li><a href="food_packs.php">FOOD PACKS</a></li>
                <li><a href="tambay_caps.php">TAMBAY CAPS</a></li>
            </ul>
        </div>

        <!-- Step 9.4: Split Container (Left Interactive Box + Right Info Box) -->
        <div class="split-container">

            <!-- LEFT BOX: Menu Board with Interactive Side Arrows -->
            <div class="left-menu-box">
               
                <!-- Left Arrow Control Button -->
                <button type="button" class="arrow-btn arrow-left" onclick="switchMenuPage('prev')" title="Previous Menu Page">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>

                <div class="interactive-board-wrapper">
                    <img id="activeMenuBoard" src="img/menu1.png" alt="Silog Meals Menu" class="menu-img">
                </div>

                <!-- Right Arrow Control Button -->
                <button type="button" class="arrow-btn arrow-right" onclick="switchMenuPage('next')" title="Next Menu Page">
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </div>

            <!-- RIGHT BOX: History & Quick Action Info -->
            <div class="right-info-box">
                <div>
                    <h5 class="text-warning fw-bold mb-3 border-bottom border-warning pb-2">
                        <i class="fa-solid fa-utensils text-warning me-2"></i>About Our Spot
                    </h5>
                    <p class="small text-white leading-relaxed mb-3">
                        At <strong>Sairene TephTeph Tapsilogan</strong>, we built our spot to feed hungry shift workers, late-night crews, and friends craving authentic Pinoy silog comfort food.
                    </p>
                    <p class="small text-white mb-0">
                        Open 24/7 at MRB Condominium C5, Taguig City.
                    </p>
                </div>

                <div class="mt-4 pt-3 border-top border-warning">
                    <a href="order.php" class="btn btn-warning w-100 fw-bold py-2 text-dark mb-2">
                        <i class="fa-solid fa-motorcycle me-2"></i>Order Online Now
                    </a>
                    <a href="menu.php" class="btn btn-warning w-100 btn-sm fw-bold text-dark">
                        Full Menu View
                    </a>
                </div>
            </div>

        </div>

        <!-- Step 9.5: Bottom Full-Width Copyright Divider Line -->
        <div class="bottom-full-line">
            <span>&copy; <?php echo date('Y'); ?> Sairene TephTeph Tapsilogan • All Rights Reserved</span>
        </div>

    </div>

    <!-- STEP 10: LOGIN & SIGNUP POPUP MODAL DIALOG -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-3 border-warning bg-dark text-white">
                <div class="modal-header border-warning">
                    <h5 class="modal-title fw-bold text-warning" id="authModalTitle">Customer Login</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Step 10.1: Login Form -->
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

                    <!-- Step 10.2: Signup Form (Hidden by default via d-none class) -->
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

    <!-- Step 11: Load Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- STEP 12: MENU SLIDER & AUTHENTICATION JAVASCRIPT HANDLERS -->
    <script>
        // Step 12.1: Track currently visible menu image page
        let currentPage = 1;

        // Step 12.2: Switch menu page image with fade transition effect
        function switchMenuPage(direction) {
            const menuImg = document.getElementById('activeMenuBoard');
           
            // Toggle between Page 1 and Page 2
            if (direction === 'next' || direction === 'prev') {
                currentPage = (currentPage === 1) ? 2 : 1;
            }

            // Quick fade transition effect
            menuImg.style.opacity = '0.3';
            setTimeout(() => {
                if (currentPage === 1) {
                    menuImg.src = 'img/menu1.png';
                    menuImg.alt = 'Silog Meals Menu';
                } else {
                    menuImg.src = 'img/menu2.png';
                    menuImg.alt = 'Combos and Ala Carte Menu';
                }
                menuImg.style.opacity = '1';
            }, 150);
        }

        // Step 12.3: Open auth modal window
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

        // Step 12.4: Toggle form views inside authentication modal
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

        // Step 12.5: Submit authentication form via AJAX fetch request to homepage.php
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

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Redirect admin users directly to pos.php upon login
                    if (data.role === 'admin') {
                        window.location.href = 'pos.php';
                    } else {
                        location.reload();
                    }
                } else {
                    alert(data.message || 'An error occurred during authentication.');
                }
            })
            .catch(err => console.error(err));
        }

        // Step 12.6: Handle user logout prompt and POST request
        function confirmLogout() {
            if (confirm('Are you sure you want to log out?')) {
                let formData = new FormData();
                formData.append('action', 'logout');
                fetch('homepage.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                });
            }
        }

        // Step 12.7: Auto-open login modal if redirected from a protected page with auth_required URL parameter
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('auth_required')) {
                openAuthModal('login');
            }
        });
    </script>
</body>
</html>
