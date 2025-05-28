<?php
$page_title = "Edit Product";
require_once 'admin_auth.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once 'admin_header.php';

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    set_message("Invalid Product ID.", "error");
    redirect("manage_products.php");
}

$product = null;
$stmt_get = $mysqli->prepare("SELECT * FROM products WHERE product_id = ?");
if ($stmt_get) {
    $stmt_get->bind_param("i", $product_id);
    if ($stmt_get->execute()) {
        $result_get = $stmt_get->get_result();
        if ($result_get->num_rows === 1) {
            $product = $result_get->fetch_assoc();
        } else {
            set_message("Product not found (ID: $product_id).", "error");
            redirect("manage_products.php");
        }
        if ($result_get instanceof mysqli_result) $result_get->free();
    } else {
         set_message("Error executing product fetch: " . $stmt_get->error, "error");
         redirect("manage_products.php");
    }
    $stmt_get->close();
} else {
     set_message("Error preparing product fetch: " . $mysqli->error, "error");
     redirect("manage_products.php");
}

$categories = [];
$result_cat = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name ASC");
if ($result_cat) {
    while ($row = $result_cat->fetch_assoc()) {
        $categories[] = $row;
    }
    if ($result_cat instanceof mysqli_result) $result_cat->free();
}

$errors = [];
$form_data = $product;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid request token.';
     } else {
         $form_data = $_POST;
         $current_image_url = $product['image_url'];

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
                try {
                    $dt = new DateTime($flash_sale_start_date_input);
                    $flash_sale_start_date = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $errors['flash_sale_start_date'] = "Invalid flash sale start date format.";
                }
            } else { $flash_sale_start_date = null; }


            if (!empty($flash_sale_end_date_input)) {
                 try {
                    $dt = new DateTime($flash_sale_end_date_input);
                    $flash_sale_end_date = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $errors['flash_sale_end_date'] = "Invalid flash sale end date format.";
                }
            } else { $flash_sale_end_date = null; }


            if (empty($errors['flash_sale_start_date']) && empty($errors['flash_sale_end_date']) &&
                $flash_sale_start_date && $flash_sale_end_date && strtotime($flash_sale_end_date) <= strtotime($flash_sale_start_date)) {
                $errors['flash_sale_end_date'] = "Flash sale end date must be after the start date.";
            }
         } else {
            $flash_sale_price = null;
            $flash_sale_start_date = null;
            $flash_sale_end_date = null;
         }

         $image_url_for_db = $current_image_url;
         $new_file_uploaded = false;
         $new_file_destination = null;
         $upload_dir = __DIR__ . '/../img/products/';
         $image_db_path = 'img/products/';

         if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
             $file = $_FILES['image'];
             $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
             $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

             if (!in_array($file_ext, $allowed_exts)) { $errors['image'] = "Invalid file type."; }
             elseif ($file['size'] > 5 * 1024 * 1024) { $errors['image'] = "File size exceeds 5MB."; }
             else {
                 $new_file_name = uniqid('prod_', true) . '.' . $file_ext;
                 $new_file_destination = $upload_dir . $new_file_name;
                 if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
                         $errors['image'] = "Failed to create upload directory. Check permissions.";
                    }
                 }

                 if (empty($errors['image']) && move_uploaded_file($file['tmp_name'], $new_file_destination)) {
                     $image_url_for_db = $image_db_path . $new_file_name;
                     $new_file_uploaded = true;
                 } elseif (empty($errors['image'])) {
                     $errors['image'] = "Failed to move uploaded file.";
                 }
             }
         } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
              $errors['image'] = "File upload error code: " . $_FILES['image']['error'];
         }

         if (empty($errors)) {
             $sql_update = "UPDATE products SET category_id=?, name=?, description=?, price=?, stock=?, image_url=?,
                                                is_active=?, is_on_flash_sale=?, flash_sale_price=?, flash_sale_start_date=?, flash_sale_end_date=?
                            WHERE product_id=?";
             $stmt_update = $mysqli->prepare($sql_update);
             if ($stmt_update) {
                 $stmt_update->bind_param("issdisiidssi", $category_id, $name, $description, $price, $stock, $image_url_for_db,
                                                        $is_active, $is_on_flash_sale, $flash_sale_price, $flash_sale_start_date, $flash_sale_end_date,
                                                        $product_id);

                 if ($stmt_update->execute()) {
                     if ($new_file_uploaded && $current_image_url !== 'img/placeholder.png' && $current_image_url !== $image_url_for_db) {
                         $absolute_old_path = __DIR__ . '/../' . $current_image_url;
                         if (file_exists($absolute_old_path)) {
                             @unlink($absolute_old_path);
                         }
                     }
                     set_message("Product '" . htmlspecialchars($name) . "' updated successfully!", "success");
                     $stmt_update->close();
                     header("Location: manage_products.php");
                     exit;
                 } else {
                      $errors['database'] = "Database error updating product: " . $stmt_update->error;
                      if ($new_file_uploaded && $new_file_destination && file_exists($new_file_destination)) {
                           @unlink($new_file_destination);
                      }
                 }
                 if($stmt_update instanceof mysqli_stmt) $stmt_update->close();
             } else {
                  $errors['database'] = "Database prepare error: " . $mysqli->error;
             }
         }

         if (!empty($errors)) {
             $form_data['product_id'] = $product_id;
             $form_data['image_url'] = $current_image_url;
             $product = $form_data;
         }
     }
} else {
    $form_data = $product;
}

