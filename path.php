<?php 
define("ROOT_PATH", realpath(dirname(__FILE__)));

// טוענים את קובץ הסודות כדי להשתמש במשתנים שבו
require_once(ROOT_PATH . '/secrets.php');

// משתמשים בקבוע שהגדרנו בסודות
define("BASE_URL", SITE_URL);

require_once ROOT_PATH . '/app/security/security_headers.php';
require_once ROOT_PATH . '/app/security/rate_limit.php';

tazrim_apply_security_headers();
tazrim_apply_api_rate_limit();
?>