<?php
if (isset($_GET['delete_id'])) {

    $page = "proudacts";

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

if (isset($_POST['add_proudact'])) {
    unset($_POST['add_proudact']);

    if(!empty($_POST['description']) && !empty($_POST['payment_link'])){

        $proudact_data = [
            'description' => $_POST['description'],
            'payment_link' => $_POST['payment_link'],
        ];

        if(!empty($_POST['price'])){
            $proudact_data['price'] = $_POST['price'];
        }

        $proudact_id = create('proudacts', $proudact_data);

        // טיפול בהצלחה או כישלון
        if ($proudact_id) {
            $_SESSION['message'] = "הנתונים נשמרו";
            $_SESSION['type'] = "success";
            exit(header("location:" . BASE_URL . "admin/pages/main/proudacts.php"));
        } else {
            $_SESSION['message'] = "תהליך השמירה לא צלח, אנא נסה שוב";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "admin/pages/mainproudacts.php"));
        }
    }else
    {
        $_SESSION['message'] = "חסרים נתונים";
        $_SESSION['type'] = "error";
        exit(header("location:" . BASE_URL . "admin/pages/mainproudacts.php"));
    }
}

if (isset($_POST['update_proudact'])) {
    unset($_POST['update_proudact']);

    if(!empty($_POST['description']) && !empty($_POST['payment_link'])){

        $proudact_data = [
            'description' => $_POST['description'],
            'payment_link' => $_POST['payment_link'],
        ];

        if(!empty($_POST['price'])){
            $proudact_data['price'] = $_POST['price'];
        }else
        {
            $proudact_data['price'] = null;
        }

        $proudact_id = update('proudacts', $_GET['update_id'], $proudact_data);

        // טיפול בהצלחה או כישלון
        if ($proudact_id) {
            $_SESSION['message'] = "הנתונים נשמרו";
            $_SESSION['type'] = "success";
            exit(header("location:" . BASE_URL . "admin/pages/main/proudacts.php"));
        } else {
            $_SESSION['message'] = "תהליך השמירה לא צלח, אנא נסה שוב";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "admin/pages/main/proudacts.php"));
        }
    }else
    {
        $_SESSION['message'] = "חסרים נתונים";
        $_SESSION['type'] = "error";
        exit(header("location:" . BASE_URL . "admin/pages/main/proudacts.php"));
    }
}
?>