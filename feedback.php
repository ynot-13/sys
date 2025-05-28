<?php

$page_title = "My Feedback";
require_once 'includes/header.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'feedback.php' . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''); 
    set_message("Please log in to submit or manage your feedback.", "warning");
    redirect('login.php');
}


if (!isset($mysqli) || !$mysqli instanceof mysqli || $mysqli->connect_error) {
    set_message("Database connection error. Cannot load feedback page.", "error");
    
    display_message(); 
    require_once 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];
$feedback_list = [];
$products_for_dropdown = []; 


$submit_errors = $_SESSION['feedback_errors'] ?? [];
$submit_success = $_SESSION['feedback_success'] ?? '';
$form_data = $_SESSION['feedback_form_data'] ?? []; 


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_data = $_POST;
}

unset($_SESSION['feedback_errors'], $_SESSION['feedback_success'], $_SESSION['feedback_form_data']); 


$product_id_for_this_feedback = null; 
$product_name_for_this_feedback = '';

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['product_id'])) {
    $product_id_from_url = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id_from_url && $product_id_from_url > 0) {
       
        $stmt_prod_name = $mysqli->prepare("SELECT name FROM products WHERE product_id = ? AND is_active = 1");
        if ($stmt_prod_name) {
            $stmt_prod_name->bind_param("i", $product_id_from_url);
            if ($stmt_prod_name->execute()) {
                $res_prod_name = $stmt_prod_name->get_result();
                if ($prod_data = $res_prod_name->fetch_assoc()) {
                    $product_id_for_this_feedback = $product_id_from_url; 
                    $product_name_for_this_feedback = $prod_data['name'];
                
                    if (!isset($form_data['product_id_feedback'])) {
                        $form_data['product_id_feedback'] = $product_id_for_this_feedback;
                    }
                } else {
                    set_message("The product you are trying to review (ID: ".htmlspecialchars($product_id_from_url).") was not found or is inactive.", "warning");
             
                }
                $res_prod_name->free();
            }
            $stmt_prod_name->close();
        }
    }
}



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $submit_errors['csrf'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $comment = sanitize_input($_POST['comment'] ?? '');
        $rating_input = $_POST['rating'] ?? '';
        $rating = ($rating_input !== '')
            ? filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]])
            : null;

   
        $product_id_from_form = filter_input(INPUT_POST, 'product_id_feedback', FILTER_VALIDATE_INT);
       
        if ($product_id_from_form === false) $product_id_from_form = null;
        if (isset($_POST['product_id_feedback']) && $_POST['product_id_feedback'] === '') $product_id_from_form = null;


        if (empty($comment)) { $submit_errors['comment'] = "Feedback comment cannot be empty."; }
        if ($rating_input !== '' && $rating === false) { $submit_errors['rating'] = "Invalid rating value (1-5)."; $rating = null; }

        
        if ($product_id_from_form !== null && $product_id_from_form <= 0) { 
            $submit_errors['product_id_feedback'] = "Invalid product selected.";
            $product_id_from_form = null;
        }

       
        if (!$mysqli || $mysqli->connect_error) {
            $submit_errors['database'] = "Database connection error. Please try again.";
            error_log("Feedback submit DB error: " . ($mysqli ? $mysqli->connect_error : 'mysqli object not available'));
        } elseif (empty($submit_errors)) {
            $sql_insert = "INSERT INTO feedback (user_id, product_id, rating, comment, submitted_at, is_approved) VALUES (?, ?, ?, ?, NOW(), 0)";
            $stmt_insert = $mysqli->prepare($sql_insert);
            if ($stmt_insert) {
                
                $stmt_insert->bind_param("iiis", 
                    $user_id,
                    $product_id_from_form,
                    $rating,
                    $comment
                
                );
                if ($stmt_insert->execute()) {
                    set_message("Thank you for your feedback! It will be reviewed by an administrator.", "success");
                    $form_data = []; 
                   
                    $mysqli->close();
                    redirect('feedback.php' . ($product_id_from_form ? '?product_id='.$product_id_from_form : '')); 
                    exit;
                } else {
                    $submit_errors['database'] = "Failed to submit feedback (Execute).";
                    error_log("Feedback insert EXECUTE error: " . $stmt_insert->error);
                }
                $stmt_insert->close();
            } else {
                $submit_errors['database'] = "Failed to prepare feedback submission.";
                error_log("Feedback insert PREPARE error: " . $mysqli->error);
            }
        }
    }
  
}


$sql_fetch = "SELECT f.feedback_id, f.rating, f.comment, f.submitted_at, f.is_approved,
                     p.name as product_name, p.product_id as feedback_product_id
              FROM feedback f
              LEFT JOIN products p ON f.product_id = p.product_id
              WHERE f.user_id = ?
              ORDER BY f.submitted_at DESC";
$stmt_fetch = $mysqli->prepare($sql_fetch);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $user_id);
    if ($stmt_fetch->execute()) {
        $result = $stmt_fetch->get_result();
        while ($row = $result->fetch_assoc()) { $feedback_list[] = $row; }
        $result->free();
    } else { set_message("Error retrieving your feedback history.", "error"); error_log("Feedback fetch execute error: " . $stmt_fetch->error); }
    $stmt_fetch->close();
} else { set_message("Error preparing feedback history.", "error"); error_log("Feedback fetch prepare error: " . $mysqli->error); }

