<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Ensure inventory_deducted tracking column exists in orders, kiosk_orders & website_orders tables
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN inventory_deducted INT DEFAULT 0");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE kiosk_orders ADD COLUMN inventory_deducted INT DEFAULT 0");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE website_orders ADD COLUMN inventory_deducted INT DEFAULT 0");
} catch (PDOException $e) {}

// Auto-deduct inventory for unprocessed POS, Kiosk, and Website orders upon admin page load/refresh
try {
    // 1. Process unprocessed POS orders
    $unprocessedPos = $pdo->query("SELECT id FROM orders WHERE inventory_deducted = 0 OR inventory_deducted IS NULL");
    while ($posOrder = $unprocessedPos->fetch(PDO::FETCH_ASSOC)) {
        $orderId = $posOrder['id'];
        $itemStmt = $pdo->prepare("SELECT item_name, quantity FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            deductInventoryForOrderedItem($pdo, $item['item_name'], $item['quantity']);
        }

        $pdo->prepare("UPDATE orders SET inventory_deducted = 1 WHERE id = ?")->execute([$orderId]);
    }

    // 2. Process unprocessed Kiosk orders
    $unprocessedKiosk = $pdo->query("SELECT id FROM kiosk_orders WHERE inventory_deducted = 0 OR inventory_deducted IS NULL");
    while ($kioskOrder = $unprocessedKiosk->fetch(PDO::FETCH_ASSOC)) {
        $orderId = $kioskOrder['id'];
        $itemStmt = $pdo->prepare("SELECT product_name, quantity FROM kiosk_order_items WHERE order_id = ?");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            deductInventoryForOrderedItem($pdo, $item['product_name'], $item['quantity']);
        }

        $pdo->prepare("UPDATE kiosk_orders SET inventory_deducted = 1 WHERE id = ?")->execute([$orderId]);
    }

    // 3. Process unprocessed Website orders
    $unprocessedWeb = $pdo->query("SELECT id FROM website_orders WHERE inventory_deducted = 0 OR inventory_deducted IS NULL");
    while ($webOrder = $unprocessedWeb->fetch(PDO::FETCH_ASSOC)) {
        $orderId = $webOrder['id'];
        $itemStmt = $pdo->prepare("SELECT item_name, quantity FROM website_order_items WHERE order_id = ?");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            deductInventoryForOrderedItem($pdo, $item['item_name'], $item['quantity']);
        }

        $pdo->prepare("UPDATE website_orders SET inventory_deducted = 1 WHERE id = ?")->execute([$orderId]);
    }
} catch (Exception $e) {
    // Graceful handling
}

function deductInventoryForOrderedItem($pdo, $itemName, $qty) {
    $nameLower = strtolower($itemName);
    $reduce = function($invName, $amount) use ($pdo) {
        $stmt = $pdo->prepare("UPDATE inventory_items SET stock = GREATEST(0, stock - ?) WHERE name LIKE ?");
        $stmt->execute([$amount, '%' . $invName . '%']);
    };

    if (strpos($nameLower, 'tapsilog') !== false) {
        $reduce('Tapa Portion', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'bagnet') !== false && strpos($nameLower, 'combo') === false && strpos($nameLower, 'tapa + bagnet') === false && strpos($nameLower, 'bagnet + hungarian') === false) {
        $reduce('Pork Bagnet', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'sisigsilog') !== false || (strpos($nameLower, 'sisig') !== false && strpos($nameLower, 'combo') === false && strpos($nameLower, 'portion') === false && strpos($nameLower, 'sisig + hungarian') === false)) {
        $reduce('Sisig Portion', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'liemposilog') !== false) {
        $reduce('Porkchop Portion', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'malingsilog') !== false) {
        $reduce('Maling', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'tocilog') !== false) {
        $reduce('Tocino Portion', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'hungariansilog') !== false) {
        $reduce('Hungarian Sausage', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'hotdog') !== false) {
        $reduce('Hotdog', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'bagnet + hungarian') !== false) {
        $reduce('Pork Bagnet', $qty);
        $reduce('Hungarian Sausage', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'tapa + hungarian') !== false) {
        $reduce('Tapa Portion', $qty);
        $reduce('Hungarian Sausage', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'tapa + bagnet') !== false) {
        $reduce('Tapa Portion', $qty);
        $reduce('Pork Bagnet', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'liempo + hungarian') !== false) {
        $reduce('Porkchop Portion', $qty);
        $reduce('Hungarian Sausage', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'sisig + hungarian') !== false) {
        $reduce('Sisig Portion', $qty);
        $reduce('Hungarian Sausage', $qty);
        $reduce('Garlic Rice', $qty);
        $reduce('Eggs', $qty);
        $reduce('Take-Out Boxes', $qty);
    } elseif (strpos($nameLower, 'tapa portion') !== false || strpos($nameLower, 'special tapa') !== false) {
        $reduce('Tapa Portion', $qty);
    } elseif (strpos($nameLower, 'bagnet portion') !== false || strpos($nameLower, 'special bagnet') !== false) {
        $reduce('Pork Bagnet', $qty);
    } elseif (strpos($nameLower, 'sisig portion') !== false || strpos($nameLower, 'special sisig') !== false) {
        $reduce('Sisig Portion', $qty);
    } elseif (strpos($nameLower, 'hungarian portion') !== false || strpos($nameLower, 'hungarian sausage') !== false) {
        $reduce('Hungarian Sausage', $qty);
    } elseif (strpos($nameLower, 'garlic rice') !== false || strpos($nameLower, 'plain rice') !== false) {
        $reduce('Garlic Rice', $qty);
    } elseif (strpos($nameLower, 'egg') !== false) {
        $reduce('Eggs', $qty);
    } else {
        $reduce($itemName, $qty);
    }
}


