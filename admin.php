<?php
// 1. Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('America/Los_Angeles');

// 2. Database & Session (via db.php - this loads your .env automatically)
require_once 'db.php';

// 3. Handle Logout
if (isset($_GET['logout'])) {
    // Completely clear the session data
    $_SESSION = array();
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }    
    header("Location: admin.php"); 
    exit;
}

// 4. Handle Login using Environment Variable
if (isset($_POST['password'])) {
    $admin_password = getenv('ADMIN_PASS');
    
    if ($admin_password && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Incorrect password!";
    }
}

// 5. CSV Export Logic (Must be before any HTML)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    
    // Download Order Info (The Customer/Order List)
    if (isset($_POST['download_orders_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="order_info_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order ID', 'Date', 'Customer', 'Address', 'Email', 'Scout', 'Payment', 'Total', 'Status', 'Comments']);
        $stmt = $pdo->query("SELECT * FROM {$tab_prefix}_orders where status != 'Cancelled' ORDER BY order_date DESC");
        $grandTotal = 0;
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'], 
                formatLocalDate($row['order_date']), 
                $row['customer_name'], 
                $row['address'], 
                $row['email'], 
                $row['scout_name'], 
                $row['payment_mode'], 
                $row['total_amount'], 
                $row['status'],
                $row['comments']
            ]);
            $grandTotal += $row['total_amount'];
        }
        fputcsv($output, ['==', '==', '==', '==', '==', '==', '==', number_format($grandTotal, 2), '==', 'Total Sales']);
        fclose($output);
        exit;
    }

    // Download Product Orders (The Shopping List)
    if (isset($_POST['download_products_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="product_totals_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Status', 'Product Name', 'Total Quantity Ordered']);
        $sql = "SELECT oi.product_name, o.status, SUM(oi.quantity) as total_qty FROM {$tab_prefix}_order_items oi LEFT JOIN {$tab_prefix}_orders o ON oi.order_id = o.id GROUP BY status, product_name ORDER BY status, product_name";
        $stmt = $pdo->query($sql);
        $grandTotal = 0;
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['status'], $row['product_name'], $row['total_qty']]);
            $grandTotal += $row['total_qty'];
        }
        fputcsv($output, ['== TOTAL ==', '== All Products ==', $grandTotal]);
        fclose($output);
        exit;
    }

    // Handle "Mark as Paid"
    if (isset($_POST['mark_paid'])) {
        $stmt = $pdo->prepare("UPDATE {$tab_prefix}_orders SET status = 'Paid' WHERE id = ?");
        $stmt->execute([$_POST['order_id']]);
        header("Location: admin.php");
        exit;
    }

    // Handle "Mark as Unpaid" (Pending)
    if (isset($_POST['mark_unpaid'])) {
        $stmt = $pdo->prepare("UPDATE {$tab_prefix}_orders SET status = 'Pending' WHERE id = ?");
        $stmt->execute([$_POST['order_id']]);
        header("Location: admin.php");
        exit;
    }    
}

// 6. Show Login Page if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true): ?>

    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <link rel="stylesheet" href="styles.css?v=<?php echo $styles_version; ?>">
    </head>
    <body class="small-body">
        <div class="login-box">
            <h2>Scout Fundraiser Admin</h2>
            <?php if (isset($error)) echo "<p class='error-message'>$error</p>"; ?>
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="password" placeholder="Enter Password" required><br>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-confirm">Login</button>
                    <a href="index.php" class="btn btn-back">Back Home</a>
                </div>
            </form>
        </div>
    </body>
    </html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Order Management'; include 'header-html.php'; ?>

<div class="main-container">
    <?php include 'menu.php'; ?>

    <?php
    $orders = $pdo->query("SELECT * FROM {$tab_prefix}_orders where status != 'Cancelled'ORDER BY order_date DESC")->fetchAll();
    foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <strong>Order #<?php echo $order['id']; ?></strong><br>
                    <small class="text-muted">
                        <?php 
                            echo formatLocalDate($order['order_date']);
                        ?>
                    </small>
                </div>
                <div>
                    <?php if ($order['status'] === 'Paid'): ?>
                        <span class="status-badge status-paid">✅ PAID (<?php echo $order['payment_mode']; ?>)</span>
                        <form method="POST" class="paid-unpaid">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="mark_unpaid" class="mark-unpaid">Mark Unpaid</button>
                        </form>
                    <?php else: ?>
                        <span class="status-badge status-pending">⏳ PENDING (<?php echo $order['payment_mode']; ?>)</span>
                        <form method="POST" class="paid-unpaid">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="mark_paid" class="mark-paid">Mark Paid</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <p class="tabbed"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>
            <p class="tabbed"><strong>Address:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
            <p class="tabbed"><strong>Scout Name:</strong> <?php echo htmlspecialchars($order['scout_name']); ?></p>
            <?php if (!empty($order['comments'])): ?>
                <p class="order-comments">
                    <strong>Comments:</strong> <?php echo htmlspecialchars($order['comments']); ?>
                </p>
            <?php endif; ?>            

            <table class="item-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th class="right-align">Qty</th>
                        <th class="right-align">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $itemStmt = $pdo->prepare("SELECT * FROM {$tab_prefix}_order_items WHERE order_id = ?");
                    $itemStmt->execute([$order['id']]);
                    while ($item = $itemStmt->fetch()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="right-align"><?php echo $item['quantity']; ?></td>
                            <td class="right-align">$<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="total-row">
                Total: $<?php echo number_format($order['total_amount'], 2); ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>