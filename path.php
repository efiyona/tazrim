<?php 
define("ROOT_PATH", realpath(dirname(__FILE__)));

// טוענים את קובץ הסודות כדי להשתמש במשתנים שבו
require_once(ROOT_PATH . '/secrets.php');

// משתמשים בקבוע שהגדרנו בסודות
define("BASE_URL", SITE_URL);
?>