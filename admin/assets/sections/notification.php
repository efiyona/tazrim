<div id="notification-container"></div>

<script>
    // התראות מערכת
    function addNotification(type, text) {
        let title;
        if (type === "success") {
            title = "הצלחה";
        } else if (type === "error") {
            title = "שגיאה";
        } else if (type === "warning") {
            title = "שים לב";
        }

        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        notification.classList.add('notification', type);
        notification.innerHTML = `
            <button class="close--notification--btn">&times;</button>
            <div>
                <span>${title}</span> - ${text}
            </div>
        `;

        // הוספת מאזין לכפתור הסגירה
        const closeButton = notification.querySelector('.close--notification--btn');
        closeButton.addEventListener('click', () => removeNotification(notification));

        container.appendChild(notification);

        // הוספת Timeout להסרת ההתראה
        setTimeout(() => {
            removeNotification(notification);
        }, 5000);
    }

    function removeNotification(notification) {
        notification.style.animation = 'slideOut 0.5s ease-in';
        notification.addEventListener('animationend', () => {
            notification.remove();
        });
    }

    <?php
    if (isset($_SESSION['type']) && isset($_SESSION['message'])) {
        echo "addNotification('" . htmlspecialchars($_SESSION['type'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') . "');";
        unset($_SESSION['message']);
    }
    ?>
</script>
