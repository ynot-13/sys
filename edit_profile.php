<?php


$page_title = "Edit Profile";
require_once 'includes/header.php'; 


if (!isLoggedIn()) { }
if (!isset($mysqli) || !$mysqli instanceof mysqli) { }

$user_id = $_SESSION['user_id'];
$user_info = null;
$errors = [];
$success_message = '';


$stmt = $mysqli->prepare("SELECT username, email, full_name, profile_image_path FROM users WHERE user_id = ?");
if($stmt) { $stmt->bind_param("i", $user_id); if($stmt->execute()){ $result = $stmt->get_result(); if($result->num_rows === 1){ $user_info = $result->fetch_assoc(); } else { redirect('logout.php'); } $result->free(); } $stmt->close(); }
if (!$user_info) {  }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token.';
    } else {
    
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $username = sanitize_input($_POST['username'] ?? '');
        $current_image_path = $user_info['profile_image_path']; 

      
        if (empty($username)) { $errors[] = "Username cannot be empty."; }
        elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) { $errors[] = "Invalid username format."; }
        if (empty($email)) { $errors[] = "A valid email address is required."; }

       
        if ($username !== $user_info['username']) { }
        if ($email !== $user_info['email']) {  }

     
        $new_image_path_for_db = $current_image_path; 
        $upload_error = false;
        $upload_dir = __DIR__ . '/img/avatars/'; 
        $image_db_prefix = 'img/avatars/';  

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
             $file = $_FILES['profile_picture'];
             $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
             $allowed_exts = ['jpg', 'jpeg', 'png', 'gif']; 
             $max_size = 2 * 1024 * 1024; 

             if (!in_array($file_ext, $allowed_exts)) { $errors[] = "Invalid image file type (jpg, jpeg, png, gif only)."; $upload_error = true; }
             elseif ($file['size'] > $max_size) { $errors[] = "Image file size exceeds 2MB limit."; $upload_error = true; }
             else {
              
                 $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                 $destination = $upload_dir . $new_filename;

              
                 if (!is_dir($upload_dir)) { mkdir($upload_dir, 0775, true); }

                
                 if (move_uploaded_file($file['tmp_name'], $destination)) {
                     $new_image_path_for_db = $image_db_prefix . $new_filename; 
                 
                     if ($current_image_path && $current_image_path != 'img/default-avatar.png') {
                          $old_file_path = __DIR__ . '/' . $current_image_path;
                          if(file_exists($old_file_path)) { @unlink($old_file_path); }
                     }
                 } else {
                     $errors[] = "Failed to save uploaded profile picture.";
                     $upload_error = true;
                 }
             }
        } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
             $errors[] = "Error uploading profile picture (Code: ".$_FILES['profile_picture']['error'].").";
             $upload_error = true;
        }

        
        if (empty($errors)) {
            $sql_update = "UPDATE users SET username = ?, email = ?, full_name = ?, profile_image_path = ? WHERE user_id = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("ssssi", $username, $email, $full_name, $new_image_path_for_db, $user_id);
                if ($stmt_update->execute()) {
                     $success_message = "Profile updated successfully!";
                     $_SESSION['username'] = $username; $_SESSION['email'] = $email; 
                    
                     $user_info['username'] = $username; $user_info['email'] = $email;
                     $user_info['full_name'] = $full_name; $user_info['profile_image_path'] = $new_image_path_for_db;
                } else {
                     $errors[] = "Failed to update profile database.";
                     error_log("Profile update DB error for user $user_id: " . $stmt_update->error);
                    
                     if (!$upload_error && $new_image_path_for_db != $current_image_path && $new_image_path_for_db != 'img/default-avatar.png') {
                          if(file_exists($destination)) { @unlink($destination); }
                     }
                }
                $stmt_update->close();
            } else { $errors[] = "Database prepare error during update."; error_log("Profile update prepare error: ".$mysqli->error); }
        }
    } 

    
     if(!empty($errors)) {
         $user_info['username'] = $username ?? $user_info['username'];
         $user_info['email'] = $email ?? $user_info['email'];
         $user_info['full_name'] = $full_name ?? $user_info['full_name'];
         
     }
} 


$form_profile_pic = BASE_URL . 'img/default-avatar.png'; 
if ($user_info && !empty($user_info['profile_image_path'])) {
    if (file_exists(__DIR__ . '/' . $user_info['profile_image_path'])) {
        $form_profile_pic = BASE_URL . htmlspecialchars($user_info['profile_image_path']) . '?v='. time(); 
    }
}
?>

<div class="account-page">
    <h1>Edit Profile</h1>
    <a href="account.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Account</a>

    <?php if (!empty($errors)): ?> <div class="alert alert-danger"> <ul> <?php foreach ($errors as $error): ?> <li><?php echo htmlspecialchars($error); ?></li> <?php endforeach; ?> </ul> </div> <?php endif; ?>
    <?php if ($success_message): ?> <div class="alert alert-success"><?php echo $success_message; ?></div> <?php endif; ?>

    <?php if ($user_info): ?>
    <form action="edit_profile.php" method="post" enctype="multipart/form-data"> 
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

     
        <div class="form-group">
            <label>Profile Picture</label>
            <div style="display: flex; align-items: center; gap: 20px;">
                 <img src="<?php echo $form_profile_pic; ?>" alt="Current Profile Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 1px solid #ccc;">
                 <div>
                     <input type="file" name="profile_picture" id="profile_picture" class="form-control" style="border: none; padding-left: 0;">
                     <small class="form-text text-muted">Upload a new picture (JPG, PNG, GIF - Max 2MB). Leave blank to keep current picture.</small>
                 </div>
            </div>
            <?php if (isset($errors['image'])):  ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['image']); ?></div><?php endif; ?>
        </div>

        <hr>

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_info['username']); ?>" required>
            <?php if (isset($errors['username'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['username']); ?></div><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user_info['email']); ?>" required>
             <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['email']); ?></div><?php endif; ?>
        </div>
         <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($user_info['full_name']); ?>">
        </div>

        <div class="form-group mt-4">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
             <a href="change_password.php" class="btn btn-secondary">Change Password</a>
        </div>
    </form>
    <?php else: ?> <p>Could not load profile information.</p> <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>