// Ensure inventory_items table exists in MySQL database
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category VARCHAR(50) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
        image VARCHAR(255) DEFAULT 'logo.png',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed default kitchen items & raw ingredients if table is empty
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM inventory_items");
    if ($checkStmt->fetchColumn() == 0) {
        $seedStmt = $pdo->prepare("INSERT INTO inventory_items (name, category, stock, unit, image) VALUES (?, ?, ?, ?, ?)");
        
        // General Kitchen Packaging & Basics
        $seedStmt->execute(['Garlic Rice (Portions)', 'Ingredient', 85, 'cups', 'garlic_rice.png']);
        $seedStmt->execute(['Eggs (Fresh Batch)', 'Ingredient', 120, 'pcs', 'egg.png']);
        $seedStmt->execute(['Take-Out Boxes (Silog)', 'Container', 45, 'packs', 'logo.png']);
        $seedStmt->execute(['Plastic Gravy Cups (2oz)', 'Container', 150, 'pcs', 'logo.png']);

        // Raw Ingredients (40 stock each)
        $seedStmt->execute(['Sisig Portion', 'Ingredient', 40, 'serving', 'special_sisig.png']);
        $seedStmt->execute(['Hotdog', 'Ingredient', 40, 'pcs', 'hotsilog.png']);
        $seedStmt->execute(['Hungarian Sausage', 'Ingredient', 40, 'pcs', 'hungarian.png']);
        $seedStmt->execute(['Maling', 'Ingredient', 40, 'pcs', 'malingsilog.png']);
        $seedStmt->execute(['Tapa Portion', 'Ingredient', 40, 'serving', 'special_tapa.png']);
        $seedStmt->execute(['Pork Bagnet', 'Ingredient', 40, 'pcs', 'special_bagnet.png']);
        $seedStmt->execute(['Tocino Portion', 'Ingredient', 40, 'serving', 'tocilog.png']);
        $seedStmt->execute(['Porkchop Portion', 'Ingredient', 40, 'serving', 'liemposilog.png']);
    }
} catch (PDOException $e) {
    // Graceful error handling
}

$message = '';
$message_type = '';

