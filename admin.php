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

    // Handle "Partial Payment" (Split Order)
    if (isset($_POST['partial_pay'])) {
        $orderId = $_POST['order_id'];
        $paidQtys = $_POST['paid_qtys'] ?? [];

        if (!empty($paidQtys) && array_sum($paidQtys) >= 0) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM {$tab_prefix}_orders WHERE id = ?");
                $stmt->execute([$orderId]);
                $origOrder = $stmt->fetch();

                $stmt = $pdo->prepare("SELECT * FROM {$tab_prefix}_order_items WHERE order_id = ?");
                $stmt->execute([$orderId]);
                $allOrderItems = $stmt->fetchAll();

                $needsSplit = false;
                foreach ($allOrderItems as $item) {
                    $pQty = (int)($paidQtys[$item['id']] ?? 0);
                    if ($pQty < (int)$item['quantity']) {
                        $needsSplit = true;
                        break;
                    }
                }

                if ($needsSplit) {
                    $sqlNew = "INSERT INTO {$tab_prefix}_orders (customer_name, address, email, scout_name, payment_mode, total_amount, comments, status) 
                               VALUES (?, ?, ?, ?, ?, 0, ?, 'Pending')";
                    $stmtNew = $pdo->prepare($sqlNew);
                    $stmtNew->execute([
                        $origOrder['customer_name'], $origOrder['address'], $origOrder['email'], 
                        $origOrder['scout_name'], $origOrder['payment_mode'], 
                        "Split from Order #$orderId. " . $origOrder['comments']
                    ]);
                    $newOrderId = $pdo->lastInsertId();

                    foreach ($allOrderItems as $item) {
                        $itemId = $item['id'];
                        $pQty = (int)($paidQtys[$itemId] ?? 0);
                        $origQty = (int)$item['quantity'];

                        if ($pQty <= 0) {
                            $pdo->prepare("UPDATE {$tab_prefix}_order_items SET order_id = ? WHERE id = ?")
                                ->execute([$newOrderId, $itemId]);
                        } elseif ($pQty < $origQty) {
                            $uQty = $origQty - $pQty;
                            $unitPrice = $item['subtotal'] / $origQty;
                            
                            $pdo->prepare("UPDATE {$tab_prefix}_order_items SET quantity = ?, subtotal = ? WHERE id = ?")
                                ->execute([$pQty, $pQty * $unitPrice, $itemId]);
                            
                            $pdo->prepare("INSERT INTO {$tab_prefix}_order_items (order_id, product_name, quantity, subtotal) VALUES (?, ?, ?, ?)")
                                ->execute([$newOrderId, $item['product_name'], $uQty, $uQty * $unitPrice]);
                        }
                    }

                    $pdo->prepare("UPDATE {$tab_prefix}_orders SET status = 'Paid' WHERE id = ?")
                        ->execute([$orderId]);

                    $stmtTotal = $pdo->prepare("UPDATE {$tab_prefix}_orders SET total_amount = (SELECT COALESCE(SUM(subtotal),0) FROM {$tab_prefix}_order_items WHERE order_id = ?) WHERE id = ?");
                    $stmtTotal->execute([$orderId, $orderId]);
                    $stmtTotal->execute([$newOrderId, $newOrderId]);
                } else {
                    // If all items selected, just mark the whole order as paid
                    $pdo->prepare("UPDATE {$tab_prefix}_orders SET status = 'Paid' WHERE id = ?")->execute([$orderId]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to split order: " . $e->getMessage();
            }
        }
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
        <form method="POST">
        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
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
                        <div class="paid-unpaid no-print">
                            <button type="submit" name="mark_unpaid" class="mark-unpaid">Mark Unpaid</button>
                        </div>
                    <?php else: ?>
                        <span class="status-badge status-pending">⏳ PENDING (<?php echo $order['payment_mode']; ?>)</span>
                        <div class="paid-unpaid no-print">
                            <button type="submit" name="mark_paid" class="mark-paid" style="margin-right:5px;">Mark Paid</button>
                            <?php 
                                // Only show Partial Pay if there's more than one line item OR a single item with quantity > 1
                                $checkStmt = $pdo->prepare("SELECT COUNT(*) as item_count, SUM(quantity) as total_qty FROM {$tab_prefix}_order_items WHERE order_id = ?");
                                $checkStmt->execute([$order['id']]);
                                $orderStats = $checkStmt->fetch();
                                if ($orderStats['item_count'] > 1 || ($orderStats['total_qty'] ?? 0) > 1): ?>
                                <button type="submit" name="partial_pay" class="btn-partial" onclick="return togglePartialPay(this)">Partial Pay</button>
                            <?php endif; ?>
                        </div>
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
                        <th>Item Name</th>
                        <th class="right-align">Qty Purchased</th>
                        <?php if ($order['status'] !== 'Paid'): ?>
                            <th class="no-print partial-only right-align" style="width: 100px;">Qty Paid For</th>
                        <?php endif; ?>
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
                            <?php if ($order['status'] !== 'Paid'): ?>
                                <td class="no-print partial-only right-align">
                                    <input type="number" name="paid_qtys[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="0" max="<?php echo $item['quantity']; ?>" class="qty-input" style="width: 50px !important;">
                                </td>
                            <?php endif; ?>
                            <td class="right-align">$<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="total-row">
                Total: $<?php echo number_format($order['total_amount'], 2); ?>
            </div>
        </div>
        </form>
    <?php endforeach; ?>
</div>

<script>
function togglePartialPay(btn) {
    const card = btn.closest('.order-card');
    if (!card.classList.contains('is-partial')) {
        card.classList.add('is-partial');
        btn.textContent = 'Update Payments';

        return false; // Prevent form submission on first click
    }
    return true; // Allow submission on subsequent clicks
}
</script>
</body>
</html>