<?php

$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'tazrim';

$conn = new mysqli($host, $user, $pass, $db_name);

if($conn->connect_error){
    die('Data Base Connection error: ' . $conn->connect_error);
}

date_default_timezone_set('Asia/Jerusalem');
