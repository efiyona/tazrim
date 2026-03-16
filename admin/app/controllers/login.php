<?php
    //$updateid = update('table',$id, $_POST);
    //$delete_id = delete('table', $id);
    //$create_id = create('table', $_POST);
?>


<?php
    if (isset($_POST['login'])) {
        unset($_POST['login']);

        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);

        // אימות שדות חובה
        if (empty($phone) || empty($password)) {
            $_SESSION['message'] = "יש להזין טלפון וסיסמה";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "/admin"));
        }

        $user = selectOneFromTables(
            ['managers'],
            ['phone' => $phone, 'password' => $password]
        );

        if ($user) {
            $_SESSION['user'] = $user;
            $_SESSION['login_status'] = true;
            exit(header("location:" . BASE_URL . "/admin/pages"));
        } else {
            $_SESSION['login_status'] = false;
            $_SESSION['message'] = "פרטי משתמש שגויים";
            $_SESSION['type'] = "error";
            exit(header("location:" . BASE_URL . "/admin"));
        }
    }
?>