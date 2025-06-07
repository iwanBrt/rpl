<?php
session_start();

// Hapus semua data session
session_destroy();

// Redirect ke halaman login
header("Location: auth.php");
exit();
?> 