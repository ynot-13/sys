<?php
$page_title = "Add New Product";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';

$categories = [];
$result_cat = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name ASC");
if ($result_cat) {
    while ($row = $result_cat->fetch_assoc()) {
        $categories[] = $row;
    }
    if ($result_cat instanceof mysqli_result) $result_cat->free();
}

$errors = [];
$form_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid request token.';
     } else {
         $form_data = $_POST;

         $name = sanitize_input($_POST['name'] ?? '');
         $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
         $price_input = $_POST['price'] ?? '';
         $stock_input = $_POST['stock'] ?? '';
         $description = sanitize_input($_POST['description'] ?? '');
         $is_active = isset($_POST['is_active']) ? 1 : 0;

         $price = null;
         $stock = null;

         if (empty($name)) $errors['name'] = "Product name is required.";
         if (!$category_id) $errors['category_id'] = "Please select a valid category.";

         if ($price_input === '') {
             $errors['price'] = "Price is required.";
         } else {
             $price = filter_var($price_input, FILTER_VALIDATE_FLOAT);
             if ($price === false || $price < 0) {
                 $errors['price'] = "Invalid price (must be a non-negative number).";
             }
         }

         if ($stock_input === '') {
             $errors['stock'] = "Stock quantity is required.";
         } else {
             $stock = filter_var($stock_input, FILTER_VALIDATE_INT);
             if ($stock === false || $stock < 0) {
                 $errors['stock'] = "Invalid stock quantity (must be a non-negative integer).";
             }
         }

         if (empty($description)) $errors['description'] = "Description is required.";

         $is_on_flash_sale = isset($_POST['is_on_flash_sale']) ? 1 : 0;
         $flash_sale_price_input = $_POST['flash_sale_price'] ?? null;
         $flash_sale_start_date_input = $_POST['flash_sale_start_date'] ?? null;
         $flash_sale_end_date_input = $_POST['flash_sale_end_date'] ?? null;

         $flash_sale_price = null;
         $flash_sale_start_date = null;
         $flash_sale_end_date = null;

         if ($is_on_flash_sale) {
            if ($flash_sale_price_input === '' || $flash_sale_price_input === null) {
                $errors['flash_sale_price'] = "Flash sale price is required if flash sale is enabled.";
            } else {
                $flash_sale_price = filter_var($flash_sale_price_input, FILTER_VALIDATE_FLOAT);
                if ($flash_sale_price === false || $flash_sale_price < 0) {
                    $errors['flash_sale_price'] = "Invalid flash sale price (must be a non-negative number).";
                } elseif ($price !== null && $flash_sale_price >= $price) {
                    $errors['flash_sale_price'] = "Flash sale price must be less than the regular price.";
                }
            }

            if (!empty($flash_sale_start_date_input)) {
                $start_timestamp = strtotime($flash_sale_start_date_input);
                if ($start_timestamp === false) {
                    $errors['flash_sale_start_date'] = "Invalid flash sale start date format.";
                } else {
                    $flash_sale_start_date = date('Y-m-d H:i:s', $start_timestamp);
                }
            }

            if (!empty($flash_sale_end_date_input)) {
                $end_timestamp = strtotime($flash_sale_end_date_input);
                if ($end_timestamp === false) {
                    $errors['flash_sale_end_date'] = "Invalid flash sale end date format.";
                } else {
                    $flash_sale_end_date = date('Y-m-d H:i:s', $end_timestamp);
                }
            }

            if (empty($errors['flash_sale_start_date']) && empty($errors['flash_sale_end_date']) &&
                $flash_sale_start_date && $flash_sale_end_date && isset($end_timestamp) && isset($start_timestamp) && $end_timestamp <= $start_timestamp) {
                $errors['flash_sale_end_date'] = "Flash sale end date must be after the start date.";
            }
         } else {
            $flash_sale_price = null;
            $flash_sale_start_date = null;
            $flash_sale_end_date = null;
         }

         $image_url_for_db = 'img/placeholder.png';
         $destination = null;
         $upload_dir = __DIR__ . '/../img/products/';
         $image_db_path = 'img/products/';

         if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
             $file = $_FILES['image'];
             $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
             $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

             if (!in_array($file_ext, $allowed_exts)) {
                 $errors['image'] = "Invalid file type. Allowed: " . implode(', ', $allowed_exts);
             } elseif ($file['size'] > 5 * 1024 * 1024) {
                 $errors['image'] = "File size exceeds 5MB limit.";
             } else {
                 $new_file_name = uniqid('prod_', true) . '.' . $file_ext;
                 $destination = $upload_dir . $new_file_name;
                 if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
                        $errors['image'] = "Failed to create upload directory. Check permissions.";
                    }
                 }

                 if (empty($errors['image']) && move_uploaded_file($file['tmp_name'], $destination)) {
                     $image_url_for_db = $image_db_path . $new_file_name;
                 } elseif (empty($errors['image'])) {
                     $errors['image'] = "Failed to move uploaded file. Check permissions on the products directory.";
                 }
             }
         } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
              $errors['image'] = "File upload error code: " . $_FILES['image']['error'];
         }

         if (empty($errors)) {
             $sql_insert = "INSERT INTO products (category_id, name, description, price, stock, image_url, created_at,
                                                is_active, is_on_flash_sale, flash_sale_price, flash_sale_start_date, flash_sale_end_date)
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
             $stmt_insert = $mysqli->prepare($sql_insert);
             if ($stmt_insert) {
                 $stmt_insert->bind_param("issdisiidss", $category_id, $name, $description, $price, $stock, $image_url_for_db,
                                                     $is_active, $is_on_flash_sale, $flash_sale_price, $flash_sale_start_date, $flash_sale_end_date);

                 if ($stmt_insert->execute()) {
                     set_message("Product '" . htmlspecialchars($name) . "' added successfully!", "success");
                     $stmt_insert->close();
                     header("Location: manage_products.php");
                     exit;
                 } else {
                      $errors['database'] = "Database error adding product: " . $stmt_insert->error;
                      if ($destination && $image_url_for_db !== 'img/placeholder.png' && file_exists($destination)) {
                          @unlink($destination);
                      }
                 }
                 if ($stmt_insert instanceof mysqli_stmt) $stmt_insert->close();
             } else {
                  $errors['database'] = "Database prepare error: " . $mysqli->error;
             }
         }
     }
}
?>

