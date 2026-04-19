<?php
/**
 * האם להציג את אזור «התזרים באייפון»:
 * אייפון / אייפד / מק (כולל מקבוק) — לא באנדרואיד.
 */
function tazrim_show_ios_tazrim_panel(string $user_agent): bool
{
    if ($user_agent === '') {
        return false;
    }
    if (stripos($user_agent, 'Android') !== false) {
        return false;
    }

    return (bool) preg_match('/iPhone|iPad|iPod|Macintosh/i', $user_agent);
}
