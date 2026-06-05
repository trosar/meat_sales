<?php
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// 1. Fetch all product costs from inventory (using the max purchase price per product_name)
$costs = $pdo->query("SELECT product_name, MAX(unit_purchase_price) as cost FROM {$tab_prefix}_purchases GROUP BY product_name")->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Fetch sales grouped by scout and product from website orders (excluding Cancelled)
$sql = "SELECT o.scout_name, p.internal_name, 
               SUM(oi.quantity) as total_qty, 
               SUM(oi.subtotal) as total_sales,
               SUM(CASE WHEN o.status = 'Paid' THEN oi.quantity ELSE 0 END) as paid_qty,
               SUM(CASE WHEN o.status = 'Paid' THEN oi.subtotal ELSE 0 END) as paid_sales
        FROM {$tab_prefix}_order_items oi
        JOIN {$tab_prefix}_orders o ON oi.order_id = o.id
        JOIN {$tab_prefix}_products p ON oi.product_name = p.name
        WHERE o.status != 'Cancelled'
        GROUP BY o.scout_name, p.internal_name
        ORDER BY o.scout_name ASC, total_sales DESC";
$stmt = $pdo->query($sql);
$raw_data = $stmt->fetchAll();

$scout_data = [];
foreach ($raw_data as $row) {
    $scout = $row['scout_name'];
    $prod = $row['internal_name'];
    $cost = $costs[$prod] ?? 0;
    $paid_cogs = $row['paid_qty'] * $cost;

    if (!isset($scout_data[$scout])) {
        $scout_data[$scout] = [
            'total_sales' => 0,
            'paid_sales' => 0,
            'paid_cogs' => 0,
            'products' => []
        ];
    }

    $scout_data[$scout]['total_sales'] += $row['total_sales'];
    $scout_data[$scout]['paid_sales'] += $row['paid_sales'];
    $scout_data[$scout]['paid_cogs'] += $paid_cogs;
    $scout_data[$scout]['products'][] = [
        'name' => $prod,
        'total_qty' => $row['total_qty'],
        'total_sales' => $row['total_sales'],
        'paid_sales' => $row['paid_sales'],
        'paid_cogs' => $paid_cogs,
        'paid_profit' => $row['paid_sales'] - $paid_cogs
    ];
}
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Individual Scout Sales Report'; include 'header-html.php'; ?>

<div class="main-container">
    <?php 
        $nav_no_print = true; 
        include 'menu.php'; 
    ?>

    <div class="order-card">
        <h2 class="headings">Summary by Scout</h2>
        <div style="overflow-x: auto;">
            <table class="stack-mobile">
                <thead>
                    <tr>
                        <th>Scout Name</th>
                        <th class="right-align">Sales (All)</th>
                        <th class="right-align">Sales (Paid)</th>
                        <th class="right-align">Paid COGS</th>
                        <th class="right-align">Paid Profit</th>
                        <th class="right-align">Troop Fund (10%)</th>
                        <th class="right-align">Amount to Scout</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scout_data)): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 20px;">No sales data available.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($scout_data as $name => $data): 
                        $profit = $data['paid_sales'] - $data['paid_cogs'];
                        $troop = $profit * 0.10;
                        $scout_amt = $profit * 0.90;
                    ?>
                        <tr>
                            <td data-label="Scout Name">
                                <strong>
                                    <a href="#scout-<?php echo md5($name); ?>">
                                        <?php echo htmlspecialchars($name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td data-label="Sales (All)" class="right-align">$<?php echo number_format($data['total_sales'], 2); ?></td>
                            <td data-label="Sales (Paid)" class="right-align">$<?php echo number_format($data['paid_sales'], 2); ?></td>
                            <td data-label="Paid COGS" class="right-align" style="color: #d32f2f;">($<?php echo number_format($data['paid_cogs'], 2); ?>)</td>
                            <td data-label="Paid Profit" class="right-align">$<?php echo number_format($profit, 2); ?></td>
                            <td data-label="Troop Fund" class="right-align">$<?php echo number_format($troop, 2); ?></td>
                            <td data-label="Amount to Scout" class="right-align" style="font-weight:bold; color: #2e7d32;">$<?php echo number_format($scout_amt, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="headings">Product Breakdown by Scout</h2>
    <?php foreach ($scout_data as $name => $data): ?>
        <div class="order-card" id="scout-<?php echo md5($name); ?>">
            <h3 style="color: var(--primary-color); border-bottom: 1px solid #eee; padding-bottom: 5px;">👤 <?php echo htmlspecialchars($name); ?></h3>
            <div style="overflow-x: auto;">
                <table class="item-table stack-mobile">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="right-align">Qty (All)</th>
                            <th class="right-align">Sales (All)</th>
                            <th class="right-align">Sales (Paid)</th>
                            <th class="right-align">Paid COGS</th>
                            <th class="right-align">Paid Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['products'] as $p): ?>
                            <tr>
                                <td data-label="Product"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td data-label="Qty (All)" class="right-align"><?php echo $p['total_qty']; ?></td>
                                <td data-label="Sales (All)" class="right-align">$<?php echo number_format($p['total_sales'], 2); ?></td>
                                <td data-label="Sales (Paid)" class="right-align">$<?php echo number_format($p['paid_sales'], 2); ?></td>
                                <td data-label="Paid COGS" class="right-align" style="color: #d32f2f;">($<?php echo number_format($p['paid_cogs'], 2); ?>)</td>
                                <td data-label="Paid Profit" class="right-align" style="font-weight:bold;">$<?php echo number_format($p['paid_profit'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>