// HANDLE FORM POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ADD NEW ITEM (WITH FILE/IMAGE UPLOAD TO DATABASE)
    if ($action === 'add_new_item') {
        $item_type     = $_POST['item_type'] ?? 'cap';
        $item_name     = trim($_POST['item_name'] ?? '');
        $initial_stock = max(0, intval($_POST['initial_stock'] ?? 0));
        $price         = floatval($_POST['price'] ?? 0);
        $unit          = trim($_POST['unit'] ?? 'pcs');

        $image_filename = 'logo.png'; // Fallback image

        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['item_image']['tmp_name'];
            $fileName    = $_FILES['item_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($fileName, PATHINFO_FILENAME)) . '.' . $fileExtension;
                $uploadFileDir = __DIR__ . '/img/';

                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $image_filename = $newFileName;
                }
            }
        }

        if (!empty($item_name)) {
            if ($item_type === 'cap') {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tambay_caps (product_name, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $item_name,
                        'Newly added merchandise item.',
                        $price,
                        $initial_stock,
                        $image_filename
                    ]);
                    $message = "New Cap item '{$item_name}' added to database successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Database Error: " . $e->getMessage();
                    $message_type = "danger";
                }
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO inventory_items (name, category, stock, unit, image) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $item_name,
                        ucfirst($item_type),
                        $initial_stock,
                        $unit,
                        $image_filename
                    ]);
                    $message = "New {$item_type} '{$item_name}' added to inventory database successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Database Error: " . $e->getMessage();
                    $message_type = "danger";
                }
            }
        } else {
            $message = "Item name cannot be empty.";
            $message_type = "danger";
        }
    }

        // 2. RESTOCK TAMBAY CAPS IN DATABASE
    if ($action === 'restock_cap') {
        $cap_id  = intval($_POST['cap_id']);
        $set_qty = isset($_POST['set_qty']) ? intval($_POST['set_qty']) : null;

        if ($set_qty === null || $set_qty <= 0) {
            $message = "Invalid Quantity: You cannot set zero or negative stock values!";
            $message_type = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE tambay_caps SET stock = ? WHERE id = ?");
                $stmt->execute([$set_qty, $cap_id]);
                $message = "Cap stock updated successfully in database!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }


    // 3. RESTOCK INGREDIENTS / CONTAINERS
    if ($action === 'restock_inventory') {
        $item_id = intval($_POST['item_id']);
        $add_qty = intval($_POST['add_qty'] ?? 0);

        try {
            $stmt = $pdo->prepare("UPDATE inventory_items SET stock = stock + ? WHERE id = ?");
            $stmt->execute([max(0, $add_qty), $item_id]);
            $message = "Item restocked successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch Inventory Items from MySQL
$inventory_items = [];
try {
    $stmt = $pdo->query("SELECT * FROM inventory_items ORDER BY id ASC");
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventory_items = [];
}

// Fetch Cap List from MySQL Database
$caps = [];
try {
    $stmt = $pdo->query("SELECT * FROM tambay_caps ORDER BY id ASC");
    $caps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $caps = [];
}

// Fetch Combined Sales Summary Across POS, Kiosk, and Website Orders
$recent_orders = [];
$total_kiosk_sales = 0;
$total_items_sold = 0;

try {
    $stmt = $pdo->query("SELECT * FROM kiosk_orders ORDER BY created_at DESC LIMIT 10");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sum revenue across POS, Kiosk, and Website order tables
    $posSales = $pdo->query("SELECT SUM(total_amount) FROM orders")->fetchColumn() ?: 0;
    $kioskSales = $pdo->query("SELECT SUM(total_amount) FROM kiosk_orders")->fetchColumn() ?: 0;
    $webSales = $pdo->query("SELECT SUM(total_amount) FROM website_orders")->fetchColumn() ?: 0;

    $total_kiosk_sales = $posSales + $kioskSales + $webSales;

    // Sum total items sold across all channels
    $posItems = $pdo->query("SELECT SUM(quantity) FROM order_items")->fetchColumn() ?: 0;
    $kioskItems = $pdo->query("SELECT SUM(quantity) FROM kiosk_order_items")->fetchColumn() ?: 0;
    $webItems = $pdo->query("SELECT SUM(quantity) FROM website_order_items")->fetchColumn() ?: 0;

    $total_items_sold = $posItems + $kioskItems + $webItems;
} catch (PDOException $e) {}


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Stock & Image Upload Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #2b1d0c;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.25), rgba(0, 0, 0, 0.35)), url('img/wood.jpg') no-repeat center center;
            background-size: cover;
            z-index: -1;
        }
        .admin-wrapper {
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 2px solid #ffc107;
            border-radius: 16px;
            padding: 35px 30px;
            max-width: 1250px;
            margin: 0 auto;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.6);
        }
        .page-header { color: #ffc107; font-weight: 900; text-transform: uppercase; }
        .item-img-preview { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; border: 1px solid #ffc107; }
        .search-box-container { background: rgba(0, 0, 0, 0.45); border: 2px solid #ffc107; border-radius: 12px; padding: 20px; margin-bottom: 25px; }
        /* White Text Visibility for Stocks Search Bar & Form Controls */
        .search-input { 
            background-color: #121212 !important; 
            color: #ffffff !important; 
            border: 1px solid #ffc107 !important; 
            font-weight: bold; 
        }

        .search-input::placeholder,
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        .form-control, 
        .form-select {
            color: #ffffff !important;
        }

        .form-control:focus, 
        .form-select:focus {
            color: #ffffff !important;
            background-color: #1a1a1a !important;
            border-color: #ffc107 !important;
            box-shadow: 0 0 10px rgba(255, 193, 7, 0.5) !important;
        }
        /* Strictly targets ONLY the Current Stock numbers & unit text in the table */
        td.font-monospace {
            color: #ffffff !important;
        }
        
        .filter-btn-group .btn {
            border-radius: 50px;
            font-weight: 700;
            padding: 8px 18px;
            transition: all 0.2s ease;
        }
        .filter-btn-group .btn-outline-warning:hover,
        .filter-btn-group .btn-outline-warning.active {
            background-color: #ffc107 !important;
            color: #121212 !important;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.7rem;
            font-weight: 800;
            color: #ffc107;
            font-family: monospace;
        }
        .admin-panel { background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 193, 7, 0.5); border-radius: 12px; padding: 22px; margin-bottom: 25px; }
        .table-custom { color: #ffffff; vertical-align: middle; }
        .table-custom th { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; border-color: #ffc107; }
        .table-custom td { border-color: rgba(255, 193, 7, 0.2); background-color: rgba(20, 20, 20, 0.5); }
        .badge-low-stock { background-color: #dc3545; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-in-stock { background-color: #198754; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
    </style>
</head>
<body>

    <div class="admin-wrapper">
        <div class="d-flex justify-content-between align-items-center border-bottom border-warning pb-3 mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-header m-0"><i class="fa-solid fa-boxes-packing me-2"></i>Inventory & Database Admin</h2>
                <p class="text-light small m-0 mt-1">Add items with image upload, update MySQL records, and track stock.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Add Item & Upload Image
                </button>
                <a href="kiosk.php" class="btn btn-outline-warning btn-sm"><i class="fa-solid fa-desktop me-1"></i> Kiosk View</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <span class="text-light small text-uppercase fw-bold">Total Kiosk Revenue</span>
                    <div class="stat-number">₱<?php echo number_format($total_kiosk_sales, 2); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <span class="text-light small text-uppercase fw-bold">Items Sold</span>
                    <div class="stat-number"><?php echo number_format($total_items_sold); ?> pcs</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <span class="text-light small text-uppercase fw-bold">Cap Merchandise Lineup</span>
                    <div class="stat-number"><?php echo count($caps); ?> Items</div>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR & CATEGORY BUTTON CONTAINERS -->
        <div class="search-box-container">
            <div class="row g-3 align-items-center mb-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-warning text-warning"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="adminSearchInput" class="form-control form-control-lg search-input" placeholder="Search food, ingredients, containers, or cap items..." onkeyup="filterAdminStock()">
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2 fw-bold" id="itemMatchCount">Showing all items</span>
                </div>
            </div>

            <!-- INVENTORY TYPE FILTER BUTTONS -->
            <div class="d-flex flex-wrap gap-2 filter-btn-group">
                <button type="button" class="btn btn-outline-warning active" onclick="setCategoryFilter('all', this)">
                    <i class="fa-solid fa-layer-group me-1"></i> All Items
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="setCategoryFilter('ingredient', this)">
                    <i class="fa-solid fa-utensils me-1"></i> Raw Ingredients
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="setCategoryFilter('container', this)">
                    <i class="fa-solid fa-box-open me-1"></i> Packaging Containers
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="setCategoryFilter('cap', this)">
                    <i class="fa-solid fa-hat-cowboy me-1"></i> Cap Merchandise
                </button>
            </div>
        </div>

        <!-- SECTION 1: INGREDIENTS & CONTAINERS -->
        <div class="admin-panel" id="kitchenSection">
            <h4 class="text-warning fw-bold mb-3"><i class="fa-solid fa-box-open me-2"></i>Kitchen Ingredients & Containers</h4>
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                            <th class="text-center">Restock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): ?>
                            <tr class="searchable-row" data-category="<?php echo strtolower($item['category']); ?>">
                                <td><img src="img/<?php echo htmlspecialchars($item['image'] ?? 'logo.png'); ?>" class="item-img-preview" alt="Preview" onerror="this.src='img/logo.png'"></td>
                                <td class="fw-bold text-warning item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><span class="badge bg-dark border border-warning text-warning"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <td class="font-monospace fw-bold"><?php echo $item['stock']; ?> <?php echo $item['unit']; ?></td>
                                <td>
    <?php if ($item['stock'] <= 0): ?>
        <span class="badge bg-danger text-white px-2 py-1 rounded-pill fw-bold fs-8"><i class="fa-solid fa-circle-xmark me-1"></i> Out of Stock</span>
    <?php elseif ($item['stock'] <= 15): ?>
        <span class="badge-low-stock"><i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock</span>
    <?php else: ?>
        <span class="badge-in-stock"><i class="fa-solid fa-circle-check me-1"></i> In Stock</span>
    <?php endif; ?>
</td>

                                <td>
                                    <form action="admin.php" method="POST" class="d-flex justify-content-center align-items-center gap-2">
                                        <input type="hidden" name="action" value="restock_inventory">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="add_qty" value="10" class="btn btn-outline-warning btn-sm">+10</button>
                                        <button type="submit" name="add_qty" value="40" class="btn btn-outline-warning btn-sm">+40</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 2: TAMBAY CAPS (DATABASE TABLE) -->
        <div class="admin-panel" id="capsSection">
            <h4 class="text-warning fw-bold mb-3"><i class="fa-solid fa-hat-cowboy me-2"></i>Tambay Caps Product Inventory (MySQL DB)</h4>
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>ID</th>
                            <th>Cap Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th class="text-center">Update Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caps as $cap): ?>
                            <tr class="searchable-row" data-category="cap">
                                <td><img src="img/<?php echo htmlspecialchars($cap['image_url'] ?? 'logo.png'); ?>" class="item-img-preview" alt="Cap" onerror="this.src='img/logo.png'"></td>
                                <td>#<?php echo $cap['id']; ?></td>
                                <td class="fw-bold text-warning item-name"><?php echo htmlspecialchars($cap['product_name']); ?></td>
                                <td class="font-monospace">₱<?php echo number_format($cap['price'], 2); ?></td>
                                <td class="font-monospace fw-bold"><?php echo $cap['stock']; ?> pcs</td>
                                <td>
    <?php if ($cap['stock'] <= 0): ?>
        <span class="badge bg-danger text-white px-2 py-1 rounded-pill fw-bold fs-8"><i class="fa-solid fa-circle-xmark me-1"></i> Out of Stock</span>
    <?php elseif ($cap['stock'] <= 5): ?>
        <span class="badge-low-stock"><i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock</span>
    <?php else: ?>
        <span class="badge-in-stock"><i class="fa-solid fa-circle-check me-1"></i> In Stock</span>
    <?php endif; ?>
</td>

                                <td>
                                    <form action="admin.php" method="POST" class="d-flex justify-content-center align-items-center gap-2">
                                        <input type="hidden" name="action" value="restock_cap">
                                        <input type="hidden" name="cap_id" value="<?php echo $cap['id']; ?>">
                                        <input type="number" name="set_qty" value="<?php echo $cap['stock']; ?>" min="0" class="form-control form-control-sm bg-dark text-white border-warning text-center" style="width: 80px;">
                                        <button type="submit" class="btn btn-warning btn-sm fw-bold text-dark">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL FOR ADDING NEW ITEM & UPLOADING IMAGE -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-2 border-warning bg-dark text-white">
                <div class="modal-header border-warning">
                    <h5 class="modal-title fw-bold text-warning"><i class="fa-solid fa-plus-circle me-2"></i>Add New Item to Database</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="admin.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_new_item">
                    <div class="modal-body p-4">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-warning">Select Category</label>
                            <select name="item_type" id="itemTypeSelect" class="form-select bg-dark text-white border-warning fw-bold" onchange="togglePriceField(this.value)">
                                <option value="cap" selected>Tambay Cap (Saves to MySQL Database)</option>
                                <option value="ingredient">Food Ingredient (Kitchen Stock)</option>
                                <option value="container">Packaging Container (Take-out Box/Cups)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-warning">Item / Product Name</label>
                            <input type="text" name="item_name" class="form-control bg-dark text-white border-warning" placeholder="e.g. Special Lechon Kawali Portion" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-warning">Product Image File</label>
                            <input type="file" name="item_image" accept="image/*" class="form-control bg-dark text-white border-warning">
                            <span class="small text-light opacity-75">Accepted formats: .jpg, .png, .webp (Saves to <code>/img/</code> folder)</span>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-warning">Initial Stock Quantity</label>
                                <input type="number" name="initial_stock" class="form-control bg-dark text-white border-warning" value="40" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-warning">Unit Type</label>
                                <input type="text" name="unit" class="form-control bg-dark text-white border-warning" value="serving" placeholder="serving, pcs, packs">
                            </div>
                        </div>

                        <div class="mb-3" id="priceFieldContainer">
                            <label class="form-label small fw-bold text-warning">Price (₱)</label>
                            <input type="number" step="0.01" name="price" class="form-control bg-dark text-white border-warning" value="350.00">
                        </div>

                    </div>
                    <div class="modal-footer border-warning">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning btn-sm fw-bold text-dark"><i class="fa-solid fa-upload me-1"></i> Upload & Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'all';

        function setCategoryFilter(category, btnElement) {
            currentFilter = category;

            document.querySelectorAll('.filter-btn-group .btn').forEach(btn => btn.classList.remove('active'));
            if (btnElement) btnElement.classList.add('active');

            const kitchenSection = document.getElementById('kitchenSection');
            const capsSection = document.getElementById('capsSection');

            if (category === 'cap') {
                kitchenSection.style.display = 'none';
                capsSection.style.display = 'block';
            } else if (category === 'ingredient' || category === 'container') {
                kitchenSection.style.display = 'block';
                capsSection.style.display = 'none';
            } else {
                kitchenSection.style.display = 'block';
                capsSection.style.display = 'block';
            }

            filterAdminStock();
        }

        function filterAdminStock() {
            let input = document.getElementById('adminSearchInput').value.toLowerCase().trim();
            let rows = document.querySelectorAll('.searchable-row');
            let matchCount = 0;

            rows.forEach(row => {
                let itemName = row.querySelector('.item-name').innerText.toLowerCase();
                let rowCategory = row.getAttribute('data-category');

                let matchesSearch = itemName.includes(input);
                let matchesCategory = (currentFilter === 'all') || (rowCategory === currentFilter);

                if (matchesSearch && matchesCategory) {
                    row.style.display = '';
                    matchCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('itemMatchCount').innerText = (input === '' && currentFilter === 'all') ? "Showing all items" : matchCount + " item(s) found";
        }

        function togglePriceField(type) {
            document.getElementById('priceFieldContainer').style.display = (type === 'cap') ? 'block' : 'none';
        }

// Validate stock values in Add Item Modal & Restock forms to block 0 or negative stocks
document.addEventListener('DOMContentLoaded', function() {
    const addItemForm = document.querySelector('#addItemModal form');
    if (addItemForm) {
        addItemForm.addEventListener('submit', function(e) {
            const stockInput = addItemForm.querySelector('input[name="initial_stock"]');
            if (stockInput && parseInt(stockInput.value) <= 0) {
                e.preventDefault();
                alert('Invalid Stock Quantity! You cannot set zero or negative initial stock.');
            }
        });
    }

    document.querySelectorAll('form').forEach(form => {
        const setQtyInput = form.querySelector('input[name="set_qty"]');
        if (setQtyInput) {
            form.addEventListener('submit', function(e) {
                if (parseInt(setQtyInput.value) <= 0) {
                    e.preventDefault();
                    alert('Invalid Quantity! Stock cannot be updated to zero or a negative value.');
                }
            });
        }
    });
});

    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
