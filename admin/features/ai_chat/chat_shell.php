<?php
declare(strict_types=1);

/**
 * תוכן HTML של מודאל הצ'אט בלבד — לטעינה עצלה (fetch) בלי JS/CSS.
 */
require_once dirname(__DIR__, 3) . '/path.php';
require_once __DIR__ . '/bootstrap.php';

admin_ai_chat_render_modal();
