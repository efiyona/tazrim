<?php
  include(ROOT_PATH . "/app/database/db.php");
  date_default_timezone_set('Asia/Jerusalem');

  function check_login_status(){
    if(!isset($_SESSION['login_status']) || !$_SESSION['login_status']){
      unset($_SESSION['login_status']);
      unset($_SESSION['user']);
      $_SESSION['message'] = "כניסה לא מורשית";
      $_SESSION['type'] = "error";
      exit(header("location:" . BASE_URL . "/admin"));
    }
  }

  if (isset($_GET['logout']) && $_GET['logout']) {
    unset($_GET['logout']);
    unset($_SESSION['login_status']);
    unset($_SESSION['user']);
    
    exit(header("location:" . BASE_URL . "/admin"));
  }

    //מיון מערך לפי שדה
  function sortArrayByField(&$array, $field, $direction = 'ASC') {
    usort($array, function ($a, $b) use ($field, $direction) {
    if (!isset($a[$field]) || !isset($b[$field])) {
      return 0; // אם השדה לא קיים, לא מבצעים מיון
    }
    if ($direction === 'ASC') {
      return $a[$field] <=> $b[$field];
    } elseif ($direction === 'DESC') {
      return $b[$field] <=> $a[$field];
    }
      return 0; // במידה והכיוון אינו תקין
    });
  }
?>
