<?php
require_once 'db.php';
date_default_timezone_set('America/Los_Angeles');

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// CSV Export Logic
if (isset($_POST['download_scout_report_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="scout_sales_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Scout', 'Customer', 'Address', 'Product', 'Qty', 'Subtotal', 'Status', 'Payment Mode', 'Order Date', 'Comments']);
    
    $sql = "SELECT o.scout_name, o.customer_name, o.address, oi.product_name, oi.quantity, 
                   oi.subtotal, o.status, o.payment_mode, o.order_date, o.comments
            FROM {$tab_prefix}_order_items oi 
            LEFT JOIN {$tab_prefix}_orders o ON oi.order_id = o.id 
            ORDER BY o.scout_name ASC, o.order_date DESC";
    
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['scout_name'], $row['customer_name'], $row['address'], 
            $row['product_name'], $row['quantity'], $row['subtotal'], 
            $row['status'], $row['payment_mode'], formatLocalDate($row['order_date']), $row['comments']
        ]);
    }
    fclose($output);
    exit;
}

// Leaderboard Query: Get total sales per Scout
$leaderboardSql = "SELECT scout_name, SUM(total_amount) as total_sales, COUNT(id) as order_count 
                  FROM {$tab_prefix}_orders  
                  where status != 'Cancelled'
                  GROUP BY scout_name 
                  ORDER BY total_sales DESC 
                  LIMIT 3"; // Top 3 Scouts
$leaderboard = $pdo->query($leaderboardSql)->fetchAll();

