<?php
require_once 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// 1. Get Totals from Shift Sales
$salesData = $pdo->query("SELECT SUM(total_sales) as total_sales, SUM(total_donations) as total_donations FROM {$tab_prefix}_shift_sales")->fetch();
$totalSales = $salesData['total_sales'] ?? 0;
$totalDonations = $salesData['total_donations'] ?? 0;
$grandTotal = $totalSales + $totalDonations;

// Calculate Cost of Goods Sold (COGS)
// We take the max purchase price per product as the unit cost
$cogsData = $pdo->query("
    SELECT SUM(s.qty_sold * p.cost) as total_cogs 
    FROM {$tab_prefix}_shift_sales s 
    LEFT JOIN (
        SELECT product_name, MAX(unit_purchase_price) as cost 
        FROM {$tab_prefix}_purchases 
        GROUP BY product_name
    ) p ON s.product_name = p.product_name
")->fetch();
$totalCOGS = $cogsData['total_cogs'] ?? 0;
$netProfit = $grandTotal - $totalCOGS;

// 2. Product-wise Totals
$productTotals = $pdo->query("SELECT product_name, SUM(total_sales) as total FROM {$tab_prefix}_shift_sales GROUP BY product_name ORDER BY total DESC")->fetchAll();

// 3. Scout Shift Totals
$shiftData = $pdo->query("SELECT SUM(shifts) as total_shifts FROM {$tab_prefix}_scout_shifts")->fetch();
$totalShifts = $shiftData['total_shifts'] ?? 0;

// 4. Financial Calculations
$troopAmount = $netProfit * 0.10;
$scoutPortionTotal = $netProfit * 0.90;
$payoutPerShift = ($totalShifts > 0) ? $scoutPortionTotal / $totalShifts : 0;

// 5. Scout-wise Earnings
$scouts = $pdo->query("SELECT scout_name, shifts FROM {$tab_prefix}_scout_shifts")->fetchAll();
foreach ($scouts as &$s) {
    $s['earnings'] = $s['shifts'] * $payoutPerShift;
}
unset($s);

// Sort scouts by earnings highest to lowest
usort($scouts, function($a, $b) {
    return $b['earnings'] <=> $a['earnings'];
});
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Shift Sales Report'; include 'header-html.php'; ?>

<div class="main-container">
    <?php 
        $nav_no_print = true; 
        include 'menu.php'; 
    ?>

    <div class="order-card">
        <h2 class="headings">Shift Sales Summary</h2>
        <table class="item-table">
            <tr>
                <td>Total Shift Sales</td>
                <td class="right-align">$<?php echo number_format($totalSales, 2); ?></td>
            </tr>
            <tr>
                <td>Total Donations</td>
                <td class="right-align">$<?php echo number_format($totalDonations, 2); ?></td>
            </tr>
            <tr style="font-weight:bold; border-top: 2px solid #eee;">
                <td>Total Income</td>
                <td class="right-align">$<?php echo number_format($grandTotal, 2); ?></td>
            </tr>
            <tr style="color: #d32f2f;">
                <td>Less: Cost of Goods Sold (COGS)</td>
                <td class="right-align">($<?php echo number_format($totalCOGS, 2); ?>)</td>
            </tr>
            <tr style="font-weight:bold; border-top: 2px solid #eee;">
            <!-- <tr style="font-weight:bold; border-top: 1px solid #eee; background: #fffde7;"> -->
                <td>Total Profit</td>
                <td class="right-align">$<?php echo number_format($netProfit, 2); ?></td>
            </tr>
            <tr style="color: #d32f2f;">
            <!-- <tr style="color: #666;"> -->
                <td>Less: Troop Fund (10% of Profit)</td>
                <td class="right-align">$<?php echo number_format($troopAmount, 2); ?></td>
            </tr>
            <tr style="color: #2e7d32; font-weight:bold; border-top: 2px solid #eee;">
            <!-- <tr style="color: #2e7d32; font-weight:bold;"> -->
                <td>Amount to Scouts</td>
                <td class="right-align">$<?php echo number_format($scoutPortionTotal, 2); ?></td>
            </tr>
            <tr>
                <td>Total Scout Shifts</td>
                <td class="right-align"><?php echo $totalShifts; ?></td>
            </tr>
            <tr style="color: #2e7d32; border-top: 2px solid #eee; font-size: 1.1rem; background: #f9f9f9;">
                <td><strong>Per Shift Payout to Each Scout</strong></td>
                <td class="right-align"><strong>$<?php echo number_format($payoutPerShift, 2); ?></strong></td>
            </tr>
        </table>
    </div>

    <div class="order-card">
        <h2 class="headings">Product-wise Total Shift Sales</h2>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th class="right-align">Total Sales</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productTotals as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td class="right-align">$<?php echo number_format($row['total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="order-card">
        <h2 class="headings">Scout-wise Earnings</h2>
        <table class="stack-mobile">
            <thead>
                <tr>
                    <th>Scout Name</th>
                    <th class="right-align">Shifts</th>
                    <th class="right-align">Total Earning</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scouts as $row): ?>
                    <tr>
                        <td data-label="Scout Name"><?php echo htmlspecialchars($row['scout_name']); ?></td>
                        <td data-label="Shifts" class="right-align"><?php echo $row['shifts']; ?></td>
                        <td data-label="Total Earning" class="right-align" style="font-weight:bold; color: #2e7d32;">
                            $<?php echo number_format($row['earnings'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>