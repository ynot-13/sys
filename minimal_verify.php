<?php


$plainPassword = 'WealthyPass123!'; 


$databaseHash = '$2y$10$YourExactHashFromDatabaseHere...'; 


echo "<!DOCTYPE html><html><head><title>Minimal Verify Test</title></head><body style='font-family: sans-serif;'>";
echo "<h1>Minimal Password Verification Test</h1>";
echo "<p>Testing Plain Password: <code>" . htmlspecialchars($plainPassword) . "</code></p>";
echo "<p>Against Database Hash: <code>" . htmlspecialchars($databaseHash) . "</code></p>";
echo "<hr>";


$hashInfo = password_get_info($databaseHash);
if ($hashInfo['algoName'] === 'unknown') {
    echo "<h2 style='color: red;'>ERROR: The provided HASH string seems INVALID or UNKNOWN format!</h2>";
    echo "<p>Make sure you copied the entire hash correctly from the database (VARCHAR(255) column).</p>";
    $isMatch = false; 
} else {
    echo "<p>Hash format appears valid (Algorithm: " . htmlspecialchars($hashInfo['algoName']) . "). Performing verification...</p>";
   
    $isMatch = password_verify($plainPassword, $databaseHash);
}

echo "<hr>";

if ($isMatch) {
    echo "<h2 style='color: green;'>SUCCESS!</h2>";
    echo "<p>The plain password matches the provided hash.</p>";
    echo "<p>If this works, but the login script still fails, the problem might be how the password or hash is being handled *inside* the login script (e.g., variable corruption, fetching wrong hash).</p>";
} else {
    echo "<h2 style='color: red;'>FAILURE!</h2>";
    echo "<p>The plain password DOES NOT match the provided hash.</p>";
    echo "<p><strong>Possible Reasons:</strong></p>";
    echo "<ul>";
    echo "<li>The HASH you pasted above is incorrect/incomplete/corrupted (Double-check copy/paste).</li>";
    echo "<li>The database column `password_hash` is NOT VARCHAR(255) and the hash got cut off (truncated).</li>";
    echo "<li>The plain password '<code>" . htmlspecialchars($plainPassword) . "</code>' is somehow different from the one used to originally generate the hash.</li>";
    echo "</ul>";
}

echo "</body></html>";
?>