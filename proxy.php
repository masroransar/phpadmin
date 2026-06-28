<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = '';
if (!empty($_GET['action'])) {
    $action = trim($_GET['action']);
} elseif (!empty($_POST['action'])) {
    $action = trim($_POST['action']);
} elseif (!empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryParams);
    if (!empty($queryParams['action'])) {
        $action = trim($queryParams['action']);
    }
}

if ($action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action parameter']);
    exit;
}

$allowedActions = [
    'teachers',
    'courses',
    'slider',
    'question_categories',
    'questions',
    'lesson_categories',
    'lessons',
    'social_links',
    'user_profile',
    'login',
    'register',
    'validate_code',
];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Action not allowed']);
    exit;
}

$_REQUEST['action'] = $action;
require_once __DIR__ . '/api.php';

