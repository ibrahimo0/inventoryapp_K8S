<?php
/*
 * Mini inventory management system
 *
 * This PHP script replaces the original todo list with a richer CRUD
 * application for managing products, suppliers, purchases and orders.
 * It keeps the same MySQL connection parameters (host `localhost`, user
 * `root`, password blank, database `sqldb`) so that it works with the
 * existing Kubernetes ConfigMap/Secret used in the tutorial.  The UI is
 * intentionally simple: a horizontal menu lets you choose which entity
 * to manage.  Within each section you can create new records, update
 * existing ones and delete them.
 */

// Connect to the MySQL database defined by environment variables or fall back to defaults.
// The code first tries to read DB_* environment variables (used in the original
// tutorial for the PHP todo app) and then falls back to MYSQL_* variables
// (standard for the MySQL container). If none are provided, it uses
// localhost/root/(empty password)/sqldb.
$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'sqldb';
$user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'root';
$pass = getenv('DB_PASSWORD');
if ($pass === false) {
    // Try MYSQL_PASSWORD or MYSQL_ROOT_PASSWORD as a fallback
    $pass = getenv('MYSQL_PASSWORD');
    if ($pass === false) {
        $pass = getenv('MYSQL_ROOT_PASSWORD') ?: '';
    }
}
$connection = mysqli_connect($host, $user, $pass, $db);
if (!$connection) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Determine which page (entity) to display
$page = isset($_GET['page']) ? $_GET['page'] : 'products';

