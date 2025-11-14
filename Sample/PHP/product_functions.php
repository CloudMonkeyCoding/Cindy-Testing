<?php

function normalizeProductCategoryValue($category)
{
    if (!is_string($category)) {
        return '';
    }

    $trimmed = trim($category);
    if ($trimmed === '') {
        return '';
    }

    return stripos($trimmed, 'pastry') !== false ? 'Bread' : $trimmed;
}

// 1) Add a new product
function addProduct($pdo, $name, $description, $price, $stock_quantity, $category, $imageFile = null) {
    $imageName = null;
    if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageName = processImageUpload($imageFile);
    }

    $stmt = $pdo->prepare("
        INSERT INTO product (Name, Description, Price, Stock_Quantity, Category, Image_Path)
        VALUES (:name, :description, :price, :stock_quantity, :category, :image_path)
    ");
    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':price' => $price,
        ':stock_quantity' => $stock_quantity,
        ':category' => $category,
        ':image_path' => $imageName
    ]);
    return $pdo->lastInsertId();
}

// 2) Get all products
function getAllProducts($pdo) {
    $sql = "SELECT\n"
        . "    p.Product_ID,\n"
        . "    p.Name,\n"
        . "    p.Description,\n"
        . "    p.Price,\n"
        . "    COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n"
        . "    p.Category,\n"
        . "    p.Image_Path,\n"
        . "    IFNULL(p.Is_Archived, 0) AS Is_Archived\n"
        . "FROM product p\n"
        . "LEFT JOIN inventory i ON i.Product_ID = p.Product_ID\n"
        . "WHERE IFNULL(p.Is_Archived, 0) = 0\n"
        . "ORDER BY p.Product_ID DESC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getArchivedProducts($pdo) {
    $sql = "SELECT\n"
        . "    p.Product_ID,\n"
        . "    p.Name,\n"
        . "    p.Description,\n"
        . "    p.Price,\n"
        . "    COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n"
        . "    p.Category,\n"
        . "    p.Image_Path,\n"
        . "    IFNULL(p.Is_Archived, 0) AS Is_Archived\n"
        . "FROM product p\n"
        . "LEFT JOIN inventory i ON i.Product_ID = p.Product_ID\n"
        . "WHERE IFNULL(p.Is_Archived, 0) = 1\n"
        . "ORDER BY p.Product_ID DESC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2a) Get products by category
function getProductsByCategory($pdo, $category) {
    if (!is_string($category)) {
        return [];
    }

    $normalized = strtolower(trim($category));
    if ($normalized === '') {
        return [];
    }

    $categoryGroups = [
        'bread' => ['bread', 'breads', 'pastry', 'pastries'],
        'cake' => ['cake', 'cakes'],
    ];

    if (isset($categoryGroups[$normalized])) {
        $matches = $categoryGroups[$normalized];
    } elseif ($normalized === 'breads') {
        $matches = $categoryGroups['bread'];
    } elseif ($normalized === 'cakes') {
        $matches = $categoryGroups['cake'];
    } elseif (in_array($normalized, $categoryGroups['bread'], true)) {
        $matches = $categoryGroups['bread'];
    } elseif (in_array($normalized, $categoryGroups['cake'], true)) {
        $matches = $categoryGroups['cake'];
    } else {
        $matches = [$normalized];
    }

    $placeholders = implode(', ', array_fill(0, count($matches), '?'));

    $sql = "SELECT\n"
        . "    p.Product_ID,\n"
        . "    p.Name,\n"
        . "    p.Description,\n"
        . "    p.Price,\n"
        . "    COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n"
        . "    p.Category,\n"
        . "    p.Image_Path,\n"
        . "    IFNULL(p.Is_Archived, 0) AS Is_Archived\n"
        . "FROM product p\n"
        . "LEFT JOIN inventory i ON i.Product_ID = p.Product_ID\n"
        . "WHERE LOWER(p.Category) IN ($placeholders) AND IFNULL(p.Is_Archived, 0) = 0\n"
        . "ORDER BY p.Product_ID DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_map('strtolower', $matches));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductImageUrl(array $product, string $relativePrefix = ''): string
{
    $relativePrefix = $relativePrefix === '' ? '' : rtrim($relativePrefix, '/\\') . '/';
    $imagePath = isset($product['Image_Path']) ? trim((string)$product['Image_Path']) : '';
    if ($imagePath === '') {
        return $relativePrefix . 'Images/logo.png';
    }

    $imageFile = basename(str_replace('\\', '/', $imagePath));
    if ($imageFile === '') {
        return $relativePrefix . 'Images/logo.png';
    }

    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false) {
        $projectRoot = dirname(__DIR__);
    }

    $uploadsAbsolute = $projectRoot . '/adminSide/products/uploads/' . $imageFile;
    if (is_file($uploadsAbsolute)) {
        return $relativePrefix . 'adminSide/products/uploads/' . $imageFile;
    }

    $categoryKey = strtolower((string)($product['Category'] ?? ''));
    $categoryMap = [
        'bread' => 'bread',
        'breads' => 'bread',
        'cake' => 'cakes',
        'cakes' => 'cakes',
        'pastry' => 'pastry',
        'pastries' => 'pastry',
    ];

    if (isset($categoryMap[$categoryKey])) {
        $categoryDir = $categoryMap[$categoryKey];
        $categoryAbsolute = $projectRoot . '/Images/' . $categoryDir . '/' . $imageFile;
        if (is_file($categoryAbsolute)) {
            return $relativePrefix . 'Images/' . $categoryDir . '/' . $imageFile;
        }
    }

    $imagesAbsolute = $projectRoot . '/Images/' . $imageFile;
    if (is_file($imagesAbsolute)) {
        return $relativePrefix . 'Images/' . $imageFile;
    }

    return $relativePrefix . 'Images/logo.png';
}

// 3) Get a product by ID
function getProductById($pdo, $productId) {
    $sql = "SELECT\n"
        . "    p.Product_ID,\n"
        . "    p.Name,\n"
        . "    p.Description,\n"
        . "    p.Price,\n"
        . "    COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n"
        . "    p.Category,\n"
        . "    p.Image_Path,\n"
        . "    IFNULL(p.Is_Archived, 0) AS Is_Archived\n"
        . "FROM product p\n"
        . "LEFT JOIN inventory i ON i.Product_ID = p.Product_ID\n"
        . "WHERE p.Product_ID = :product_id AND IFNULL(p.Is_Archived, 0) = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':product_id' => $productId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4) Update product details
function updateProductById($pdo, $productId, $name, $description, $price, $stock_quantity, $category, $imageFile = null, $keepImage = false, $removeImage = false) {
    $imageName = null;
    $shouldUpdateImage = false;

    if ($removeImage) {
        $shouldUpdateImage = true;
        $imageName = null;
    } elseif (!$keepImage && $imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded = processImageUpload($imageFile);
        if ($uploaded !== null) {
            $imageName = $uploaded;
            $shouldUpdateImage = true;
        }
    }

    $sql = "UPDATE product SET Name = :name, Description = :description, Price = :price, Stock_Quantity = :stock_quantity, Category = :category";
    if ($shouldUpdateImage) {
        $sql .= ", Image_Path = :image_path";
    }
    $sql .= " WHERE Product_ID = :product_id";

    $stmt = $pdo->prepare($sql);
    $params = [
        ':name' => $name,
        ':description' => $description,
        ':price' => $price,
        ':stock_quantity' => $stock_quantity,
        ':category' => $category,
        ':product_id' => $productId
    ];
    if ($shouldUpdateImage) {
        $params[':image_path'] = $imageName;
    }
    $stmt->execute($params);
    return $stmt->rowCount(); // rows updated
}

function processImageUpload($imageFile) {
    $allowedTypes = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif'];

    if ($imageFile['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $mime = mime_content_type($imageFile['tmp_name']);
    if (!isset($allowedTypes[$mime])) {
        return null;
    }

    if ($imageFile['size'] > 2 * 1024 * 1024) { // 2MB
        return null;
    }

    $targetDir = __DIR__ . '/../adminSide/products/uploads/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $fileName = uniqid('prod_', true) . $allowedTypes[$mime];
    $targetPath = $targetDir . $fileName;
    if (!move_uploaded_file($imageFile['tmp_name'], $targetPath)) {
        return null;
    }

    return $fileName;
}

// 5) Archive product by ID
function archiveProductById($pdo, $productId) {
    $stmt = $pdo->prepare("UPDATE product SET Is_Archived = 1 WHERE Product_ID = :product_id");
    $stmt->execute([':product_id' => $productId]);
    return $stmt->rowCount();
}

function restoreProductById($pdo, $productId) {
    $stmt = $pdo->prepare("UPDATE product SET Is_Archived = 0 WHERE Product_ID = :product_id");
    $stmt->execute([':product_id' => $productId]);
    return $stmt->rowCount();
}

// Backwards compatibility helper for any legacy delete calls
function deleteProductById($pdo, $productId) {
    return archiveProductById($pdo, $productId);
}

// 6) Search products by name or category
function searchProducts($pdo, $keyword) {
    $sql = "SELECT\n"
        . "    p.Product_ID,\n"
        . "    p.Name,\n"
        . "    p.Description,\n"
        . "    p.Price,\n"
        . "    COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n"
        . "    p.Category,\n"
        . "    p.Image_Path,\n"
        . "    IFNULL(p.Is_Archived, 0) AS Is_Archived\n"
        . "FROM product p\n"
        . "LEFT JOIN inventory i ON i.Product_ID = p.Product_ID\n"
        . "WHERE (p.Name LIKE :kw OR p.Category LIKE :kw)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':kw' => "%$keyword%"]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 7) Adjust stock quantity (+/-)
function adjustProductStock($pdo, $productId, $quantityChange) {
    $stmt = $pdo->prepare("
        UPDATE product
        SET Stock_Quantity = Stock_Quantity + :quantity_change
        WHERE Product_ID = :product_id
    ");
    $stmt->execute([
        ':quantity_change' => $quantityChange,
        ':product_id' => $productId
    ]);
    return $stmt->rowCount();
}

// 8) Count total products (optional)
function countProducts($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM product WHERE IFNULL(Is_Archived, 0) = 0");
    return $stmt->fetchColumn();
}

// --- API Endpoints -------------------------------------------------------
// Allows this file to handle update, delete, and list operations via AJAX.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/db_connect.php';
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    header('Content-Type: application/json');

    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    switch ($action) {
        case 'add':
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $price = $price !== false ? $price : 0;
            $stock = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
            $stock = $stock !== false ? $stock : 0;
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $imageFile = $_FILES['image'] ?? null;
            $productId = addProduct($pdo, $name, $description, $price, $stock, $category, $imageFile);
            echo json_encode(['success' => (bool)$productId]);
            break;

        case 'update':
            $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?? 0;
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $price = $price !== false ? $price : 0;
            $stock = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
            $stock = $stock !== false ? $stock : 0;
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
            $imageFile = $_FILES['image'] ?? null;
            $keepImage = filter_input(INPUT_POST, 'keep_current_image', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $keepImage = $keepImage ?? false;
            $removeImage = filter_input(INPUT_POST, 'remove_image', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $removeImage = $removeImage ?? false;
            if ($removeImage) {
                $keepImage = false;
            }
            $success = updateProductById($pdo, $id, $name, $description, $price, $stock, $category, $imageFile, $keepImage, $removeImage) > 0;
            echo json_encode(['success' => $success]);
            break;

        case 'archive':
        case 'delete':
            $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?? 0;
            $success = archiveProductById($pdo, $id) > 0;
            echo json_encode(['success' => $success]);
            break;

        case 'unarchive':
            $id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?? 0;
            $success = restoreProductById($pdo, $id) > 0;
            echo json_encode(['success' => $success]);
            break;

        case 'getAll':
            $products = getAllProducts($pdo);
            $products = array_map(static function ($product) {
                $product['Image_Url'] = getProductImageUrl($product, '../');
                return $product;
            }, $products);
            echo json_encode($products);
            break;

        case 'getArchived':
            $products = getArchivedProducts($pdo);
            $products = array_map(static function ($product) {
                $product['Image_Url'] = getProductImageUrl($product, '../');
                return $product;
            }, $products);
            echo json_encode($products);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit;
}
?>
