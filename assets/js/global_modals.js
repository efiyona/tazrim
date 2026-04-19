document.addEventListener('DOMContentLoaded', function() {
    
    // הפונקציה שנועלת/משחררת את הגלילה
    function updateBodyScroll() {
        const openModals = document.querySelectorAll('.modal[style*="display: block"]');
        if (openModals.length > 0) {
            document.body.classList.add('no-scroll');
        } else {
            document.body.classList.remove('no-scroll');
        }
    }

    // משקיף העיצוב (בודק מתי פופאפ מקבל display: block)
    const modalObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === "style") {
                updateBodyScroll();
            }
        });
    });

    // פונקציה שמחברת את המשקיף לכל מודאל שקיים כרגע בדף
    function observeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            if (!modal.dataset.isObserved) { // מוודא שלא נחבר פעמיים לאותו אחד
                modalObserver.observe(modal, { attributes: true });
                modal.dataset.isObserved = 'true';
            }
        });
    }

    // הפעלה ראשונית
    observeAllModals();

    // משקיף על כל המסך (Body): למקרה שבעתיד תטען פופאפ חדש בעזרת AJAX, 
    // המערכת תזהה את זה ותחבר גם אליו את מנגנון הנעילה אוטומטית!
    const bodyObserver = new MutationObserver(function() {
        observeAllModals();
    });
    bodyObserver.observe(document.body, { childList: true, subtree: true });

});