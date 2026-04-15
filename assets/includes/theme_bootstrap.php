<?php
if (!defined('BASE_URL')) {
    return;
}
if (isset($GLOBALS['tazrim_theme_bootstrap_rendered']) && $GLOBALS['tazrim_theme_bootstrap_rendered'] === true) {
    return;
}
$GLOBALS['tazrim_theme_bootstrap_rendered'] = true;

$allowed_theme_preferences = ['light', 'dark', 'system'];
$session_theme_preference = $_SESSION['theme_preference'] ?? 'light';
if (!in_array($session_theme_preference, $allowed_theme_preferences, true)) {
    $session_theme_preference = 'light';
}

$is_logged_in = isset($_SESSION['id']) ? 'true' : 'false';
$save_theme_url = rtrim(BASE_URL, '/') . '/app/ajax/save_theme_preference.php';
?>
<meta name="color-scheme" content="light dark">
<script>
(function() {
    var storageKey = 'tazrim_theme_preference';
    var allowed = { light: true, dark: true, system: true };
    var appDefaultPreference = 'light';
    var serverPreference = <?php echo json_encode($session_theme_preference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var isLoggedIn = <?php echo $is_logged_in; ?>;
    var saveUrl = <?php echo json_encode($save_theme_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function sanitizePreference(value) {
        if (typeof value !== 'string') {
            return null;
        }
        return allowed[value] ? value : null;
    }

    function readStoredPreference() {
        try {
            return sanitizePreference(window.localStorage.getItem(storageKey));
        } catch (err) {
            return null;
        }
    }

    function writeStoredPreference(value) {
        try {
            if (!value || !allowed[value]) {
                window.localStorage.removeItem(storageKey);
                return;
            }
            window.localStorage.setItem(storageKey, value);
        } catch (err) {
            // Swallow storage errors (private mode / blocked storage).
        }
    }

    function resolveTheme(preference) {
        if (preference === 'dark') {
            return 'dark';
        }
        if (preference === 'light') {
            return 'light';
        }
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    function applyThemeState(preference) {
        var resolved = resolveTheme(preference);
        var html = document.documentElement;
        html.setAttribute('data-theme-preference', preference);
        html.setAttribute('data-theme', resolved);
        html.style.colorScheme = resolved;
        return resolved;
    }

    function persistPreference(preference) {
        if (!isLoggedIn || !saveUrl) {
            return Promise.resolve({ status: 'local' });
        }
        var payload = new URLSearchParams();
        payload.set('theme_preference', preference);
        return fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: payload.toString()
        }).then(function(res) {
            return res.json();
        });
    }

    var localPreference = readStoredPreference();
    var initialPreference = sanitizePreference(serverPreference);
    if (!initialPreference) {
        initialPreference = localPreference || appDefaultPreference;
    }
    if (isLoggedIn) {
        writeStoredPreference(initialPreference);
    } else if (!localPreference) {
        writeStoredPreference(initialPreference);
    }

    var currentPreference = initialPreference;
    var currentResolvedTheme = applyThemeState(currentPreference);
    var mediaQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function notifyThemeChange() {
        document.dispatchEvent(new CustomEvent('tazrim:theme-changed', {
            detail: {
                preference: currentPreference,
                theme: currentResolvedTheme
            }
        }));
    }

    if (mediaQuery) {
        var handleSystemThemeChange = function() {
            if (currentPreference !== 'system') {
                return;
            }
            currentResolvedTheme = applyThemeState(currentPreference);
            notifyThemeChange();
        };
        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleSystemThemeChange);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(handleSystemThemeChange);
        }
    }

    window.tazrimTheme = {
        getPreference: function() {
            return currentPreference;
        },
        getResolvedTheme: function() {
            return currentResolvedTheme;
        },
        setPreference: function(nextPreference, options) {
            var sanitized = sanitizePreference(nextPreference);
            if (!sanitized) {
                return Promise.reject(new Error('invalid_theme_preference'));
            }

            var shouldPersist = !options || options.persist !== false;
            currentPreference = sanitized;
            currentResolvedTheme = applyThemeState(currentPreference);
            writeStoredPreference(currentPreference);
            notifyThemeChange();

            if (!shouldPersist) {
                return Promise.resolve({ status: 'local' });
            }
            return persistPreference(currentPreference).then(function(response) {
                if (!response || response.status !== 'success') {
                    throw new Error((response && response.message) || 'theme_save_failed');
                }
                return response;
            });
        }
    };
})();
</script>
