<?php
// logout.php (נמצא בתיקיית tazrim/ המרכזית)
require('path.php'); 

session_start();
session_destroy();

// הפניה לדף הלוגין שנמצא בתוך תיקיית pages
header('location: ' . BASE_URL . 'pages/login.php');
exit();