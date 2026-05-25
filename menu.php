<?php
$current_page = basename($_SERVER['PHP_SELF']);
$nav_class = isset($nav_no_print) && $nav_no_print ? 'nav-bar no-print' : 'nav-bar';
?>
<div class="<?php echo $nav_class; ?>">
    <div class="dropdown">
        <button type="button" class="btn btn-confirm" onclick="toggleMenu()"> Menu ▾</button>
        <div id="adminMenu" class="dropdown-content">
            <div class="dropdown-section">Management</div>
            <a href="admin.php">📋 Order Management</a>
            <a href="purchases.php">📦 Manage Purchases</a>
            <a href="scout_shifts.php">👥 Manage Scout Shifts</a>
            <a href="shift_sales.php">💰 Manage Shift Sales</a>
            
            <div class="dropdown-section">Reports</div>
            <a href="shift_report.php">📊 Shift Sales Report</a>
            <a href="individual_sales_report.php">👤 Individual Sales Report</a>
            <a href="inventory_report.php">📦 Inventory Report</a>
            <a href="reports.php">📜 Individual Sales Details</a>

            <?php if ($current_page === 'admin.php'): ?>
                <div class="dropdown-section">Exports</div>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="download_orders_csv" class="dropdown-item-btn">📥 Download Order Info (CSV)</button>
                    <button type="submit" name="download_products_csv" class="dropdown-item-btn">🛍️ Download Products (CSV)</button>
                </form>
            <?php elseif ($current_page === 'reports.php'): ?>
                <div class="dropdown-section">Exports</div>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="download_scout_report_csv" class="dropdown-item-btn">📥 Download Report (CSV)</button>
                    <button type="button" onclick="downloadPrintableHTML()" class="dropdown-item-btn">📄 Download Printable (HTML)</button>
                </form>
            <?php elseif (in_array($current_page, ['shift_report.php', 'individual_sales_report.php', 'inventory_report.php'])): ?>
                <div class="dropdown-section">Exports</div>
                <button type="button" onclick="window.print()" class="dropdown-item-btn">🖨️ Print Report</button>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if (isset($extra_nav_html)) echo $extra_nav_html; ?>
        <a href="admin.php?logout=1" class="btn btn-logout">Logout</a>
    </div>
</div>

<script>
function toggleMenu() {
    const menu = document.getElementById("adminMenu");
    if (menu) menu.classList.toggle("show");
}

window.onclick = function(event) {
  if (!event.target.matches('.btn-confirm')) {
    var dropdowns = document.getElementsByClassName("dropdown-content");
    for (var i = 0; i < dropdowns.length; i++) {
      var openDropdown = dropdowns[i];
      if (openDropdown && openDropdown.classList.contains('show')) {
        openDropdown.classList.remove('show');
      }
    }
  }
}
</script>