?>

<div class="admin-card">
     <a href="manage_products.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Product List</a>
    <h2>Edit Product: <?php echo htmlspecialchars($product['name'] ?? 'N/A'); ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul> <?php foreach ($errors as $error): ?> <li><?php echo htmlspecialchars($error); ?></li> <?php endforeach; ?> </ul>
        </div>
    <?php endif; ?>

    <form action="edit_product.php?id=<?php echo $product_id; ?>" method="post" enctype="multipart/form-data">
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
            <div style="margin-bottom: 10px;">
                Current Image:
                <?php
                    $current_img_display_path = BASE_URL . ($product['image_url'] ?? 'img/placeholder.png');
                    if (isset($product['image_url']) && !empty($product['image_url']) && $product['image_url'] !== 'img/placeholder.png' && file_exists(__DIR__ . '/../' . $product['image_url'])) {
                        // Valid current image
                    } else {
                        $current_img_display_path = BASE_URL . 'img/placeholder.png'; // Fallback if original is placeholder or not found
                    }
                ?>
                <img src="<?php echo htmlspecialchars($current_img_display_path); ?>?v=<?php echo time(); ?>" alt="Current Image" style="max-width: 100px; max-height: 100px; margin-left: 10px; vertical-align: middle; border:1px solid #eee;">
            </div>
            <input type="file" name="image" id="image" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>">
             <small class="form-text text-muted">Upload new image to replace current. Allowed: jpg, png, etc. Max 5MB.</small>
             <?php if (isset($errors['image'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['image']); ?></div><?php endif; ?>
        </div>

        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" name="is_active" id="is_active" value="1" <?php echo (isset($form_data['is_active']) && $form_data['is_active'] == '1') ? 'checked' : ''; ?>>
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
                <?php
                    $fs_start_date_val = '';
                    if (!empty($form_data['flash_sale_start_date'])) {
                        try {
                            $dt = new DateTime($form_data['flash_sale_start_date']);
                            $fs_start_date_val = $dt->format('Y-m-d\TH:i');
                        } catch (Exception $e) { /* leave empty if invalid */ }
                    }
                ?>
                <input type="datetime-local" name="flash_sale_start_date" id="flash_sale_start_date" class="form-control <?php echo isset($errors['flash_sale_start_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($fs_start_date_val); ?>">
                <small class="form-text text-muted">Optional. If blank, sale starts immediately if enabled.</small>
                <?php if (isset($errors['flash_sale_start_date'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['flash_sale_start_date']); ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="flash_sale_end_date">Flash Sale End Date/Time</label>
                <?php
                    $fs_end_date_val = '';
                    if (!empty($form_data['flash_sale_end_date'])) {
                         try {
                            $dt = new DateTime($form_data['flash_sale_end_date']);
                            $fs_end_date_val = $dt->format('Y-m-d\TH:i');
                        } catch (Exception $e) { /* leave empty if invalid */ }
                    }
                ?>
                <input type="datetime-local" name="flash_sale_end_date" id="flash_sale_end_date" class="form-control <?php echo isset($errors['flash_sale_end_date']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($fs_end_date_val); ?>">
                <small class="form-text text-muted">Optional. If blank, sale runs indefinitely (or until manually disabled).</small>
                <?php if (isset($errors['flash_sale_end_date'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['flash_sale_end_date']); ?></div><?php endif; ?>
            </div>
        </div>
        <hr>

        <div class="form-group mt-4">
            <button type="submit" class="btn btn-primary">Update Product</button>
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