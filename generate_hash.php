<?php
$passwordToHash = 'WealthyPass123!'; 
$hashedPassword = password_hash($passwordToHash, PASSWORD_DEFAULT);

echo "Password: " . htmlspecialchars($passwordToHash) . "<br>";
echo "Generated Hash: <textarea rows='3' cols='70' readonly>" . htmlspecialchars($hashedPassword) . "</textarea>";
echo "<br><br>Kopyahin mo nang eksakto ang nasa loob ng text box.";
?>