<?php


require_once __DIR__ . '/config.php';



$mysqli = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);


if ($mysqli->connect_error) {
    error_log("DB Connection Error (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
    die("DATABASE_CONNECTION_ERROR"); 
}


if (!$mysqli->set_charset("utf8mb4")) {
    error_log("DB Charset Error: " . $mysqli->error);
}



?>