<div class="admin-card">
    <a href="manage_products.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Product List</a>
    <h2>Add New Product</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $key => $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="add_product.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="form-group">
            <label for="name">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
            <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['name']); ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="category_id">Category <span class="text-danger">*</span></label>
            <select name="category_id" id="category_id" class="form-control <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($form_data['category_id']) && $form_data['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
             <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['category_id']); ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="price">Price (<?php echo CURRENCY_SYMBOL ?? '$'; ?>) <span class="text-danger">*</span></label>
            <input type="number" name="price" id="price" step="0.01" min="0" class="form-control <?php echo isset($errors['price']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>" required>
             <?php if (isset($errors['price'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['price']); ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="stock">Stock Quantity <span class="text-danger">*</span></label>
            <input type="number" name="stock" id="stock" step="1" min="0" class="form-control <?php echo isset($errors['stock']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['stock'] ?? '0'); ?>" required>
             <?php if (isset($errors['stock'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['stock']); ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="description">Description <span class="text-danger">*</span></label>
            <textarea name="description" id="description" rows="6" class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
             <?php if (isset($errors['description'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['description']); ?></div><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="image">Product Image</label>
            <input type="file" name="image" id="image" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>">
             <small class="form-text text-muted">Optional. Allowed: jpg, jpeg, png, gif, webp. Max 5MB.</small>
             <?php if (isset($errors['image'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['image']); ?></div><?php endif; ?>
        </div>

        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1" <?php echo (isset($form_data['is_active']) && $form_data['is_active'] == '1' || !isset($form_data['is_active']) && empty($_POST) ) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active">Product is Active</label>
        </div>

        <hr>
        <h4>Flash Sale (Optional)</h4>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_on_flash_sale" id="is_on_flash_sale" value="1" <?php echo (isset($form_data['is_on_flash_sale']) && $form_data['is_on_flash_sale'] == '1') ? 'checked' : ''; ?> onchange="toggleFlashSaleFields()">
            <label class="form-check-label" for="is_on_flash_sale">
                Enable Flash Sale for this product
            </label>
        </div>

        <div id="flash_sale_fields_container" style="<?php echo (isset($form_data['is_on_flash_sale']) && $form_data['is_on_flash_sale'] == '1') ? '' : 'display: none;'; ?>">
            <div class="form-group">
                <label for="flash_sale_price">Flash Sale Price (<?php echo CURRENCY_SYMBOL ?? '$'; ?>) <span class="text-danger">*</span></label>
                <input type="number" name="flash_sale_price" id="flash_sale_price" step="0.01" min="0" class="form-control <?php echo isset($errors['flash_sale_price']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['flash_sale_price'] ?? ''); ?>">
                <?php if (isset($errors['flash_sale_price'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['flash_sale_price']); ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="flash_sale_start_date">Flash Sale Start Date/Time</label>
                <input type="datetime-local" name="flash_sale_start_date" id="flash_sale_start_date" class="form-control <?php echo isset($errors['flash_sale_start_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['flash_sale_start_date'] ?? ''); ?>">
                <small class="form-text text-muted">Optional. If blank, sale starts immediately if enabled.</small>
                <?php if (isset($errors['flash_sale_start_date'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['flash_sale_start_date']); ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="flash_sale_end_date">Flash Sale End Date/Time</label>
                <input type="datetime-local" name="flash_sale_end_date" id="flash_sale_end_date" class="form-control <?php echo isset($errors['flash_sale_end_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_data['flash_sale_end_date'] ?? ''); ?>">
                <small class="form-text text-muted">Optional. If blank, sale runs indefinitely (or until manually disabled).</small>
                <?php if (isset($errors['flash_sale_end_date'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['flash_sale_end_date']); ?></div><?php endif; ?>
            </div>
        </div>
        <hr>

        <div class="form-group mt-4">
            <button type="submit" class="btn btn-success">Add Product</button>
            <a href="manage_products.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
function toggleFlashSaleFields() {
    var checkbox = document.getElementById('is_on_flash_sale');
    var fieldsContainer = document.getElementById('flash_sale_fields_container');
    var flashPriceInput = document.getElementById('flash_sale_price');
    if (checkbox.checked) {
        fieldsContainer.style.display = 'block';
        flashPriceInput.required = true;
    } else {
        fieldsContainer.style.display = 'none';
        flashPriceInput.required = false;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleFlashSaleFields();
});
</script>

<?php
require_once 'admin_footer.php';
?>