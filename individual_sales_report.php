<?php
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// 1. Fetch all product costs from inventory (using the max purchase price per product_name)
$costs = $pdo->query("SELECT product_name, MAX(unit_purchase_price) as cost FROM {$tab_prefix}_purchases GROUP BY product_name")->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Fetch sales grouped by scout and product from website orders (excluding Cancelled)
$sql = "SELECT o.scout_name, p.internal_name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as sales
        FROM {$tab_prefix}_order_items oi
        JOIN {$tab_prefix}_orders o ON oi.order_id = o.id
        JOIN {$tab_prefix}_products p ON oi.product_name = p.name
        WHERE o.status != 'Cancelled'
        GROUP BY o.scout_name, p.internal_name
        ORDER BY o.scout_name ASC, sales DESC";
$stmt = $pdo->query($sql);
$raw_data = $stmt->fetchAll();

$scout_data = [];
foreach ($raw_data as $row) {
    $scout = $row['scout_name'];
    $prod = $row['internal_name'];
    $qty = $row['qty'];
    $sales = $row['sales'];
    $cost = $costs[$prod] ?? 0;
    $cogs = $qty * $cost;

    if (!isset($scout_data[$scout])) {
        $scout_data[$scout] = [
            'total_sales' => 0,
            'total_cogs' => 0,
            'products' => []
        ];
    }

    $scout_data[$scout]['total_sales'] += $sales;
    $scout_data[$scout]['total_cogs'] += $cogs;
    $scout_data[$scout]['products'][] = [
        'name' => $prod,
        'qty' => $qty,
        'sales' => $sales,
        'cogs' => $cogs,
        'profit' => $sales - $cogs
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
                        <th class="right-align">Total Sales</th>
                        <th class="right-align">COGS</th>
                        <th class="right-align">Total Profit</th>
                        <th class="right-align">Troop Fund (10%)</th>
                        <th class="right-align">Amount to Scout</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scout_data)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 20px;">No sales data available.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($scout_data as $name => $data): 
                        $profit = $data['total_sales'] - $data['total_cogs'];
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
                            <td data-label="Total Sales" class="right-align">$<?php echo number_format($data['total_sales'], 2); ?></td>
                            <td data-label="COGS" class="right-align" style="color: #d32f2f;">($<?php echo number_format($data['total_cogs'], 2); ?>)</td>
                            <td data-label="Total Profit" class="right-align">$<?php echo number_format($profit, 2); ?></td>
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
                            <th class="right-align">Qty Sold</th>
                            <th class="right-align">Sales</th>
                            <th class="right-align">COGS</th>
                            <th class="right-align">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['products'] as $p): ?>
                            <tr>
                                <td data-label="Product"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td data-label="Qty Sold" class="right-align"><?php echo $p['qty']; ?></td>
                                <td data-label="Sales" class="right-align">$<?php echo number_format($p['sales'], 2); ?></td>
                                <td data-label="COGS" class="right-align" style="color: #d32f2f;">($<?php echo number_format($p['cogs'], 2); ?>)</td>
                                <td data-label="Profit" class="right-align" style="font-weight:bold;">$<?php echo number_format($p['profit'], 2); ?></td>
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