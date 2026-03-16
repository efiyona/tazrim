<section class="navbar menu--horizontal flex--start">
    <ul class="navbar--warp flex--start gap10">
        <li class="dropdown flex--center dropdown-current">
            <a href="<?php echo BASE_URL;?>admin/pages/index.php" class="flex--center gap10">
                <i class="fa-solid fa-house icon-dropdown"></i>
                <div class="text-dropdown">ראשי</div>
            </a>
        </li>
        <li class="dropdown dropdown--hover flex--center">
            <a href="#" class="flex--center gap10">
                <i class="fa-regular fa-money-bill-1"></i>
                <div class="text-dropdown">תרומות</div>
                <i class="fa-solid fa-chevron-down icon-dropdown"></i>
            </a>
            <ul class="dropdown--menu--hover">
                <ul class="dropdown--menu--items flex--center--c">
                    <li class="flex--center">
                        <a href="<?php echo BASE_URL;?>admin/pages/main/transactions.php" class="dropdown--item">
                            <div class="flex--start gap10">
                                <i class="fa-solid fa-list-check icon-dropdown-menu"></i>
                                <span>רשימת תרומות</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </ul>
        </li>
        <li class="dropdown dropdown--hover flex--center">
            <a href="#" class="flex--center gap10">
                <i class="fa-solid fa-link"></i>
                <div class="text-dropdown">כפתורים</div>
                <i class="fa-solid fa-chevron-down icon-dropdown"></i>
            </a>
            <ul class="dropdown--menu--hover">
                <ul class="dropdown--menu--items flex--center--c">
                    <li class="flex--center">
                        <a href="<?php echo BASE_URL;?>admin/pages/main/proudacts.php" class="dropdown--item">
                            <div class="flex--start gap10">
                                <i class="fa-solid fa-list-check icon-dropdown-menu"></i>
                                <span>רשימת כפתורים</span>
                            </div>
                        </a>
                    </li>
                </ul>
            </ul>
        </li>
    </ul>
</section>