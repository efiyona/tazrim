<?php
$pageTitle = isset($pageTitle) ? $pageTitle : 'ניהול מערכת';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | התזרים</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/9a47092d09.js" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Assistant', 'system-ui', 'sans-serif'] },
                },
            },
        };
    </script>
    <style>[x-cloak]{display:none!important}</style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tazrim_admin_asset_href('admin/assets/css/admin.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tazrim_admin_asset_href('admin/assets/css/tazrim-dialog.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo htmlspecialchars(tazrim_admin_asset_href('assets/js/tazrim_dialogs.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body class="antialiased bg-gray-200 overflow-x-hidden">
