<?php
/**
 * כתובת מלאה ל-user.css עם פרמטר גרסה לפי זמן שינוי הקובץ —
 * מונע מטמון CSS ישן ב־Safari / אפליקציית מסך הבית (PWA).
 */
if (!function_exists('tazrim_user_css_href')) {
    function tazrim_user_css_href() {
        $path = ROOT_PATH . '/assets/css/user.css';
        $v = is_file($path) ? filemtime($path) : time();
        return rtrim(BASE_URL, '/') . '/assets/css/user.css?v=' . $v;
    }
}
