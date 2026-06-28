<?php
define('API_ACTION', 'lesson_categories');
if (!isset($_REQUEST['action'])) {
    $_REQUEST['action'] = API_ACTION;
}
require_once __DIR__ . '/api.php';

