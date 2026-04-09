<?php
/**
 * מפחית שמירת דף במטמון אגרסיבית ב־iOS Web App — כדי שקישור ל-CSS עם v= יתעדכן.
 */
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