?>

<div class="feedback-page account-page">
    <h1>My Feedback</h1>
    <?php display_message(); ?>

    <div class="admin-card feedback-form-section">
        <h2>Submit New Feedback</h2>
        <?php if (!empty($submit_errors)): ?> <div class="alert alert-danger"><strong>Please fix:</strong><br><?php echo implode('<br>', array_map('htmlspecialchars', $submit_errors)); ?></div> <?php endif; ?>
        <?php if ($submit_success && empty($submit_errors)): ?> <div class="alert alert-success"><?php echo htmlspecialchars($submit_success); ?></div> <?php endif; ?>

        <?php if (empty($submit_success) || !empty($submit_errors)): ?>
        <form action="feedback.php<?php echo $product_id_for_this_feedback ? '?product_id='.$product_id_for_this_feedback : ''; ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <?php if ($product_id_for_this_feedback && !empty($product_name_for_this_feedback)): ?>
                <div class="form-group">
                    <label>Regarding Product:</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($product_name_for_this_feedback); ?>" readonly disabled>
                 
                    <input type="hidden" name="product_id_feedback" value="<?php echo htmlspecialchars($product_id_for_this_feedback); ?>">
                </div>
            <?php else: ?>
                
                <div class="form-group">
                    <label for="product_id_feedback">Regarding Product (Optional)</label>
                    <select name="product_id_feedback" id="product_id_feedback" class="form-control <?php echo isset($submit_errors['product_id_feedback']) ? 'is-invalid' : ''; ?>">
                        <option value="">-- General Site Feedback --</option>
                        <?php foreach ($products_for_dropdown as $prod):?>
                            <option value="<?php echo $prod['product_id']; ?>" <?php echo (isset($form_data['product_id_feedback']) && $form_data['product_id_feedback'] == $prod['product_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prod['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                     <?php if (isset($submit_errors['product_id_feedback'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($submit_errors['product_id_feedback']); ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="rating">Rating (1-5 Stars, Optional)</label>
                 <select name="rating" id="rating" class="form-control <?php echo isset($submit_errors['rating']) ? 'is-invalid' : ''; ?>" style="max-width: 200px;">
                    <option value="" <?php if(empty($form_data['rating'])) echo 'selected'; ?>>No Rating</option>
                    <?php for($i=5; $i>=1; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php if(isset($form_data['rating']) && $form_data['rating'] == $i) echo 'selected'; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
                 <?php if (isset($submit_errors['rating'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($submit_errors['rating']); ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="comment">Your Feedback / Review <span class="text-danger">*</span></label>
                <textarea name="comment" id="comment" rows="5" class="form-control <?php echo isset($submit_errors['comment']) ? 'is-invalid' : ''; ?>" required><?php echo htmlspecialchars($form_data['comment'] ?? ''); ?></textarea>
                <?php if (isset($submit_errors['comment'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($submit_errors['comment']); ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                 <button type="submit" name="submit_feedback" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="feedback-history-section admin-card">
        <h2>Your Feedback History</h2>
        <?php if (!empty($feedback_list)): ?>
            <div class="table-responsive">
            <table class="admin-table">
                <thead> <tr> <th>Date</th> <th>Product</th> <th>Rating</th> <th>Comment</th> <th>Status</th> <th>Action</th> </tr> </thead>
                <tbody>
                    <?php foreach ($feedback_list as $fb): ?>
                    <tr>
                        <td data-label="Date"><?php echo date("M d, Y", strtotime($fb['submitted_at'])); ?></td>
                        <td data-label="Product">
                            <?php if ($fb['feedback_product_id'] && $fb['product_name']): ?>
                                <a href="product_details.php?id=<?php echo $fb['feedback_product_id']; ?>"><?php echo htmlspecialchars($fb['product_name']); ?></a>
                            <?php else: echo '<em>General Site Feedback</em>'; endif; ?>
                        </td>
                        <td data-label="Rating">
                            <?php if($fb['rating']): for($i=1; $i<=$fb['rating']; $i++) echo '<i class="fas fa-star" style="color: var(--warning-color);"></i>'; for($i=$fb['rating']+1; $i<=5; $i++) echo '<i class="far fa-star" style="color: #ccc;"></i>'; else: echo 'N/A'; endif; ?>
                        </td>
                        <td data-label="Comment"><?php echo nl2br(htmlspecialchars(substr($fb['comment'], 0, 150) . (strlen($fb['comment']) > 150 ? '...' : ''))); ?></td>
                        <td data-label="Status"><?php echo $fb['is_approved'] ? '<span class="badge badge-success">Approved</span>' : '<span class="badge badge-warning">Pending</span>'; ?></td>
                        <td data-label="Action"><button class="btn btn-sm btn-danger disabled" disabled><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?> <p class="text-muted">You have not submitted any feedback yet.</p> <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>