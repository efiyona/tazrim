<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/app/includes/auth_check.php'); // מוודא שרק משתמש מחובר ניגש

$home_id = $_SESSION['home_id'];
$user_id = $_SESSION['id'];

// 1. שליפת פרטי הבית
$home_data = selectOne('homes', ['id' => $home_id]);

// 2. שליפת קטגוריות (מחולקות להוצאות והכנסות)
$categories_query = "SELECT * FROM categories WHERE home_id = $home_id ORDER BY type ASC, name ASC";
$categories_result = mysqli_query($conn, $categories_query);
$expenses_cats = [];
$income_cats = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    if ($cat['type'] == 'expense') {
        $expenses_cats[] = $cat;
    } else {
        $income_cats[] = $cat;
    }
}

// 3. שליפת פעולות קבועות פעילות
$recurring_query = "SELECT r.*, c.name as cat_name, c.icon as cat_icon 
                    FROM recurring_transactions r 
                    LEFT JOIN categories c ON r.category = c.id 
                    WHERE r.home_id = $home_id AND r.is_active = 1 
                    ORDER BY r.day_of_month ASC";
$recurring_result = mysqli_query($conn, $recurring_query);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול הבית | התזרים</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/user.css">
    <style>
        /* תוספות עיצוב ספציפיות לדף ניהול הבית */
        .management-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 20px;
            padding-bottom: 80px; /* מקום למובייל */
        }
        @media (min-width: 768px) {
            .management-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .full-width-card {
                grid-column: span 2;
            }
        }
        .join-code-box {
            background-color: var(--main-light);
            border: 2px dashed var(--main);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .join-code-box h2 {
            font-size: 2rem;
            color: var(--main);
            letter-spacing: 5px;
            margin: 10px 0;
            user-select: all;
        }
        .cat-list-item, .recurring-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background-color: var(--gray);
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .action-btns button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: 0.2s;
        }
        .btn-edit { color: var(--main); }
        .btn-delete { color: var(--error); }
        .action-btns button:hover { background-color: white; }
    </style>
</head>
<body>

    <?php include(ROOT_PATH . '/app/includes/sidebar.php'); ?>

    <main class="main-content">
        <header class="top-bar">
            <div class="user-greeting">
                <h2>ניהול הבית</h2>
                <p>הגדרות, תקציבים ופעולות קבועות</p>
            </div>
            <div class="user-profile">
                <div class="avatar"><i class="fa-solid fa-gear"></i></div>
            </div>
        </header>

        <div class="management-grid">
            
            <div class="card">
                <div class="card-header">
                    <h3>פרטי הבית</h3>
                </div>
                <div class="join-code-box">
                    <p style="font-weight: 600; font-size: 0.9rem;">קוד ההצטרפות לבית זה:</p>
                    <h2><?php echo $home_data['join_code']; ?></h2>
                    <p style="font-size: 0.8rem; color: #666;">שלח את הקוד הזה לשותפים/בני זוג כדי שיצטרפו אליך.</p>
                </div>
                
                <form id="update-home-form">
                    <div class="input-group">
                        <label>שם הבית</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-house"></i>
                            <input type="text" name="home_name" value="<?php echo htmlspecialchars($home_data['name']); ?>" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>יתרת בנק התחלתית (₪)</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-building-columns"></i>
                            <input type="number" step="0.01" name="initial_balance" value="<?php echo $home_data['initial_balance']; ?>" required>
                        </div>
                    </div>
                    <button type="button" class="btn-primary" onclick="updateHomeDetails()">שמור שינויים</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>פעולות קבועות</h3>
                </div>
                <div class="recurring-list">
                    <?php if (mysqli_num_rows($recurring_result) > 0): ?>
                        <?php while ($rec = mysqli_fetch_assoc($recurring_result)): ?>
                            <div class="recurring-list-item">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; color: var(--text-light);">
                                        <i class="fa-solid <?php echo $rec['cat_icon'] ?: 'fa-tag'; ?>"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text);"><?php echo $rec['description']; ?></div>
                                        <div style="font-size: 0.8rem; color: #666;">כל <?php echo $rec['day_of_month']; ?> בחודש | <span style="color: <?php echo $rec['type'] == 'expense' ? 'var(--error)' : 'var(--success)'; ?>; font-weight: 600;"><?php echo number_format($rec['amount']); ?> ₪</span></div>
                                    </div>
                                </div>
                                <div class="action-btns">
                                    <button class="btn-delete" onclick="deleteRecurring(<?php echo $rec['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #777; padding: 20px;">אין פעולות קבועות כרגע.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card full-width-card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>קטגוריות ותקציב חודשי</h3>
                    <button class="btn-primary" style="padding: 5px 15px; font-size: 0.9rem; border-radius: 8px;" onclick="openAddCategoryModal()">
                        <i class="fa-solid fa-plus"></i> קטגוריה חדשה
                    </button>
                </div>
                
                <h4 style="margin: 15px 0 10px; color: var(--error);">הוצאות</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                    <?php foreach ($expenses_cats as $cat): ?>
                        <div class="cat-list-item">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid <?php echo $cat['icon'] ?: 'fa-tag'; ?>" style="color: var(--text-light); width: 20px; text-align: center;"></i>
                                <span style="font-weight: 600;"><?php echo $cat['name']; ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.85rem; color: #666;">תקציב: <strong style="color: var(--text);"><?php echo number_format($cat['budget_limit']); ?> ₪</strong></span>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="openEditCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', <?php echo $cat['budget_limit']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h4 style="margin: 25px 0 10px; color: var(--success);">הכנסות</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                    <?php foreach ($income_cats as $cat): ?>
                        <div class="cat-list-item">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid <?php echo $cat['icon'] ?: 'fa-tag'; ?>" style="color: var(--text-light); width: 20px; text-align: center;"></i>
                                <span style="font-weight: 600;"><?php echo $cat['name']; ?></span>
                            </div>
                            <div class="action-btns">
                                <button class="btn-edit" onclick="openEditCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', 0)"><i class="fa-solid fa-pen"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </main>

    <?php include(ROOT_PATH . '/app/includes/bottom_nav.php'); ?>

    <script>
        // הכנה לפונקציות שייכתבו בשלב הבא
        function updateHomeDetails() {
            alert('יעודכן בקרוב!');
        }
        function deleteRecurring(id) {
            if(confirm('האם אתה בטוח שברצונך למחוק פעולה קבועה זו?')) {
                alert('יימחק בקרוב! ID: ' + id);
            }
        }
        function openAddCategoryModal() {
            alert('פופאפ הוספת קטגוריה ייפתח כאן');
        }
        function openEditCategoryModal(id, name, budget) {
            alert('עריכת קטגוריה: ' + name + ' | תקציב: ' + budget);
        }
    </script>
</body>
</html>