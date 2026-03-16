<?php
if (isset($_GET['delete_id'])) {

    $page = "transactions";

    if(!empty($_GET['delete_id'])){

        $delete_id = delete($page, $_GET['delete_id']);

        unset($_GET['delete_id']);

        // טיפול בהצלחה או כישלון
        if ($delete_id) {
            $_SESSION['message'] = "מחיקה בוצעה";
            $_SESSION['type'] = "success";
            exit(header("location:" . BASE_URL . "admin/pages/main/" . $page . ".php"));
        } else {
            $_SESSION['message'] = "תהליך המחיקה לא צלח, אנא נסה שוב";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "admin/pages/main/" . $page . ".php"));
        }
    }else
    {
        $_SESSION['message'] = "חסרים נתונים";
        $_SESSION['type'] = "error";
        exit(header("location:" . BASE_URL . "admin/pages/main/" . $page . ".php"));
    }
}
?>