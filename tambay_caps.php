<?php
// Include your database connection
require_once '../backend/config/db.php';

try {
    // Fetch all caps from the database
    $stmt = $pdo->query("SELECT * FROM tambay_caps");
    $caps = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching caps: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambay Caps | Sairene Tephteph Tapsilogan</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- CSS Styles -->
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
            padding: 8px 16px;
            transition: all 0.2s ease-in-out;
        }

        .btn-gold:hover {
            background-color: #e0a800;
            color: #000;
            transform: scale(1.05);
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
                    <a class="nav-link" href="menu.php">Menu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="milktea.php">Milk Tea</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="tambay_caps.php">Tambay Caps</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="food_packs.php">Food Packs</a>
                </li>
            </ul>
        </nav>

        <!-- Header Section -->
        <header class="text-center mb-5">
            <h1 class="display-5 fw-bold text-gold">TAMBAY CAPS COLLECTION</h1>
            <p class="lead text-muted">Level up your street style while grabbing your favorite tapsilog. Grab yours now!</p>
        </header>

        <!-- Product Grid Container -->
        <main class="mb-5">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger text-center rounded-4 shadow-sm" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php elseif (empty($caps)): ?>
                <div class="text-center text-muted py-5">
                    <p class="fs-5">No caps available at the moment. Check back soon!</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($caps as $cap): ?>
                        <div class="col">
                            <div class="card bg-secondary bg-opacity-10 border border-secondary border-opacity-25 rounded-4 h-100 shadow-sm overflow-hidden d-flex flex-column justify-content-between">
                                <div>
                                    <!-- Product Image -->
                                    <div class="position-relative bg-dark" style="height: 220px;">
                                        <?php if (!empty($cap['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($cap['image_url']) ?>" alt="<?= htmlspecialchars($cap['product_name']) ?>" class="w-100 h-100 object-fit-cover">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted fw-bold small text-uppercase tracking-wider">
                                                Cap Image
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Stock Badge -->
                                        <span class="position-absolute top-0 end-0 m-3 badge bg-dark bg-opacity-75 text-light border border-secondary border-opacity-50">
                                            Stock: <?= htmlspecialchars($cap['stock']) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="p-4">
                                        <h3 class="h5 fw-bold text-light mb-2"><?= htmlspecialchars($cap['product_name']) ?></h3>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($cap['description']) ?></p>
                                    </div>
                                </div>
                                
                                <!-- Card Footer / Price & Button -->
                                <div class="px-4 pb-4 pt-3 border-top border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                                    <span class="fs-5 fw-bold text-gold">₱<?= number_format($cap['price'], 2) ?></span>
                                    <button class="btn btn-gold btn-sm">
                                        <i class="fa-solid fa-cart-plus me-1"></i> Add to Order
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="text-center mt-5 text-muted small">
            <p class="mb-1">MRB Condominium C5, Taguig City</p>
            <p class="mb-0">GCash Payments Accepted: 09270466231 (Sarippudin Aming)</p>
        </footer>

    </div>

</body>
</html>