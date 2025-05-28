<?php
$page_title = "Website Settings";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (!is_admin_logged_in()) {
    redirect_to(BASE_URL . 'admin/login.php');
}

$current_admin_id = $_SESSION['admin_user_id'] ?? null;
$current_admin_username = $_SESSION['admin_username'] ?? 'Admin';

$errors = [];
$success_message = '';

function update_setting($mysqli, $key, $value) {
    $stmt = $mysqli->prepare("INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $key, $value, $value);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

function get_setting($mysqli, $key, $default = '') {
    $stmt = $mysqli->prepare("SELECT setting_value FROM website_settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['setting_value'] : $default;
    }
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "CSRF token validation failed. Action aborted.";
    } else {
        $site_name = trim($_POST['site_name']);
        $primary_color = trim($_POST['primary_color']);
        $secondary_color = trim($_POST['secondary_color']);

        if (empty($site_name)) {
            $errors[] = "Site Name cannot be empty.";
        }
        if (!preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $primary_color)) {
            $errors[] = "Invalid Primary Color format. Use hex (e.g., #RRGGBB).";
        }
        if (!empty($secondary_color) && !preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $secondary_color)) {
            $errors[] = "Invalid Secondary Color format. Use hex (e.g., #RRGGBB).";
        }

        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == UPLOAD_ERR_OK) {
            $logo_upload = handle_file_upload($_FILES['site_logo'], UPLOAD_DIR_SITE_ASSETS, ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']);
            if ($logo_upload['success']) {
                $old_logo_path_db = get_setting($mysqli, 'logo_path');
                $old_logo_full_path = UPLOAD_DIR_SITE_ASSETS . basename($old_logo_path_db);
                if ($old_logo_path_db && $old_logo_path_db !== 'img/default_logo.png' && file_exists($old_logo_full_path)) {
                    unlink($old_logo_full_path);
                }
                update_setting($mysqli, 'logo_path', 'uploads/site_assets/' . $logo_upload['filename']);
            } else {
                $errors[] = "Logo upload failed: " . $logo_upload['error'];
            }
        }

        if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] == UPLOAD_ERR_OK) {
            $favicon_upload = handle_file_upload($_FILES['site_favicon'], UPLOAD_DIR_SITE_ASSETS, ['image/x-icon', 'image/png', 'image/vnd.microsoft.icon']);
             if ($favicon_upload['success']) {
                $old_favicon_path_db = get_setting($mysqli, 'favicon_path');
                $old_favicon_full_path = UPLOAD_DIR_SITE_ASSETS . basename($old_favicon_path_db);
                 if ($old_favicon_path_db && $old_favicon_path_db !== 'img/favicon.ico' && file_exists($old_favicon_full_path)) {
                    unlink($old_favicon_full_path);
                }
                update_setting($mysqli, 'favicon_path', 'uploads/site_assets/' . $favicon_upload['filename']);
            } else {
                $errors[] = "Favicon upload failed: " . $favicon_upload['error'];
            }
        }

        if (empty($errors)) {
            update_setting($mysqli, 'site_name', $site_name);
            update_setting($mysqli, 'primary_color', $primary_color);
            update_setting($mysqli, 'secondary_color', $secondary_color);
            $_SESSION['success_message'] = "Website settings updated successfully! Changes might require a page refresh or clearing browser cache to fully apply (especially for CSS and images).";
            redirect_to(BASE_URL . 'admin/website_settings.php');
            exit;
        }
    }
    $csrf_token = generateCsrfToken();
} else {
    $csrf_token = generateCsrfToken();
}

$current_site_name = get_setting($mysqli, 'site_name', SITE_NAME);
$current_logo_path = get_setting($mysqli, 'logo_path', str_replace(BASE_URL, '', LOGO_PATH));
$current_favicon_path = get_setting($mysqli, 'favicon_path', str_replace(BASE_URL, '', FAVICON_PATH));
$current_primary_color = get_setting($mysqli, 'primary_color', PRIMARY_COLOR);
$current_secondary_color = get_setting($mysqli, 'secondary_color', SECONDARY_COLOR);

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-container">
    <h2>Website Settings</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="website_settings.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="form-group">
            <label for="site_name">Site Name:</label>
            <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($current_site_name); ?>" required>
        </div>

        <div class="form-group">
            <label for="site_logo">Site Logo (e.g., PNG, JPG, SVG):</label>
            <input type="file" id="site_logo" name="site_logo" class="form-control-file" accept="image/png, image/jpeg, image/gif, image/svg+xml">
            <small class="form-text text-muted">Current logo:
                <?php if ($current_logo_path): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($current_logo_path); ?>" alt="Current Logo" style="max-height: 50px; background: #eee; padding: 5px; margin-top:5px;">
                <?php else: ?>
                    None set.
                <?php endif; ?>
            </small>
        </div>

        <div class="form-group">
            <label for="site_favicon">Site Favicon (e.g., .ico, .png):</label>
            <input type="file" id="site_favicon" name="site_favicon" class="form-control-file" accept="image/x-icon, image/png, image/vnd.microsoft.icon">
            <small class="form-text text-muted">Current favicon:
                 <?php if ($current_favicon_path): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($current_favicon_path); ?>" alt="Current Favicon" style="max-height: 20px; margin-top:5px; vertical-align:middle;">
                <?php else: ?>
                    None set.
                <?php endif; ?>
            </small>
        </div>

        <div class="form-group">
            <label for="primary_color">Primary Theme Color:</label>
            <input type="color" id="primary_color" name="primary_color" class="form-control" value="<?php echo htmlspecialchars($current_primary_color); ?>" style="width: 100px; height: 40px;">
            <small>Current: <?php echo htmlspecialchars($current_primary_color); ?></small>
        </div>
        
        <div class="form-group">
            <label for="secondary_color">Secondary Theme Color (Optional):</label>
            <input type="color" id="secondary_color" name="secondary_color" class="form-control" value="<?php echo htmlspecialchars($current_secondary_color); ?>" style="width: 100px; height: 40px;">
            <small>Current: <?php echo htmlspecialchars($current_secondary_color); ?></small>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>