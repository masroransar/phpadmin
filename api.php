<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../db.php';

$action = trim($_REQUEST['action'] ?? '');

switch ($action) {
    case 'teachers':
        fetchTeachers();
        break;
    case 'courses':
        fetchCourses();
        break;
    case 'slider':
        fetchSliders();
        break;
    case 'question_categories':
        fetchQuestionCategories();
        break;
    case 'questions':
        fetchQuestions();
        break;
    case 'lesson_categories':
        fetchLessonCategories();
        break;
    case 'lessons':
        fetchLessons();
        break;
    case 'social_links':
        fetchSocialLinks();
        break;
    case 'user_profile':
        fetchUserProfile();
        break;
    case 'login':
        loginUser();
        break;
    case 'register':
        registerUser();
        break;
    case 'validate_code':
        validateCode();
        break;
    default:
        respondJson(['success' => false, 'message' => 'Action not found: ' . $action]);
        break;
}

function fetchTeachers() {
    global $pdo;
    $stmt = $pdo->prepare('SELECT t.id, t.name, t.title, t.school, t.sales_count, t.bio, t.image_url, (
        SELECT COUNT(*) FROM courses c WHERE c.teacher_id = t.id
    ) AS courses_count FROM teachers t ORDER BY t.id DESC');
    $stmt->execute();
    $teachers = $stmt->fetchAll();
    respondJson($teachers);
}

function fetchCourses() {
    global $pdo;
    $teacherId = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : 0;

    if ($teacherId > 0) {
        $stmt = $pdo->prepare('SELECT c.id, c.title, c.description, c.image_url, c.teacher_id, t.name AS teacher_name, t.image_url AS teacher_image FROM courses c JOIN teachers t ON c.teacher_id = t.id WHERE c.teacher_id = ? ORDER BY c.id DESC');
        $stmt->execute([$teacherId]);
    } else {
        $stmt = $pdo->prepare('SELECT c.id, c.title, c.description, c.image_url, c.teacher_id, t.name AS teacher_name, t.image_url AS teacher_image FROM courses c JOIN teachers t ON c.teacher_id = t.id ORDER BY c.id DESC');
        $stmt->execute();
    }

    $courses = [];
    while ($course = $stmt->fetch()) {
        $fileStmt = $pdo->prepare('SELECT id, title, file_type, file_url FROM course_files WHERE course_id = ? ORDER BY id');
        $fileStmt->execute([$course['id']]);
        $course['files'] = $fileStmt->fetchAll();
        $courses[] = $course;
    }

    respondJson($courses);
}

function fetchSliders() {
    global $pdo;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $rootPath = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
    if ($rootPath === DIRECTORY_SEPARATOR || $rootPath === '\\' || $rootPath === '.') {
        $rootPath = '';
    }
    $baseUrl = rtrim($scheme . '://' . $host . $rootPath, '/');

    $stmt = $pdo->prepare('SELECT id, title, image_url FROM sliders WHERE active = 1 ORDER BY id DESC');
    $stmt->execute();
    $sliders = $stmt->fetchAll();

    foreach ($sliders as &$slider) {
        if (empty($slider['image_url'])) {
            continue;
        }
        $imageUrl = trim($slider['image_url']);
        if (preg_match('/^https?:\/\//i', $imageUrl)) {
            $slider['image_url'] = $imageUrl;
        } elseif (strpos($imageUrl, '//') === 0) {
            $slider['image_url'] = $scheme . ':' . $imageUrl;
        } else {
            $slider['image_url'] = $baseUrl . '/' . ltrim($imageUrl, '/');
        }
    }
    unset($slider);

    respondJson($sliders);
}

function fetchQuestionCategories() {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, name, thumbnail_url FROM question_categories ORDER BY id DESC');
    $stmt->execute();
    respondJson($stmt->fetchAll());
}

function fetchQuestions() {
    global $pdo;
    $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
    $query = 'SELECT q.id, q.category_id, q.title, q.description, c.name AS category_name FROM twelfth_questions q LEFT JOIN question_categories c ON q.category_id = c.id';
    $params = [];
    if ($categoryId > 0) {
        $query .= ' WHERE q.category_id = ?';
        $params[] = $categoryId;
    }
    $query .= ' ORDER BY q.id DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $questions = [];
    while ($question = $stmt->fetch()) {
        $fileStmt = $pdo->prepare('SELECT id, title, file_type, file_url FROM twelfth_question_files WHERE question_id = ? ORDER BY id');
        $fileStmt->execute([$question['id']]);
        $question['files'] = $fileStmt->fetchAll();
        $questions[] = $question;
    }

    respondJson($questions);
}

function fetchLessonCategories() {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, name, thumbnail_url FROM lesson_categories ORDER BY id DESC');
    $stmt->execute();
    respondJson($stmt->fetchAll());
}

function fetchSocialLinks() {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, `key`, label, url, sort_order FROM social_links ORDER BY sort_order, id DESC');
    $stmt->execute();
    respondJson($stmt->fetchAll());
}

function fetchLessons() {
    global $pdo;
    $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
    $query = 'SELECT l.id, l.category_id, l.title, l.description, c.name AS category_name FROM school_lessons l LEFT JOIN lesson_categories c ON l.category_id = c.id';
    $params = [];
    if ($categoryId > 0) {
        $query .= ' WHERE l.category_id = ?';
        $params[] = $categoryId;
    }
    $query .= ' ORDER BY l.id DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $lessons = [];
    while ($lesson = $stmt->fetch()) {
        $fileStmt = $pdo->prepare('SELECT id, title, file_type, file_url FROM school_lesson_files WHERE lesson_id = ? ORDER BY id');
        $fileStmt->execute([$lesson['id']]);
        $lesson['files'] = $fileStmt->fetchAll();
        $lessons[] = $lesson;
    }

    respondJson($lessons);
}

