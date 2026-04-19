// האזנה לאירוע Push מהשרת
self.addEventListener('push', function(event) {
    if (event.data) {
        // קבלת הנתונים מהשרת (כותרת, גוף ההודעה וקישור)
        const data = event.data.json();
        
        const options = {
            body: data.body,
            icon: '/assets/img/logo_small.png', // וודא שהנתיב ללוגו שלך תקין
            badge: '/assets/img/badge.png',     // האייקון הקטן שמופיע בשורת הסטטוס
            vibrate: [100, 50, 100],            // דפוס רטט
            data: {
                url: data.url || '/'            // לאן להפנות את המשתמש בלחיצה
            }
        };

        // הצגת ההתראה על מסך הנעילה
        event.waitUntil(
            self.registration.showNotification(data.title, options)
        );
    }
});

// מה קורה כשלוחצים על ההתראה?
self.addEventListener('notificationclick', function(event) {
    event.notification.close(); // סגירת ההתראה

    // פתיחת האפליקציה בכתובת המבוקשת
    event.waitUntil(
        clients.openWindow(event.notification.data.url)
    );
});