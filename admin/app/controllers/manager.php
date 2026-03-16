<?php
if (isset($_POST['update_manager'])) {
    unset($_POST['update_manager']);

    if(!empty($_POST['first_name']) && !empty($_POST['last_name']) && !empty($_POST['phone'])){

        $manager_data = [
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'phone' => $_POST['phone'],
        ];

        if(!empty($_POST['password'])){
            $manager_data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $manager_id = update('managers', $_SESSION['user']['id'], $manager_data);

        // טיפול בהצלחה או כישלון
        if ($manager_id) {
            $user = selectOne('managers', ['id' => $_SESSION['user']['id']]);
            $_SESSION['user'] = $user;
            $_SESSION['user']['table'] = 'managers';

            $_SESSION['message'] = "הנתונים נשמרו";
            $_SESSION['type'] = "success";
            exit(header("location:" . BASE_URL . "admin/pages/edit.php"));
        } else {
            $_SESSION['message'] = "תהליך השמירה לא צלח, אנא נסה שוב";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "admin/pages/edit.php"));
        }
    }else
    {
        $_SESSION['message'] = "חסרים נתונים";
        $_SESSION['type'] = "error";
        exit(header("location:" . BASE_URL . "admin/pages/edit.php"));
    }
}

if (isset($_POST['update_campaign'])) {
    unset($_POST['update_campaign']);

    if(!empty($_POST['data'])){

        $setup_data = [
            'data' => $_POST['data'],
        ];

        $setup = selectOne('setup', ['description' => "target"]);
        $setup_id = update('setup', $setup['id'], $setup_data);

        // טיפול בהצלחה או כישלון
        if ($setup_id) {
            $_SESSION['message'] = "הנתונים נשמרו";
            $_SESSION['type'] = "success";
            exit(header("location:" . BASE_URL . "admin/pages/edit.php"));
        } else {
            $_SESSION['message'] = "תהליך השמירה לא צלח, אנא נסה שוב";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "admin/pages/edit.php"));
        }
    }else
    {
        $_SESSION['message'] = "חסרים נתונים";
        $_SESSION['type'] = "error";
        exit(header("location:" . BASE_URL . "admin/pages/edit.php"));
    }
}
