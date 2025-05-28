<?php

require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$autoloaderPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    error_log("CRITICAL ERROR: Composer autoloader not found at $autoloaderPath.");
    die("System Error: Cannot generate report. Required components missing. (Ref: PDF_AUTOLOAD)");
}
require_once $autoloaderPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_message("Invalid request for report.", "error");
    redirect('reports.php');
}
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    set_message('Invalid security token.', 'error');
    redirect('reports.php');
}
if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    error_log("Generate Report Error: Invalid database connection.");
    set_message("Database connection error. Cannot generate report.", "error");
    redirect('reports.php');
}

$report_title = "WEALTHY'S Combined Report";
$filename = "wealthys_combined_report_" . date('Ymd') . ".pdf";
$data_html = '';

$data_html .= "<h2>Combined Report</h2>";
$data_html .= "<p style='font-size: 9pt; color: #555;'>Generated on: " . date("Y-m-d H:i:s") . "</p><hr>";

$data_html .= "<h3>Sales Summary</h3>";
$total_sales = 0;
$delivered_sales = 0;
if ($res = $mysqli->query("SELECT SUM(total_amount) as t FROM orders")) {
    $total_sales = $res->fetch_assoc()['t'] ?? 0;
    $res->free();
}
if ($res = $mysqli->query("SELECT SUM(total_amount) as t FROM orders WHERE status = 'Delivered'")) {
    $delivered_sales = $res->fetch_assoc()['t'] ?? 0;
    $res->free();
}
$data_html .= "<p>Total Revenue (All): " . formatCurrency($total_sales) . "</p>";
$data_html .= "<p><strong>Total Revenue (Delivered): " . formatCurrency($delivered_sales) . "</strong></p>";
$data_html .= "<h4>Recent Orders</h4>";
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status, COALESCE(u.username, o.guest_name, 'Guest') as customer FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.order_date DESC LIMIT 100";
if ($result = $mysqli->query($sql)) {
    $data_html .= "<table border=\"1\" cellpadding=\"4\"><thead><tr><th>ID</th><th>Date</th><th>Customer</th><th>Status</th><th align=\"right\">Total</th></tr></thead><tbody>";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data_html .= "<tr><td>#{$row['order_id']}</td><td>" . date('Y-m-d H:i', strtotime($row['order_date'])) . "</td><td>" . htmlspecialchars($row['customer']) . "</td><td>" . htmlspecialchars($row['status']) . "</td><td align=\"right\">" . formatCurrency($row['total_amount']) . "</td></tr>";
        }
    } else {
        $data_html .= "<tr><td colspan='5'>No recent orders found.</td></tr>";
    }
    $data_html .= "</tbody></table>";
    $result->free();
}

$data_html .= "<h3>User List</h3>";
$sql = "SELECT user_id, username, email, full_name, role, created_at, last_login FROM users ORDER BY user_id ASC";
if ($result = $mysqli->query($sql)) {
    $data_html .= "<table border=\"1\" cellpadding=\"4\"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Registered</th><th>Last Login</th></tr></thead><tbody>";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $role_style = $row['role'] == 'admin' ? 'color:red;font-weight:bold;' : 'color:green;';
            $last_login = $row['last_login'] ? date('Y-m-d H:i', strtotime($row['last_login'])) : 'Never';
            $data_html .= "<tr><td>{$row['user_id']}</td><td>" . htmlspecialchars($row['username']) . "</td><td>" . htmlspecialchars($row['email']) . "</td><td>" . htmlspecialchars($row['full_name'] ?: '-') . "</td><td style='{$role_style}'>" . htmlspecialchars(ucfirst($row['role'])) . "</td><td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td><td>{$last_login}</td></tr>";
        }
    } else {
        $data_html .= "<tr><td colspan='7'>No users found.</td></tr>";
    }
    $data_html .= "</tbody></table>";
    $result->free();
}

