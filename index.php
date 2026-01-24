<?php
/*
 * Main entry point for the Mini Inventory System.
 *
 * This script presents a modern web interface for managing products,
 * suppliers, purchases and orders.  It requires the user to be
 * authenticated; a session is started at the top and the user is
 * redirected to login.php if not logged in.  CRUD operations are
 * implemented with prepared statements to prevent SQL injection.  The
 * application supports file uploads for product images and for
 * attachments on purchases and orders.  Bootstrap 5 is used for
 * styling and Chart.js is used on the Reports page.
 */

session_start();
// Redirect unauthenticated users to the login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Connect to the MySQL database using environment variables with sensible defaults
$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'sqldb';
$user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'root';
$pass = getenv('DB_PASSWORD');
if ($pass === false) {
    $pass = getenv('MYSQL_PASSWORD');
    if ($pass === false) {
        $pass = getenv('MYSQL_ROOT_PASSWORD') ?: '';
    }
}
$connection = mysqli_connect($host, $user, $pass, $db);
if (!$connection) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Determine which page to show
$page = isset($_GET['page']) ? $_GET['page'] : 'products';

// Helper functions to fetch lists of suppliers and products
function getSuppliers($conn) {
    $result = mysqli_query($conn, 'SELECT id, name FROM suppliers ORDER BY name');
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getProducts($conn) {
    $result = mysqli_query($conn, 'SELECT id, name FROM products ORDER BY name');
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Helper to fetch a single record for editing
function getRecord($conn, $table, $id) {
    $id = intval($id);
    $res = mysqli_query($conn, "SELECT * FROM `$table` WHERE id = $id");
    return mysqli_fetch_assoc($res);
}

// Handle form submissions for create/update operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Products
    if (isset($_POST['entity']) && $_POST['entity'] === 'product') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        $supplier_id = intval($_POST['supplier_id']);
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $tmpName = $_FILES['image']['tmp_name'];
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $targetFile = $uploadDir . uniqid('img_') . '.' . $ext;
            if (move_uploaded_file($tmpName, $targetFile)) {
                $imagePath = 'uploads/' . basename($targetFile);
            }
        }
        if ($id === '') {
            if ($name !== '') {
                if ($imagePath) {
                    $stmt = mysqli_prepare($connection, 'INSERT INTO products(name, description, price, quantity, supplier_id, image_path) VALUES (?, ?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'ssdiss', $name, $description, $price, $quantity, $supplier_id, $imagePath);
                } else {
                    $stmt = mysqli_prepare($connection, 'INSERT INTO products(name, description, price, quantity, supplier_id) VALUES (?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'ssdii', $name, $description, $price, $quantity, $supplier_id);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                echo "<script>alert('Product added');</script>";
            } else {
                echo "<script>alert('Please enter a product name');</script>";
            }
        } else {
            if ($imagePath) {
                $stmt = mysqli_prepare($connection, 'UPDATE products SET name = ?, description = ?, price = ?, quantity = ?, supplier_id = ?, image_path = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssdiisi', $name, $description, $price, $quantity, $supplier_id, $imagePath, $id);
            } else {
                $stmt = mysqli_prepare($connection, 'UPDATE products SET name = ?, description = ?, price = ?, quantity = ?, supplier_id = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'ssdiii', $name, $description, $price, $quantity, $supplier_id, $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Product updated');</script>";
        }
    }
    // Suppliers
    if (isset($_POST['entity']) && $_POST['entity'] === 'supplier') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $address = trim($_POST['address']);
        if ($id === '') {
            if ($name !== '') {
                $stmt = mysqli_prepare($connection, 'INSERT INTO suppliers(name, contact, address) VALUES (?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'sss', $name, $contact, $address);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                echo "<script>alert('Supplier added');</script>";
            } else {
                echo "<script>alert('Please enter a supplier name');</script>";
            }
        } else {
            $stmt = mysqli_prepare($connection, 'UPDATE suppliers SET name = ?, contact = ?, address = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssi', $name, $contact, $address, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Supplier updated');</script>";
        }
    }
    // Purchases
    if (isset($_POST['entity']) && $_POST['entity'] === 'purchase') {
        $id = $_POST['id'] ?? '';
        $product_id = intval($_POST['product_id']);
        $supplier_id = intval($_POST['supplier_id']);
        $quantity = intval($_POST['quantity']);
        $date = $_POST['date'];
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $targetFile = $uploadDir . uniqid('att_') . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                $attachment = 'uploads/' . basename($targetFile);
            }
        }
        if ($id === '') {
            if ($attachment) {
                $stmt = mysqli_prepare($connection, 'INSERT INTO purchases(product_id, supplier_id, quantity, purchase_date, attachment_path) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iiiss', $product_id, $supplier_id, $quantity, $date, $attachment);
            } else {
                $stmt = mysqli_prepare($connection, 'INSERT INTO purchases(product_id, supplier_id, quantity, purchase_date) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iiis', $product_id, $supplier_id, $quantity, $date);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Purchase added');</script>";
        } else {
            if ($attachment) {
                $stmt = mysqli_prepare($connection, 'UPDATE purchases SET product_id = ?, supplier_id = ?, quantity = ?, purchase_date = ?, attachment_path = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'iiissi', $product_id, $supplier_id, $quantity, $date, $attachment, $id);
            } else {
                $stmt = mysqli_prepare($connection, 'UPDATE purchases SET product_id = ?, supplier_id = ?, quantity = ?, purchase_date = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'iiisi', $product_id, $supplier_id, $quantity, $date, $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Purchase updated');</script>";
        }
    }
    // Orders
    if (isset($_POST['entity']) && $_POST['entity'] === 'order') {
        $id = $_POST['id'] ?? '';
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $date = $_POST['date'];
        $status = trim($_POST['status']);
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $targetFile = $uploadDir . uniqid('ord_') . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                $attachment = 'uploads/' . basename($targetFile);
            }
        }
        if ($id === '') {
            if ($attachment) {
                $stmt = mysqli_prepare($connection, 'INSERT INTO orders(product_id, quantity, order_date, status, attachment_path) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iisss', $product_id, $quantity, $date, $status, $attachment);
            } else {
                $stmt = mysqli_prepare($connection, 'INSERT INTO orders(product_id, quantity, order_date, status) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iiss', $product_id, $quantity, $date, $status);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Order added');</script>";
        } else {
            if ($attachment) {
                $stmt = mysqli_prepare($connection, 'UPDATE orders SET product_id = ?, quantity = ?, order_date = ?, status = ?, attachment_path = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'iisssi', $product_id, $quantity, $date, $status, $attachment, $id);
            } else {
                $stmt = mysqli_prepare($connection, 'UPDATE orders SET product_id = ?, quantity = ?, order_date = ?, status = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'iissi', $product_id, $quantity, $date, $status, $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Order updated');</script>";
        }
    }
}

// Handle deletions via GET parameters
if (isset($_GET['delete']) && isset($_GET['entity'])) {
    $id = intval($_GET['delete']);
    $entity = $_GET['entity'];
    $table = '';
    if ($entity === 'product') $table = 'products';
    if ($entity === 'supplier') $table = 'suppliers';
    if ($entity === 'purchase') $table = 'purchases';
    if ($entity === 'order') $table = 'orders';
    if ($table) {
        mysqli_query($connection, "DELETE FROM `$table` WHERE id = $id");
        echo "<script>alert('Record deleted');</script>";
    }
}

// Determine if we are editing an existing record
$updateData = null;
if (isset($_GET['edit']) && isset($_GET['entity'])) {
    $entity = $_GET['entity'];
    $id = intval($_GET['edit']);
    if ($entity === 'product') $updateData = getRecord($connection, 'products', $id);
    if ($entity === 'supplier') $updateData = getRecord($connection, 'suppliers', $id);
    if ($entity === 'purchase') $updateData = getRecord($connection, 'purchases', $id);
    if ($entity === 'order') $updateData = getRecord($connection, 'orders', $id);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mini Inventory System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Inventory System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?php echo $page === 'products' ? 'active' : ''; ?>" href="?page=products">Products</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $page === 'suppliers' ? 'active' : ''; ?>" href="?page=suppliers">Suppliers</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $page === 'purchases' ? 'active' : ''; ?>" href="?page=purchases">Purchases</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $page === 'orders' ? 'active' : ''; ?>" href="?page=orders">Orders</a></li>
                <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>" href="reports.php">Reports</a></li>
            </ul>
            <span class="navbar-text me-3">Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a class="btn btn-outline-light" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">
<?php
switch ($page) {
    case 'suppliers':
        echo '<h2>Suppliers</h2>';
        $sid = $updateData['id'] ?? '';
        $sname = $updateData['name'] ?? '';
        $scontact = $updateData['contact'] ?? '';
        $saddress = $updateData['address'] ?? '';
        echo '<form method="post" action="" class="row g-2">';
        echo '<input type="hidden" name="entity" value="supplier">';
        echo '<input type="hidden" name="id" value="' . $sid . '">';
        echo '<div class="col-md-3"><input class="form-control" type="text" name="name" placeholder="Name" value="' . htmlspecialchars($sname) . '" required></div>';
        echo '<div class="col-md-3"><input class="form-control" type="text" name="contact" placeholder="Contact" value="' . htmlspecialchars($scontact) . '"></div>';
        echo '<div class="col-md-4"><input class="form-control" type="text" name="address" placeholder="Address" value="' . htmlspecialchars($saddress) . '"></div>';
        echo '<div class="col-md-2"><button type="submit" class="btn btn-primary w-100">' . ($sid ? 'Update' : 'Add') . '</button></div>';
        echo '</form>';
        $res = mysqli_query($connection, 'SELECT * FROM suppliers ORDER BY id DESC');
        echo '<table class="table table-striped table-hover mt-3"><thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Address</th><th>Actions</th></tr></thead><tbody>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['contact']) . '</td>';
            echo '<td>' . htmlspecialchars($row['address']) . '</td>';
            echo '<td><a href="?page=suppliers&edit=' . $row['id'] . '&entity=supplier" class="me-2">Edit</a>';
            echo '<a href="?page=suppliers&delete=' . $row['id'] . '&entity=supplier" onclick="return confirm(\'Delete this supplier?\')">Delete</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        break;

    case 'purchases':
        echo '<h2>Purchases</h2>';
        $purchaseId = $updateData['id'] ?? '';
        $purchaseProduct = $updateData['product_id'] ?? '';
        $purchaseSupplier = $updateData['supplier_id'] ?? '';
        $purchaseQty = $updateData['quantity'] ?? '';
        $purchaseDate = $updateData['purchase_date'] ?? date('Y-m-d');
        $suppliersList = getSuppliers($connection);
        $productsList = getProducts($connection);
        echo '<form method="post" action="" enctype="multipart/form-data" class="row g-2">';
        echo '<input type="hidden" name="entity" value="purchase">';
        echo '<input type="hidden" name="id" value="' . $purchaseId . '">';
        // Product dropdown
        echo '<div class="col-md-3"><select name="product_id" class="form-select">';
        foreach ($productsList as $p) {
            $selected = ($p['id'] == $purchaseProduct) ? 'selected' : '';
            echo '<option value="' . $p['id'] . '" ' . $selected . '>' . htmlspecialchars($p['name']) . '</option>';
        }
        echo '</select></div>';
        // Supplier dropdown
        echo '<div class="col-md-3"><select name="supplier_id" class="form-select">';
        foreach ($suppliersList as $s) {
            $selected = ($s['id'] == $purchaseSupplier) ? 'selected' : '';
            echo '<option value="' . $s['id'] . '" ' . $selected . '>' . htmlspecialchars($s['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-md-2"><input class="form-control" type="number" name="quantity" placeholder="Quantity" value="' . htmlspecialchars($purchaseQty) . '" required></div>';
        echo '<div class="col-md-2"><input class="form-control" type="date" name="date" value="' . htmlspecialchars($purchaseDate) . '" required></div>';
        echo '<div class="col-md-2"><input class="form-control" type="file" name="attachment" accept="application/pdf,image/*"></div>';
        echo '<div class="col-md-12 mt-2"><button type="submit" class="btn btn-primary">' . ($purchaseId ? 'Update' : 'Add') . '</button></div>';
        echo '</form>';
        $res = mysqli_query($connection, 'SELECT purchases.*, products.name AS product_name, suppliers.name AS supplier_name FROM purchases JOIN products ON purchases.product_id = products.id JOIN suppliers ON purchases.supplier_id = suppliers.id ORDER BY purchases.id DESC');
        echo '<table class="table table-striped table-hover mt-3"><thead><tr><th>ID</th><th>Product</th><th>Supplier</th><th>Quantity</th><th>Date</th><th>Attachment</th><th>Actions</th></tr></thead><tbody>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['supplier_name']) . '</td>';
            echo '<td>' . $row['quantity'] . '</td>';
            echo '<td>' . $row['purchase_date'] . '</td>';
            echo '<td>';
            if (!empty($row['attachment_path'])) {
                echo '<a href="' . htmlspecialchars($row['attachment_path']) . '" target="_blank">View</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td><a href="?page=purchases&edit=' . $row['id'] . '&entity=purchase" class="me-2">Edit</a>';
            echo '<a href="?page=purchases&delete=' . $row['id'] . '&entity=purchase" onclick="return confirm(\'Delete this purchase?\')">Delete</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        break;

    case 'orders':
        echo '<h2>Orders</h2>';
        $orderId = $updateData['id'] ?? '';
        $orderProduct = $updateData['product_id'] ?? '';
        $orderQty = $updateData['quantity'] ?? '';
        $orderDate = $updateData['order_date'] ?? date('Y-m-d');
        $orderStatus = $updateData['status'] ?? 'pending';
        $productsList = getProducts($connection);
        echo '<form method="post" action="" enctype="multipart/form-data" class="row g-2">';
        echo '<input type="hidden" name="entity" value="order">';
        echo '<input type="hidden" name="id" value="' . $orderId . '">';
        echo '<div class="col-md-3"><select name="product_id" class="form-select">';
        foreach ($productsList as $p) {
            $selected = ($p['id'] == $orderProduct) ? 'selected' : '';
            echo '<option value="' . $p['id'] . '" ' . $selected . '>' . htmlspecialchars($p['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-md-2"><input class="form-control" type="number" name="quantity" placeholder="Quantity" value="' . htmlspecialchars($orderQty) . '" required></div>';
        echo '<div class="col-md-2"><input class="form-control" type="date" name="date" value="' . htmlspecialchars($orderDate) . '" required></div>';
        echo '<div class="col-md-2"><select name="status" class="form-select">';
        $statuses = ['pending', 'completed', 'cancelled'];
        foreach ($statuses as $statusOption) {
            $selected = ($statusOption === $orderStatus) ? 'selected' : '';
            echo '<option value="' . $statusOption . '" ' . $selected . '>' . ucfirst($statusOption) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-md-3"><input class="form-control" type="file" name="attachment" accept="application/pdf,image/*"></div>';
        echo '<div class="col-md-12 mt-2"><button type="submit" class="btn btn-primary">' . ($orderId ? 'Update' : 'Add') . '</button></div>';
        echo '</form>';
        $res = mysqli_query($connection, 'SELECT orders.*, products.name AS product_name FROM orders JOIN products ON orders.product_id = products.id ORDER BY orders.id DESC');
        echo '<table class="table table-striped table-hover mt-3"><thead><tr><th>ID</th><th>Product</th><th>Quantity</th><th>Date</th><th>Status</th><th>Attachment</th><th>Actions</th></tr></thead><tbody>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
            echo '<td>' . $row['quantity'] . '</td>';
            echo '<td>' . $row['order_date'] . '</td>';
            echo '<td>' . htmlspecialchars($row['status']) . '</td>';
            echo '<td>';
            if (!empty($row['attachment_path'])) {
                echo '<a href="' . htmlspecialchars($row['attachment_path']) . '" target="_blank">View</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td><a href="?page=orders&edit=' . $row['id'] . '&entity=order" class="me-2">Edit</a>';
            echo '<a href="?page=orders&delete=' . $row['id'] . '&entity=order" onclick="return confirm(\'Delete this order?\')">Delete</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        break;

    case 'products':
    default:
        echo '<h2>Products</h2>';
        $pid = $updateData['id'] ?? '';
        $pname = $updateData['name'] ?? '';
        $pdesc = $updateData['description'] ?? '';
        $pprice = $updateData['price'] ?? '';
        $pqty = $updateData['quantity'] ?? '';
        $psupplier = $updateData['supplier_id'] ?? '';
        $suppliersList = getSuppliers($connection);
        echo '<form method="post" action="" enctype="multipart/form-data" class="row g-2">';
        echo '<input type="hidden" name="entity" value="product">';
        echo '<input type="hidden" name="id" value="' . $pid . '">';
        echo '<div class="col-md-3"><input class="form-control" type="text" name="name" placeholder="Name" value="' . htmlspecialchars($pname) . '" required></div>';
        echo '<div class="col-md-3"><input class="form-control" type="text" name="description" placeholder="Description" value="' . htmlspecialchars($pdesc) . '"></div>';
        echo '<div class="col-md-2"><input class="form-control" type="number" step="0.01" name="price" placeholder="Price" value="' . htmlspecialchars($pprice) . '" required></div>';
        echo '<div class="col-md-2"><input class="form-control" type="number" name="quantity" placeholder="Quantity" value="' . htmlspecialchars($pqty) . '" required></div>';
        echo '<div class="col-md-2"><select name="supplier_id" class="form-select">';
        foreach ($suppliersList as $s) {
            $selected = ($s['id'] == $psupplier) ? 'selected' : '';
            echo '<option value="' . $s['id'] . '" ' . $selected . '>' . htmlspecialchars($s['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="col-md-3 mt-2"><input class="form-control" type="file" name="image" accept="image/*"></div>';
        echo '<div class="col-md-12 mt-2"><button type="submit" class="btn btn-primary">' . ($pid ? 'Update' : 'Add') . '</button></div>';
        echo '</form>';
        $res = mysqli_query($connection, 'SELECT products.*, suppliers.name AS supplier_name FROM products LEFT JOIN suppliers ON products.supplier_id = suppliers.id ORDER BY products.id DESC');
        echo '<table class="table table-striped table-hover mt-3"><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Quantity</th><th>Supplier</th><th>Image</th><th>Actions</th></tr></thead><tbody>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['description']) . '</td>';
            echo '<td>' . number_format($row['price'], 2) . '</td>';
            echo '<td>' . $row['quantity'] . '</td>';
            echo '<td>' . htmlspecialchars($row['supplier_name']) . '</td>';
            echo '<td>';
            if (!empty($row['image_path'])) {
                echo '<img src="' . htmlspecialchars($row['image_path']) . '" alt="Image" style="max-width:80px;">';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td><a href="?page=products&edit=' . $row['id'] . '&entity=product" class="me-2">Edit</a>';
            echo '<a href="?page=products&delete=' . $row['id'] . '&entity=product" onclick="return confirm(\'Delete this product?\')">Delete</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        break;
}
?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>