<?php

$page_title = "";
require_once 'admin_auth.php';
require_once 'admin_header.php';

$report_type = $_GET['type'] ?? 'sales_summary';

?>

<div class="admin-card">
    <h2>Data Reports</h2>
    <p>Select a report type below and click generate to create a printable PDF document.</p>

    <div style="margin-top: 30px;">
        <form action="generate_report.php" method="post" target="_blank">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="report_select" style="font-weight: bold; display: block; margin-bottom: 10px;">Select Report Type:</label>
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <select name="report_type" id="report_select" class="form-control" style="width: auto; flex-grow: 1; min-width: 200px;">
                        <option value="sales_summary" <?php echo $report_type == 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                        <option value="user_list" <?php echo $report_type == 'user_list' ? 'selected' : ''; ?>>User List</option>
                        <option value="inventory_status" <?php echo $report_type == 'inventory_status' ? 'selected' : ''; ?>>Inventory Status</option>
                        <option value="feedback_report" <?php echo $report_type == 'feedback_report' ? 'selected' : ''; ?>>Feedback Report</option>
                        <option value="all_reports" <?php echo $report_type == 'all_reports' ? 'selected' : ''; ?>>All Reports</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generate PDF Report</button>
                </div>
            </div>
        </form>
    </div>

<?php
require_once 'admin_footer.php';
?>