// Total Troop Sales
$stats = $pdo->query("SELECT SUM(total_amount) as total_sum, count(1) order_count FROM {$tab_prefix}_orders where status != 'Cancelled'")->fetch();
$troopTotal = $stats['total_sum'] ?? 0;
$orderCount = $stats['order_count'] ?? 0;
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Scout Sales Report'; include 'header-html.php'; ?>
    <div class="main-container">
    <?php $nav_no_print = true; include 'menu.php'; ?>

    <div class="leaderboard-container no-print">
        <div class="leaderboard-card">
            <h3 style="margin-top:0; color: #673ab7; border-bottom: 2px solid #f3e5f5; padding-bottom: 10px;">
                🏆 Top Sellers (Leaderboard)
            </h3>
            <?php 
            $rank = 1;
            foreach ($leaderboard as $row): 
                $medal = ($rank == 1) ? '🥇' : (($rank == 2) ? '🥈' : (($rank == 3) ? '🥉' : ''));
            ?>
                <div class="leader-row">
                    <span class="rank"><?php echo $rank; ?>.</span>
                    <span class="scout-name"><?php echo $medal . ' ' . htmlspecialchars($row['scout_name']); ?></span>
                    <span style="color: #888; font-size: 0.8rem; margin-right: 15px;"><?php echo $row['order_count']; ?> orders</span>
                    <span class="sales-amount">$<?php echo number_format($row['total_sales'], 2); ?></span>
                </div>
            <?php $rank++; endforeach; ?>
        </div>

        <div class="leaderboard-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
            <h3 style="margin: 0; color: #555;">Total Troop Sales</h3>
            <div style="font-size: 2.5rem; font-weight: bold; color: #2e7d32; margin: 10px 0;">
                $<?php echo number_format($troopTotal, 2); ?>
            </div>
            <p style="color: #888; margin: 0;">(<?php echo $orderCount; ?> orders)</p>
        </div>
    </div>    

    <div class="card">
        <table>
            <tbody>
                <?php
                $sql = "SELECT o.scout_name, o.id as order_id, o.customer_name, o.email, o.address, 
                            o.status, o.payment_mode, o.order_date, o.comments,
                            oi.product_name, oi.quantity, 
                            (oi.subtotal/oi.quantity) as price_per_item, oi.subtotal 
                        FROM {$tab_prefix}_order_items oi 
                        LEFT JOIN {$tab_prefix}_orders o ON oi.order_id = o.id 
                        ORDER BY o.scout_name ASC, o.order_date DESC, o.id ASC";
                $data = $pdo->query($sql)->fetchAll();

                $currentScout = '';
                $currentOrder = '';

                foreach ($data as $row):
                    // --- 1. NEW SCOUT GROUPING ---
                    if ($currentScout !== $row['scout_name']):
                        $currentScout = $row['scout_name'];
                        $currentOrder = ''; // Reset order tracking for new scout
                ?>
                    <tr class="scout-header">
                        <td colspan="5" class="scout-header-tr">
                            👤 <?php echo htmlspecialchars($currentScout); ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php 
                    // --- 2. NEW ORDER SUB-GROUPING ---
                    if ($currentOrder !== $row['order_id']):
                        $currentOrder = $row['order_id'];
                ?>
                    <tr style="background: #f9f9f9; border-top: 1px solid #ddd;">
                        <td colspan="5" style="padding: 10px 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>
                                    <strong>Order #<?php echo $row['order_id']; ?></strong> - 
                                    <?php echo htmlspecialchars($row['customer_name']); ?> 
                                    <small style="color: #666;">(<?php echo htmlspecialchars($row['email']); ?>)</small>
                                </span>
                                <span class="<?php echo ($row['status'] === 'Paid') ? 'status-paid' : 'status-pending'; ?>">
                                    <?php echo $row['status']; ?> (<?php echo $row['payment_mode']; ?>)
                                </span>
                            </div>
                            <div style="font-size: 0.85rem; color: #555; margin-top: 4px;">
                                📍 <?php echo htmlspecialchars($row['address']); ?> | 📅 <?php echo formatLocalDate($row['order_date']); ?>
                            </div>
                            <?php if (!empty($row['comments'])): ?>
                                <div style="font-size: 0.85rem; padding: 5px;">
                                    <strong>💬 Note:</strong> <?php echo htmlspecialchars($row['comments']); ?>
                                </div>
                            <?php endif; ?>                            
                        </td>
                    </tr>
                    <tr style="font-size: 0.8rem; color: #666;">
                        <td style="padding-left: 40px;"><em>Product Name</em></td>
                        <td></td>
                        <td style="text-align:center;"><em>Qty</em></td>
                        <td style="text-align:right;"><em>Price/ea</em></td>
                        <td style="text-align:right; padding-right: 15px;"><em>Subtotal</em></td>
                    </tr>
                <?php endif; ?>

                    <tr>
                        <td style="padding-left: 40px; border-bottom: none;"><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td style="border-bottom: none;"></td>
                        <td style="text-align:center; border-bottom: none;"><?php echo $row['quantity']; ?></td>
                        <td style="text-align:right; border-bottom: none;">$<?php echo number_format($row['price_per_item'], 2); ?></td>
                        <td style="text-align:right; padding-right: 15px; border-bottom: none;">$<?php echo number_format($row['subtotal'], 2); ?></td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function downloadPrintableHTML() {
    // 1. Get all the CSS from the current page
    const styles = Array.from(document.styleSheets)
        .map(styleSheet => {
            try {
                return Array.from(styleSheet.cssRules)
                    .map(rule => rule.cssText)
                    .join('');
            } catch (e) {
                return ''; // Handle cross-origin issues if any
            }
        })
        .join('');

    // 2. Get the main content (the .main-container div)
    const content = document.querySelector('.main-container').innerHTML;

    // 3. Construct a full HTML document string
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Troop 60 - Scout Sales Report</title>
            <style>
                ${styles}
                /* Ensure it opens in "Print Mode" appearance */
                body { background: white !important; }
                .no-print { display: none !important; }
            </style>
        </head>
        <body>
            <div class="main-container">
                ${content}
            </div>
            <script>
                // Optional: Auto-open print dialog when they open the file
                // window.print();
            <\/script>
        </body>
        </html>
    `;

    // 4. Create the download link
    const blob = new Blob([htmlContent], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    // Name the file with today's date
    a.href = url;
    a.download = 'Scout_Report_<?php echo date('Y-m-d'); ?>.html';
    
    // Trigger the download
    document.body.appendChild(a);
    a.click();
    
    // Cleanup
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>