<?php

?> 
    </main>
    <script src="<?php echo BASE_URL; ?>js/script.js?v=<?php echo time(); ?>"></script>
    
</body>
</html>
<?php
if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
    $mysqli->close();
}
?>