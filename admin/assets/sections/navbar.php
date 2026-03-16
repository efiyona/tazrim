<?php require ROOT_PATH . '/admin/assets/sections/notification.php'; ?>

<section class="navbar">
    <div class="navbar--container flex--space--between">
        <div class="navbar--logo flex--start gap10">
            <div class="logo--icon img--box">
                <?php $school_logo = selectOne('photos', ['id' => 1]); ?>
                <a href="<?php echo BASE_URL . "admin/pages"; ?>"><img src="<?php echo BASE_URL . "/assets/images/" . $school_logo['image_folder'] . "/" . $school_logo['image_name']; ?>" alt="<?php echo $school_logo['alt']; ?>"></a>
            </div>
            <div class="navbar--logo--name"><?php echo $_SESSION['user']['first_name'] . " " . $_SESSION['user']['last_name']; ?></div>
        </div>
        <ul class="navbar--warp flex--end gap10">
            <li class="dropdown flex--center">
                <a href="#none"><i class="fa-solid fa-bars-staggered icon-dropdown rtl--icon" id="open--navbar--mobile" onclick="menu_vertical()"></i></a>
            </li>
            <li class="dropdown flex--center">
                <a href="#none" class="alert_bell">
                    <?php
                    /* הודעות מנהל
                        $messages = selectAll('messages', ['recipient_id' => $admin_data['id'], 'publish' => 0]);
                        if(count($messages) > 0){
                            echo '<div class="alert_circle"></div>';
                        }
                    */
                    ?>
                    <i class="fa-regular fa-bell icon-dropdown"></i>
                </a>
                <ul class="dropdown--menu">
                    <ul class="dropdown--menu--items flex--center--c alert_dropdown">
                        <?php
                            if(!isset($messages) || count($messages) == 0){
                                echo "- אין הודעות -";
                            }else{
                                foreach(array_reverse($messages) as $message){ $send_data = selectOne('teams', ['id' => $message['send_id']]);
                        ?>
                                <li class="flex--center">
                                    <a href="" class="dropdown--item">
                                        <div class="flex--start gap10">
                                            <i class="fa-solid fa-circle-<?php if($message['type']){echo "question";}else{echo "exclamation";}?> icon-dropdown-menu"></i>
                                            <div class="flex--start--c">
                                                <span><?php echo $message['data']; ?></span>
                                                <div class="alrt_sender"><?php echo date('d.m.y h:i', strtotime($message['date']))  . " | " . $send_data['first_name'] . " " . $send_data['last_name']; ?></div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                        <?php }} ?>
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li class="flex--center">
                            <a href="" class="dropdown--item">
                                <div class="flex--start gap10">
                                    <i class="fa-regular fa-rectangle-list"></i>
                                    <span>לוח הודעות</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                </ul>
            </li>
            <li class="dropdown flex--center">
                <a href="#none">
                    <div class="avater img--box">
                        <?php $profile_img = selectOne('photos', ['id' => 1]); ?>
                        <img src="<?php echo BASE_URL . "/assets/images/" . $profile_img['image_folder'] . "/" . $profile_img['image_name']; ?>" alt="<?php echo $profile_img['alt']; ?>">
                    </div>
                </a>
                <ul class="dropdown--menu">
                    <ul class="dropdown--menu--items flex--center--c">
                        <li class="flex--center">
                            <a href="#" class="dropdown--item flex--start gap15">
                                <div class="item--avater img--box flex--start">
                                    <img src="<?php echo BASE_URL . "/assets/images/" . $profile_img['image_folder'] . "/" . $profile_img['image_name']; ?>" alt="<?php echo $profile_img['alt']; ?>">
                                </div>
                                <div class="dropdown--item--text flex--start--c">
                                    <span><?php echo $_SESSION['user']['first_name'] . " " . $_SESSION['user']['last_name']; ?></span>
                                    מנהל הקמפיין
                                </div>
                            </a>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li class="flex--center">
                            <a href="<?php echo BASE_URL . 'admin/pages/edit.php';?>" class="dropdown--item">
                                <div class="flex--start gap10">
                                    <i class="fa-solid fa-user-pen icon-dropdown-menu"></i>
                                    <span>עריכה</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li class="flex--center">
                            <a href="<?php echo BASE_URL; ?>admin/index.php?logout=true" class="dropdown--item">
                                <div class="flex--start gap10">
                                    <i class="fa-solid fa-arrow-right-from-bracket icon-dropdown-menu"></i>
                                    <span>התנתקות</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                </ul>
            </li>
        </ul>
    </div>
</section>