$data_html .= "<h3>Inventory Status</h3>";
$sql = "SELECT p.product_id, p.name, p.stock, p.price, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.category_id ORDER BY p.name ASC";
if ($result = $mysqli->query($sql)) {
    $data_html .= "<table border=\"1\" cellpadding=\"4\"><thead><tr><th>ID</th><th>Product</th><th>Category</th><th>Stock</th><th align=\"right\">Price</th></tr></thead><tbody>";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stock_level = (int)$row['stock'];
            $row_style = '';
            if ($stock_level <= 0) {
                $row_style = 'background-color:#f8d7da;font-weight:bold;';
            } elseif ($stock_level <= 10) {
                $row_style = 'background-color:#fff3cd;';
            }
            $data_html .= "<tr style='{$row_style}'><td>{$row['product_id']}</td><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['category_name'] ?: 'N/A') . "</td><td>{$stock_level}</td><td align=\"right\">" . formatCurrency($row['price']) . "</td></tr>";
        }
    } else {
        $data_html .= "<tr><td colspan='5'>No products found.</td></tr>";
    }
    $data_html .= "</tbody></table>";
    $result->free();
}

$data_html .= "<h3>Feedback Report</h3>";
$sql = "SELECT f.feedback_id, f.rating, f.comment, f.submitted_at, f.is_approved, u.username as user_username, p.name as product_name FROM feedback f JOIN users u ON f.user_id = u.user_id LEFT JOIN products p ON f.product_id = p.product_id ORDER BY f.submitted_at DESC";
if ($result = $mysqli->query($sql)) {
    $data_html .= "<table border=\"1\" cellpadding=\"4\"><thead><tr><th>ID</th><th>Date</th><th>User</th><th>Product</th><th>Rating</th><th>Comment</th><th>Status</th></tr></thead><tbody>";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status = $row['is_approved'] ? 'Approved' : 'Pending';
            $status_style = $row['is_approved'] ? 'color:green;' : 'color:orange;font-style:italic;';
            $rating = $row['rating'] ? str_repeat('★', $row['rating']) . str_repeat('☆', 5 - $row['rating']) : 'N/A';
            $data_html .= "<tr><td>{$row['feedback_id']}</td><td>" . date('Y-m-d', strtotime($row['submitted_at'])) . "</td><td>" . htmlspecialchars($row['user_username']) . "</td><td>" . htmlspecialchars($row['product_name'] ?: 'General') . "</td><td>{$rating}</td><td>" . nl2br(htmlspecialchars($row['comment'])) . "</td><td style='{$status_style}'>{$status}</td></tr>";
        }
    } else {
        $data_html .= "<tr><td colspan='7'>No feedback found.</td></tr>";
    }
    $data_html .= "</tbody></table>";
    $result->free();
}

try {
    $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(SITE_NAME . ' Admin');
    $pdf->SetTitle($report_title . ' - ' . SITE_NAME);
    $pdf->SetSubject($report_title);
    $pdf->setHeaderData('', 0, SITE_NAME . ' Report', $report_title . "\nGenerated: " . date('Y-m-d H:i'));
    $pdf->setFooterData(array(0, 64, 0), array(0, 64, 128));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->AddPage();
    $full_html_for_pdf = "<style>
        body { font-family: 'dejavusans', sans-serif; font-size: 9pt; }
        h2 { font-size: 14pt; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; color: #333; }
        h3, h4 { font-size: 11pt; margin-top: 15px; margin-bottom: 5px; color: #444; }
        p { margin-bottom: 8px; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 8pt; }
        th, td { border: 1px solid #AEAEAE; padding: 4px 6px; text-align: left; vertical-align: top; }
        thead th { background-color: #EFEFEF; font-weight: bold; }
        tbody tr:nth-child(even) { background-color: #F9F9F9; }
        .currency, [align=\"right\"] { text-align: right; }
        .user-role-admin { color: red; font-weight: bold; }
        .user-role-user { color: green; }
        .stock-low { background-color: #fff3cd; }
        .stock-out { background-color: #f8d7da; font-weight: bold; }
        .feedback-pending { font-style: italic; color: orange; }
        .feedback-approved { color: green; }
    </style>" . $data_html;
    $pdf->writeHTML($full_html_for_pdf, true, false, true, false, '');
    ob_end_clean();
    $pdf->Output($filename, 'I');
    if (isset($mysqli)) $mysqli->close();
    exit;
} catch (Exception $e) {
    error_log('TCPDF Error generating report: ' . $e->getMessage());
    set_message('Error generating PDF report: ' . $e->getMessage(), 'error');
    if (isset($mysqli) && $mysqli instanceof mysqli) $mysqli->close();
    redirect('reports.php');
}
?>
