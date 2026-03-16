<section class="navbar menu--vertical flex--center" id="menu_vertical">
    <div class="close--btn" id="close_btn" onclick="menu_vertical()">
        <i class="fa-solid fa-xmark"></i>
    </div>
    <ul class="navbar--warp flex--start--c gap30">
        <li class="dropdown--vertical flex--center dropdown-current">
            <a href="<?php echo BASE_URL;?>admin/pages/index.php" class="flex--center gap10">
                <i class="fa-solid fa-house icon-dropdown"></i>
                <div class="text-dropdown">ראשי</div>
            </a>
        </li>
        <li class="dropdown--vertical dropdown flex--start--c">
            <a href="#" class="flex--start gap10">
                <i class="fa-regular fa-money-bill-1"></i>
                <div class="text-dropdown">תרומות</div>
                <i class="fa-solid fa-chevron-down icon-dropdown"></i>
            </a>
            <ul class="dropdown--menu">
                <ul class="dropdown--menu--items flex--start--c gap5">
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
        <li class="dropdown--vertical dropdown flex--start--c">
            <a href="#" class="flex--start gap10">
                <i class="fa-solid fa-link"></i>
                <div class="text-dropdown">כפתורים</div>
                <i class="fa-solid fa-chevron-down icon-dropdown"></i>
            </a>
            <ul class="dropdown--menu">
                <ul class="dropdown--menu--items flex--start--c gap5">
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

<script>
    function menu_vertical() {
        var element = document.getElementById("menu_vertical");
        element.classList.toggle("menu--vertical--show");
    }
</script>