// Handle form submissions for different entities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Products
    if (isset($_POST['entity']) && $_POST['entity'] === 'product') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        $supplier_id = intval($_POST['supplier_id']);
        if ($id === '') {
            // Insert new product
            if ($name !== '') {
                $stmt = mysqli_prepare($connection, 'INSERT INTO products(name, description, price, quantity, supplier_id) VALUES (?, ?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssdii', $name, $description, $price, $quantity, $supplier_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                echo "<script>alert('Product added');</script>";
            } else {
                echo "<script>alert('Please enter a product name');</script>";
            }
        } else {
            // Update existing product
            $stmt = mysqli_prepare($connection, 'UPDATE products SET name = ?, description = ?, price = ?, quantity = ?, supplier_id = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssdiii', $name, $description, $price, $quantity, $supplier_id, $id);
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
        if ($id === '') {
            $stmt = mysqli_prepare($connection, 'INSERT INTO purchases(product_id, supplier_id, quantity, purchase_date) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iiis', $product_id, $supplier_id, $quantity, $date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Purchase added');</script>";
        } else {
            $stmt = mysqli_prepare($connection, 'UPDATE purchases SET product_id = ?, supplier_id = ?, quantity = ?, purchase_date = ? WHERE id = ?');
            // Bind as: int, int, int, string, int
            mysqli_stmt_bind_param($stmt, 'iiisi', $product_id, $supplier_id, $quantity, $date, $id);
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
        if ($id === '') {
            $stmt = mysqli_prepare($connection, 'INSERT INTO orders(product_id, quantity, order_date, status) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'iiss', $product_id, $quantity, $date, $status);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Order added');</script>";
        } else {
            $stmt = mysqli_prepare($connection, 'UPDATE orders SET product_id = ?, quantity = ?, order_date = ?, status = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'iissi', $product_id, $quantity, $date, $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<script>alert('Order updated');</script>";
        }
    }
}

// Handle deletions via GET
if (isset($_GET['delete'])) {
    $entity = $_GET['entity'] ?? '';
    $id = intval($_GET['delete']);
    if ($entity === 'product') {
        mysqli_query($connection, 'DELETE FROM products WHERE id = ' . $id);
        echo "<script>alert('Product deleted');</script>";
    }
    if ($entity === 'supplier') {
        mysqli_query($connection, 'DELETE FROM suppliers WHERE id = ' . $id);
        echo "<script>alert('Supplier deleted');</script>";
    }
    if ($entity === 'purchase') {
        mysqli_query($connection, 'DELETE FROM purchases WHERE id = ' . $id);
        echo "<script>alert('Purchase deleted');</script>";
    }
    if ($entity === 'order') {
        mysqli_query($connection, 'DELETE FROM orders WHERE id = ' . $id);
        echo "<script>alert('Order deleted');</script>";
    }
}

// Fetch lists of suppliers and products for dropdowns
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

// Determine if update mode
$updateData = null;
if (isset($_GET['edit']) && isset($_GET['entity'])) {
    $updateData = getRecord($connection, $_GET['entity'] . 's', $_GET['edit']);
    // For purchases and orders the table names differ slightly
    if ($_GET['entity'] === 'purchase') {
        $updateData = getRecord($connection, 'purchases', $_GET['edit']);
    }
    if ($_GET['entity'] === 'order') {
        $updateData = getRecord($connection, 'orders', $_GET['edit']);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Mini Inventory System</title>
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
        }
        .nav {
            background-color: #333;
            padding: 10px;
            text-align: center;
        }
        .nav a {
            color: #fff;
            margin: 0 15px;
            text-decoration: none;
            font-weight: bold;
        }
        .nav a.active {
            text-decoration: underline;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        form.inline {
            display: inline;
        }
    </style>
</head>
<body>
<div class="nav">
    <a href="?page=products" class="<?php echo $page === 'products' ? 'active' : ''; ?>">Products</a>
    <a href="?page=suppliers" class="<?php echo $page === 'suppliers' ? 'active' : ''; ?>">Suppliers</a>
    <a href="?page=purchases" class="<?php echo $page === 'purchases' ? 'active' : ''; ?>">Purchases</a>
    <a href="?page=orders" class="<?php echo $page === 'orders' ? 'active' : ''; ?>">Orders</a>
</div>

<div class="container">
<?php
// Show different page content based on selected entity
switch ($page) {
    case 'suppliers':
        echo '<h2>Suppliers</h2>';
        // Form for create/update supplier
        $sid = $updateData && isset($updateData['id']) ? $updateData['id'] : '';
        $sname = $updateData && isset($updateData['name']) ? $updateData['name'] : '';
        $scontact = $updateData && isset($updateData['contact']) ? $updateData['contact'] : '';
        $saddress = $updateData && isset($updateData['address']) ? $updateData['address'] : '';
        echo '<form method="post" action="" >';
        echo '<input type="hidden" name="entity" value="supplier">';
        echo '<input type="hidden" name="id" value="' . $sid . '">';
        echo '<label>Name: <input type="text" name="name" value="' . htmlspecialchars($sname) . '" required></label> ';
        echo '<label>Contact: <input type="text" name="contact" value="' . htmlspecialchars($scontact) . '"></label> ';
        echo '<label>Address: <input type="text" name="address" value="' . htmlspecialchars($saddress) . '"></label> ';
        echo '<input type="submit" value="' . ($sid ? 'Update' : 'Add') . '">';
        echo '</form>';
        // List suppliers
        $res = mysqli_query($connection, 'SELECT * FROM suppliers ORDER BY id DESC');
        echo '<table><tr><th>ID</th><th>Name</th><th>Contact</th><th>Address</th><th>Actions</th></tr>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['contact']) . '</td>';
            echo '<td>' . htmlspecialchars($row['address']) . '</td>';
            echo '<td>';
            echo '<a href="?page=suppliers&edit=' . $row['id'] . '&entity=supplier">Edit</a> | ';
            echo '<a href="?page=suppliers&delete=' . $row['id'] . '&entity=supplier" onclick="return confirm(\'Delete this supplier?\')">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        break;

    case 'purchases':
        echo '<h2>Purchases</h2>';
        $purchaseId = $updateData && isset($updateData['id']) ? $updateData['id'] : '';
        $purchaseProduct = $updateData && isset($updateData['product_id']) ? $updateData['product_id'] : '';
        $purchaseSupplier = $updateData && isset($updateData['supplier_id']) ? $updateData['supplier_id'] : '';
        $purchaseQty = $updateData && isset($updateData['quantity']) ? $updateData['quantity'] : '';
        $purchaseDate = $updateData && isset($updateData['purchase_date']) ? $updateData['purchase_date'] : date('Y-m-d');
        $suppliersList = getSuppliers($connection);
        $productsList = getProducts($connection);
        echo '<form method="post" action="" >';
        echo '<input type="hidden" name="entity" value="purchase">';
        echo '<input type="hidden" name="id" value="' . $purchaseId . '">';
        // product dropdown
        echo '<label>Product: <select name="product_id">';
        foreach ($productsList as $p) {
            $selected = ($p['id'] == $purchaseProduct) ? 'selected' : '';
            echo '<option value="' . $p['id'] . '" ' . $selected . '>' . htmlspecialchars($p['name']) . '</option>';
        }
        echo '</select></label> ';
        // supplier dropdown
        echo '<label>Supplier: <select name="supplier_id">';
        foreach ($suppliersList as $s) {
            $selected = ($s['id'] == $purchaseSupplier) ? 'selected' : '';
            echo '<option value="' . $s['id'] . '" ' . $selected . '>' . htmlspecialchars($s['name']) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>Quantity: <input type="number" name="quantity" value="' . htmlspecialchars($purchaseQty) . '" required></label> ';
        echo '<label>Date: <input type="date" name="date" value="' . htmlspecialchars($purchaseDate) . '" required></label> ';
        echo '<input type="submit" value="' . ($purchaseId ? 'Update' : 'Add') . '">';
        echo '</form>';
        // List purchases
        $res = mysqli_query($connection, 'SELECT purchases.*, products.name AS product_name, suppliers.name AS supplier_name FROM purchases JOIN products ON purchases.product_id = products.id JOIN suppliers ON purchases.supplier_id = suppliers.id ORDER BY purchases.id DESC');
        echo '<table><tr><th>ID</th><th>Product</th><th>Supplier</th><th>Quantity</th><th>Date</th><th>Actions</th></tr>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['supplier_name']) . '</td>';
            echo '<td>' . $row['quantity'] . '</td>';
            echo '<td>' . $row['purchase_date'] . '</td>';
            echo '<td>';
            echo '<a href="?page=purchases&edit=' . $row['id'] . '&entity=purchase">Edit</a> | ';
            echo '<a href="?page=purchases&delete=' . $row['id'] . '&entity=purchase" onclick="return confirm(\'Delete this purchase?\')">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        break;

    case 'orders':
        echo '<h2>Orders</h2>';
        $orderId = $updateData && isset($updateData['id']) ? $updateData['id'] : '';
        $orderProduct = $updateData && isset($updateData['product_id']) ? $updateData['product_id'] : '';
        $orderQty = $updateData && isset($updateData['quantity']) ? $updateData['quantity'] : '';
        $orderDate = $updateData && isset($updateData['order_date']) ? $updateData['order_date'] : date('Y-m-d');
        $orderStatus = $updateData && isset($updateData['status']) ? $updateData['status'] : 'pending';
        $productsList = getProducts($connection);
        echo '<form method="post" action="" >';
        echo '<input type="hidden" name="entity" value="order">';
        echo '<input type="hidden" name="id" value="' . $orderId . '">';
        echo '<label>Product: <select name="product_id">';
        foreach ($productsList as $p) {
            $selected = ($p['id'] == $orderProduct) ? 'selected' : '';
            echo '<option value="' . $p['id'] . '" ' . $selected . '>' . htmlspecialchars($p['name']) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>Quantity: <input type="number" name="quantity" value="' . htmlspecialchars($orderQty) . '" required></label> ';
        echo '<label>Date: <input type="date" name="date" value="' . htmlspecialchars($orderDate) . '" required></label> ';
        echo '<label>Status: <select name="status">';
        $statuses = ['pending', 'completed', 'cancelled'];
        foreach ($statuses as $statusOption) {
            $selected = ($statusOption === $orderStatus) ? 'selected' : '';
            echo '<option value="' . $statusOption . '" ' . $selected . '>' . ucfirst($statusOption) . '</option>';
        }
        echo '</select></label> ';
        echo '<input type="submit" value="' . ($orderId ? 'Update' : 'Add') . '">';
        echo '</form>';
        // List orders
        $res = mysqli_query($connection, 'SELECT orders.*, products.name AS product_name FROM orders JOIN products ON orders.product_id = products.id ORDER BY orders.id DESC');
        echo '<table><tr><th>ID</th><th>Product</th><th>Quantity</th><th>Date</th><th>Status</th><th>Actions</th></tr>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
            echo '<td>' . $row['quantity'] . '</td>';
            echo '<td>' . $row['order_date'] . '</td>';
            echo '<td>' . htmlspecialchars($row['status']) . '</td>';
            echo '<td>';
            echo '<a href="?page=orders&edit=' . $row['id'] . '&entity=order">Edit</a> | ';
            echo '<a href="?page=orders&delete=' . $row['id'] . '&entity=order" onclick="return confirm(\'Delete this order?\')">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        break;

    case 'products':
    default:
        echo '<h2>Products</h2>';
        $pid = $updateData && isset($updateData['id']) ? $updateData['id'] : '';
        $pname = $updateData && isset($updateData['name']) ? $updateData['name'] : '';
        $pdesc = $updateData && isset($updateData['description']) ? $updateData['description'] : '';
        $pprice = $updateData && isset($updateData['price']) ? $updateData['price'] : '';
        $pqty = $updateData && isset($updateData['quantity']) ? $updateData['quantity'] : '';
        $psupplier = $updateData && isset($updateData['supplier_id']) ? $updateData['supplier_id'] : '';
        $suppliersList = getSuppliers($connection);
        echo '<form method="post" action="" >';
        echo '<input type="hidden" name="entity" value="product">';
        echo '<input type="hidden" name="id" value="' . $pid . '">';
        echo '<label>Name: <input type="text" name="name" value="' . htmlspecialchars($pname) . '" required></label> ';
        echo '<label>Description: <input type="text" name="description" value="' . htmlspecialchars($pdesc) . '"></label> ';
        echo '<label>Price: <input type="number" step="0.01" name="price" value="' . htmlspecialchars($pprice) . '" required></label> ';
        echo '<label>Quantity: <input type="number" name="quantity" value="' . htmlspecialchars($pqty) . '" required></label> ';
        echo '<label>Supplier: <select name="supplier_id">';
        foreach ($suppliersList as $s) {
            $selected = ($s['id'] == $psupplier) ? 'selected' : '';
            echo '<option value="' . $s['id'] . '" ' . $selected . '>' . htmlspecialchars($s['name']) . '</option>';
        }
        echo '</select></label> ';
        echo '<input type="submit" value="' . ($pid ? 'Update' : 'Add') . '">';
        echo '</form>';
        // List products
        $res = mysqli_query($connection, 'SELECT products.*, suppliers.name AS supplier_name FROM products LEFT JOIN suppliers ON products.supplier_id = suppliers.id ORDER BY products.id DESC');
        echo '<table><tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Quantity</th><th>Supplier</th><th>Actions</th></tr>';
        while ($row = mysqli_fetch_assoc($res)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['description']) . '</td>';
            echo '<td>' . number_format($row['price'], 2) . '</td>';
            echo '<td>' . $row['quantity'] . '</td>';
            echo '<td>' . htmlspecialchars($row['supplier_name']) . '</td>';
            echo '<td>';
            echo '<a href="?page=products&edit=' . $row['id'] . '&entity=product">Edit</a> | ';
            echo '<a href="?page=products&delete=' . $row['id'] . '&entity=product" onclick="return confirm(\'Delete this product?\')">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        break;
}
?>
</div>
</body>
</html>