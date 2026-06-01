<?php
require_once 'db.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_shift'])) {
        $name = trim($_POST['scout_name']);
        $count = (int)$_POST['shifts'];
        $comments = trim($_POST['comments'] ?? '');
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO {$tab_prefix}_scout_shifts (scout_name, shifts, comments) VALUES (?, ?, ?)");
            $stmt->execute([$name, $count, $comments]);
            $_SESSION['success_msg'] = "Record added for " . $name;
        }
    } elseif (isset($_POST['update_shift'])) {
        $orig_name = $_POST['original_name'];
        $new_name = trim($_POST['scout_name']);
        $count = (int)$_POST['shifts'];
        $comments = trim($_POST['comments'] ?? '');
        $stmt = $pdo->prepare("UPDATE {$tab_prefix}_scout_shifts SET scout_name = ?, shifts = ?, comments = ? WHERE scout_name = ? LIMIT 1");
        $stmt->execute([$new_name, $count, $comments, $orig_name]);
        $_SESSION['success_msg'] = "Record updated for " . $new_name;
    } elseif (isset($_POST['delete_shift'])) {
        $name = $_POST['scout_name'];

        // Log the record before deletion
        $fetchStmt = $pdo->prepare("SELECT * FROM {$tab_prefix}_scout_shifts WHERE scout_name = ?");
        $fetchStmt->execute([$name]);
        $record = $fetchStmt->fetch();
        if ($record) {
            $logStmt = $pdo->prepare("INSERT INTO {$tab_prefix}_delete_log (log_timestamp, page, log_message) VALUES (CURRENT_TIMESTAMP, 'scout_shifts.php', ?)");
            $logStmt->execute([generateInsertSql("{$tab_prefix}_scout_shifts", $record, $pdo)]);
        }

        $stmt = $pdo->prepare("DELETE FROM {$tab_prefix}_scout_shifts WHERE scout_name = ?");
        $stmt->execute([$name]);
        $_SESSION['success_msg'] = "Record deleted successfully.";
    }
    header("Location: scout_shifts.php");
    exit;
}

$shifts = $pdo->query("SELECT * FROM {$tab_prefix}_scout_shifts ORDER BY scout_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<?php $page_title = 'Manage Scout Shifts'; include 'header-html.php'; ?>

<div class="main-container">
    <?php include 'menu.php'; ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="success-message">
            ✅ <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <div class="order-card">
        <h2 class="headings" id="form_title">Add New Scout Shift Record</h2>
        <form id="edit_form" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <input type="hidden" name="original_name">
            
            <div class="form-group">
                <label>Scout Name</label>
                <input type="text" name="scout_name" required placeholder="Full Name">
            </div>
            <div class="form-group">
                <label>Shifts Completed</label>
                <input type="number" name="shifts" value="1" min="0" required>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Comments</label>
                <input type="text" name="comments" placeholder="Optional notes">
            </div>
            <div style="grid-column: 1 / -1; text-align: right;">
                <button type="button" id="cancel_btn" onclick="cancelEdit()" class="btn btn-back" style="display:none;">Cancel</button>
                <button type="submit" id="submit_btn" name="add_shift" class="btn btn-confirm">Add Record</button>
            </div>
        </form>
    </div>

    <div class="order-card">
        <h2 class="headings">Scout Shifts Completed</h2>
        <div style="overflow-x: auto;">
            <table class="stack-mobile">
                <thead>
                    <tr>
                        <th>Scout Name</th>
                        <th style="width: 150px; text-align: center;">Shifts Completed</th>
                        <th>Comments</th>
                        <th style="width: 200px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px; color: #888;">No shift records found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($shifts as $row): ?>
                        <tr>
                            <td data-label="Scout Name"><?php echo htmlspecialchars($row['scout_name']); ?></td>
                            <td data-label="Shifts Completed" style="text-align: center;"><?php echo $row['shifts']; ?></td>
                            <td data-label="Comments"><?php echo htmlspecialchars($row['comments'] ?? ''); ?></td>
                            <td data-label="Actions" style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn-edit" 
                                        style="border:none; background:none; cursor:pointer;"
                                        onclick="editScout(this)"
                                        data-name="<?php echo htmlspecialchars($row['scout_name']); ?>"
                                        data-shifts="<?php echo $row['shifts']; ?>"
                                        data-comments="<?php echo htmlspecialchars($row['comments'] ?? ''); ?>">
                                    Edit
                                </button>

                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');" style="display:inline;">
                                    <input type="hidden" name="scout_name" value="<?php echo htmlspecialchars($row['scout_name']); ?>">
                                    <button type="submit" name="delete_shift" class="btn-remove" style="border:none; background:none; cursor:pointer;">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* Inline adjustments for the shift management table */
    .card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    input[type="number"] { font-size: 14px !important; }
</style>

<script>
    // Auto-hide success messages after 5 seconds
    document.querySelectorAll('.success-message').forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s ease';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });

    function editScout(btn) {
        const data = btn.dataset;
        document.getElementById('form_title').innerText = "Update Scout Record";
        document.getElementById('submit_btn').innerText = "Save Changes";
        document.getElementById('submit_btn').name = "update_shift";
        document.getElementById('cancel_btn').style.display = "inline-block";
        
        const form = document.getElementById('edit_form');
        form.scout_name.value = data.name;
        form.shifts.value = data.shifts;
        form.comments.value = data.comments;
        form.original_name.value = data.name;

        window.scrollTo({ top: 0, behavior: 'smooth' });
        form.scout_name.focus();
    }
    function cancelEdit() {
        document.getElementById('edit_form').reset();
        document.getElementById('form_title').innerText = "Add New Scout Shift Record";
        document.getElementById('submit_btn').innerText = "Add Record";
        document.getElementById('submit_btn').name = "add_shift";
        document.getElementById('cancel_btn').style.display = "none";
    }
</script>

</body>
</html>