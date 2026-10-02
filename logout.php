<?php
// Destroy the session and send user back to login page
session_start();
session_destroy();
header("Location: index.php");
exit();
?>
