<?php
/*
 * Login page for the Mini Inventory System.
 *
 * Presents a login form and verifies the provided credentials against
 * the `users` table.  Passwords are stored as SHA-256 hashes in the
 * database.  After successful login a session is started and the user
 * is redirected to index.php.
 */

session_start();

// If user is already authenticated redirect to main page
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Connect to the database
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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    // Compute SHA-256 hash of the input password
    $passwordHash = hash('sha256', $password);
    $stmt = mysqli_prepare($connection, 'SELECT id, username, role FROM users WHERE username = ? AND password = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $username, $passwordHash);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $userRow = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if ($userRow) {
        $_SESSION['user_id'] = $userRow['id'];
        $_SESSION['username'] = $userRow['username'];
        $_SESSION['role'] = $userRow['role'];
        header('Location: index.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login - Mini Inventory System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card shadow-sm" style="width: 360px;">
        <div class="card-body">
            <h4 class="card-title mb-3">Login</h4>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>