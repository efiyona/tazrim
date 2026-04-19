<?php
// משיכת נתוני ההסבר ממסד הנתונים לפי המפתח שנשלח
$info_data = selectOne('info_messages', ['msg_key' => $info_key ?? '']);

// הגדרת ערכי ברירת מחדל במקרה שהמפתח לא נמצא במסד
$title = $info_data ? $info_data['title'] : 'מידע חסר';
$content = $info_data ? $info_data['content'] : 'לא נמצא הסבר מקושר במערכת.';
?>

<span style="display: inline-flex; align-items: center; gap: 6px; width: fit-content;">
    <span class="info-trigger" 
          style="margin-right: 0;"
          data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>" 
          data-content="<?php echo htmlspecialchars($content, ENT_QUOTES); ?>">
        <i class="fa-solid fa-info"></i>
    </span>
</span>