function fetchUserProfile() {
    global $pdo;
    $userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($userId <= 0) {
        respondJson(['success' => false, 'message' => 'معرف المستخدم غير صالح.']);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, phone, country FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        respondJson(['success' => false, 'message' => 'المستخدم غير موجود.']);
    }

    $codeStmt = $pdo->prepare('SELECT code, type, duration_hours FROM codes WHERE assigned_user_id = ? LIMIT 1');
    $codeStmt->execute([$userId]);
    $codeRow = $codeStmt->fetch(PDO::FETCH_ASSOC);

    $userRow['assigned_code'] = $codeRow ? [
        'code' => $codeRow['code'],
        'type' => $codeRow['type'],
        'duration_hours' => (int) $codeRow['duration_hours'],
    ] : [];

    respondJson(['success' => true, 'user' => $userRow]);
}

function loginUser() {
    global $pdo;
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        respondJson(['success' => false, 'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان.']);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow || !password_verify($password, $userRow['password_hash'])) {
        respondJson(['success' => false, 'message' => 'بيانات الدخول غير صحيحة.']);
    }

    $assignedCode = null;
    $codeStmt = $pdo->prepare('SELECT * FROM codes WHERE assigned_user_id = ? LIMIT 1');
    $codeStmt->execute([$userRow['id']]);
    $codeRow = $codeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$codeRow) {
        $unassignedStmt = $pdo->prepare('SELECT * FROM codes WHERE status = ? AND assigned_user_id IS NULL LIMIT 1');
        $unassignedStmt->execute(['unused']);
        $codeRow = $unassignedStmt->fetch(PDO::FETCH_ASSOC);
        if ($codeRow) {
            $assignStmt = $pdo->prepare('UPDATE codes SET assigned_user_id = ?, assigned_at = NOW() WHERE id = ?');
            $assignStmt->execute([$userRow['id'], $codeRow['id']]);
        }
    }

    if ($codeRow) {
        $assignedCode = [
            'code' => $codeRow['code'],
            'type' => $codeRow['type'],
            'duration_hours' => (int) $codeRow['duration_hours'],
        ];
    }

    $user = [
        'id' => (int) $userRow['id'],
        'name' => $userRow['name'],
        'email' => $userRow['email'],
        'phone' => $userRow['phone'] ?? '',
        'country' => $userRow['country'] ?? '',
        'assigned_code' => $assignedCode ?: [],
    ];

    respondJson(['success' => true, 'message' => 'تم تسجيل الدخول بنجاح.', 'user' => $user]);
}

function registerUser() {
    global $pdo;
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $phone === '' || $country === '') {
        respondJson(['success' => false, 'message' => 'جميع الحقول مطلوبة.']);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respondJson(['success' => false, 'message' => 'البريد الإلكتروني غير صالح.']);
    }

    if (strlen($password) < 6) {
        respondJson(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respondJson(['success' => false, 'message' => 'هذا البريد مسجل بالفعل.']);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, phone, country) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $passwordHash, $phone, $country]);
    $userId = $pdo->lastInsertId();

    $assignedCode = null;
    $codeStmt = $pdo->prepare('SELECT * FROM codes WHERE status = ? AND assigned_user_id IS NULL LIMIT 1');
    $codeStmt->execute(['unused']);
    $codeRow = $codeStmt->fetch(PDO::FETCH_ASSOC);
    if ($codeRow) {
        $assignStmt = $pdo->prepare('UPDATE codes SET assigned_user_id = ?, assigned_at = NOW() WHERE id = ?');
        $assignStmt->execute([$userId, $codeRow['id']]);
        $assignedCode = [
            'code' => $codeRow['code'],
            'type' => $codeRow['type'],
            'duration_hours' => (int) $codeRow['duration_hours'],
        ];
    }

    $user = [
        'id' => (int) $userId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'country' => $country,
        'assigned_code' => $assignedCode ?: [],
    ];

    respondJson(['success' => true, 'message' => 'تم إنشاء الحساب بنجاح.', 'user' => $user]);
}

function validateCode() {
    global $pdo;
    $code = trim($_POST['code'] ?? '');
    $source = trim($_POST['source'] ?? 'flutter_app');
    $usedBy = trim($_POST['used_by'] ?? '');

    if ($code === '') {
        respondJson(['success' => false, 'message' => 'الكود مطلوب.']);
    }

    $stmt = $pdo->prepare('SELECT * FROM codes WHERE code = ?');
    $stmt->execute([$code]);
    $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$codeRow) {
        respondJson(['success' => false, 'message' => 'الكود غير صالح.']);
    }

    if ($codeRow['status'] !== 'unused') {
        respondJson(['success' => false, 'message' => 'هذا الكود مستخدم بالفعل.']);
    }

    $update = $pdo->prepare('UPDATE codes SET status = ?, used_at = NOW(), used_by = ?, source = ? WHERE id = ?');
    $update->execute(['used', $usedBy, $source, $codeRow['id']]);

    respondJson([
        'success' => true,
        'message' => 'تم تفعيل الكود بنجاح.',
        'code' => $codeRow['code'],
        'type' => $codeRow['type'],
        'duration_hours' => (int) $codeRow['duration_hours'],
    ]);
}

function respondJson($data) {
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
