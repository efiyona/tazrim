<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

$home_id = $_SESSION['home_id'];
$categories = $_POST['categories'] ?? [];

if (empty($categories)) {
    echo json_encode(['status' => 'error', 'message' => 'אנא בחרו לפחות חנות אחת.']);
    exit();
}

$added = 0;
// המערך של הקטגוריות מגיע כ- JSON מחרוזת מכל פריט
foreach ($categories as $cat_json) {
    $cat = json_decode($cat_json, true);
    if ($cat && !empty($cat['name'])) {
        $name = mysqli_real_escape_string($conn, trim($cat['name']));
        $icon = mysqli_real_escape_string($conn, trim($cat['icon']));
        
        // סידור (sort_order) אוטומטי לפי סדר ההוספה
        $sort_order = $added + 1;
        
        $query = "INSERT INTO shopping_categories (home_id, name, icon, sort_order) VALUES ($home_id, '$name', '$icon', $sort_order)";
        if (mysqli_query($conn, $query)) {
            $added++;
        }
    }
}

if ($added > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה בשמירת החנויות.']);
}
?>