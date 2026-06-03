<?php
// logout.php
session_start();
session_destroy();
header('Location: /otel/login.php');
exit;