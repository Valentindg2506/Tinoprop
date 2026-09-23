<?php
/**
 * Lightweight i18n helper.
 * loadLang($lang) must be called once per request (done in header.php).
 * __($key) returns the translated string or the key itself as fallback.
 */

if (!function_exists('loadLang')) {
    function loadLang($lang) {
        global $LANG;
        $allowed = ['es', 'en'];
        if (!in_array($lang, $allowed)) {
            $lang = 'es';
        }
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        $LANG = file_exists($file) ? include $file : [];
    }
}

if (!function_exists('__')) {
    function __($key) {
        global $LANG;
        if (!isset($LANG) || !is_array($LANG)) {
            return $key;
        }
        return $LANG[$key] ?? $key;
    }
}
