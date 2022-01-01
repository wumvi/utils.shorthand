<?php
function isDev(): bool
{
    return ($_SERVER['IS_DEV'] ?? 'no') === 'yes' || ($_GET[$_SERVER['DEV_KEY']] ?? '0') === '1';
}

$isDev = isDev();

if ($isDev) {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 'Off');
    error_reporting(E_ERROR | E_PARSE | E_NOTICE);
}



