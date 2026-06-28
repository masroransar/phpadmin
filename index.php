<?php
require_once __DIR__ . '/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    type ENUM('trial','one_time','one_month','six_months') NOT NULL,
    duration_hours INT NOT NULL,
    status ENUM('unused','used') NOT NULL DEFAULT 'unused',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    used_by VARCHAR(255) NULL,
    assigned_user_id INT NULL,
    assigned_at DATETIME NULL,
    source VARCHAR(255) NULL,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NULL,
    image_url VARCHAR(1024) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NULL,
    school VARCHAR(255) NULL,
    sales_count INT NOT NULL DEFAULT 0,
    bio TEXT NULL,
    image_url VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS school VARCHAR(255) NULL");
$pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS sales_count INT NOT NULL DEFAULT 0");

$pdo->exec("CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image_url VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS course_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NULL,
    file_type VARCHAR(100) NULL,
    file_url VARCHAR(1024) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS question_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    thumbnail_url VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS twelfth_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES question_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS twelfth_question_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    title VARCHAR(255) NULL,
    file_type VARCHAR(100) NULL,
    file_url VARCHAR(1024) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES twelfth_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS lesson_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    thumbnail_url VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS school_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES lesson_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS school_lesson_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    title VARCHAR(255) NULL,
    file_type VARCHAR(100) NULL,
    file_url VARCHAR(1024) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES school_lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$hasAssignedColumn = $pdo->query("SHOW COLUMNS FROM codes LIKE 'assigned_user_id'")->fetch();
if (!$hasAssignedColumn) {
    $pdo->exec("ALTER TABLE codes ADD COLUMN assigned_user_id INT NULL AFTER used_by, ADD COLUMN assigned_at DATETIME NULL AFTER assigned_user_id");
}

$section = $_GET['section'] ?? 'slider';
$message = ''; 
$editTeacherId = isset($_GET['edit_teacher_id']) ? (int) $_GET['edit_teacher_id'] : 0;
$editingTeacher = null;
$editSliderId = isset($_GET['edit_slider_id']) ? (int) $_GET['edit_slider_id'] : 0;
$editingSlider = null;

if ($editTeacherId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE id = ? LIMIT 1');
    $stmt->execute([$editTeacherId]);
    $editingTeacher = $stmt->fetch();
}

if ($editSliderId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM sliders WHERE id = ? LIMIT 1');
    $stmt->execute([$editSliderId]);
    $editingSlider = $stmt->fetch();
}

function safeValue($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

function generateCode($length = 12) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

function getCodeTypeLabel($type) {
    switch ($type) {
        case 'trial':
            return 'تجربة ساعة';
        case 'one_time':
            return 'مرة واحدة';
        case 'one_month':
            return 'شهر واحد';
        case 'six_months':
            return 'ستة أشهر';
        default:
            return $type;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_slider'])) {
        $title = safeValue('slider_title');
        $imageUrl = safeValue('slider_image_url');
        $upload = uploadFile('slider_image_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $imageUrl = 'admin/' . $upload;
        }
        if ($imageUrl) {
            $stmt = $pdo->prepare('INSERT INTO sliders (title, image_url, active) VALUES (?, ?, 1)');
            $stmt->execute([$title, $imageUrl]);
            $message = 'تم إضافة صورة السلايدر بنجاح.';
        } else {
            $message = 'يرجى إضافة رابط صورة أو رفع ملف صالح.';
        }
    }

    if (isset($_POST['update_slider'])) {
        $sliderId = (int) safeValue('slider_id');
        $title = safeValue('slider_title');
        $imageUrl = safeValue('slider_image_url');
        $currentImageUrl = safeValue('current_slider_image_url');
        $upload = uploadFile('slider_image_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $imageUrl = 'admin/' . $upload;
        }
        if (!$imageUrl) {
            $imageUrl = $currentImageUrl;
        }
        if ($sliderId && $imageUrl) {
            $stmt = $pdo->prepare('UPDATE sliders SET title = ?, image_url = ? WHERE id = ?');
            $stmt->execute([$title, $imageUrl, $sliderId]);
            $message = 'تم تحديث صورة السلايدر بنجاح.';
            header('Location: ?section=slider');
            exit;
        } else {
            $message = 'يرجى إضافة رابط صورة أو رفع ملف صالح.';
        }
    }

    if (isset($_POST['add_teacher'])) {
        $name = safeValue('teacher_name');
        $title = safeValue('teacher_title');
        $school = safeValue('teacher_school');
        $salesCount = max(0, (int) safeValue('teacher_sales_count'));
        $bio = safeValue('teacher_bio');
        $imageUrl = safeValue('teacher_image_url');
        $upload = uploadFile('teacher_image_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $imageUrl = 'admin/' . $upload;
        }
        if ($name) {
            $stmt = $pdo->prepare('INSERT INTO teachers (name, title, school, sales_count, bio, image_url) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $title, $school, $salesCount, $bio, $imageUrl]);
            $message = 'تم إضافة المعلم بنجاح.';
        } else {
            $message = 'اسم المعلم مطلوب.';
        }
    }

    if (isset($_POST['update_teacher'])) {
        $teacherId = (int) safeValue('teacher_id');
        $name = safeValue('teacher_name');
        $title = safeValue('teacher_title');
        $school = safeValue('teacher_school');
        $salesCount = max(0, (int) safeValue('teacher_sales_count'));
        $bio = safeValue('teacher_bio');
        $imageUrl = safeValue('teacher_image_url');
        $upload = uploadFile('teacher_image_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $imageUrl = 'admin/' . $upload;
        }
        if ($teacherId && $name) {
            if ($imageUrl) {
                $stmt = $pdo->prepare('UPDATE teachers SET name = ?, title = ?, school = ?, sales_count = ?, bio = ?, image_url = ? WHERE id = ?');
                $stmt->execute([$name, $title, $school, $salesCount, $bio, $imageUrl, $teacherId]);
            } else {
                $stmt = $pdo->prepare('UPDATE teachers SET name = ?, title = ?, school = ?, sales_count = ?, bio = ? WHERE id = ?');
                $stmt->execute([$name, $title, $school, $salesCount, $bio, $teacherId]);
            }
            $message = 'تم تحديث معلومات المعلم بنجاح.';
            header('Location: ?section=teachers');
            exit;
        } else {
            $message = 'اسم المعلم مطلوب لتحديث البيانات.';
        }
    }

    if (isset($_POST['add_course'])) {
        $teacherId = (int) safeValue('course_teacher_id');
        $title = safeValue('course_title');
        $description = safeValue('course_description');
        $imageUrl = safeValue('course_image_url');
        $upload = uploadFile('course_image_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $imageUrl = 'admin/' . $upload;
        }
        if ($teacherId && $title) {
            $stmt = $pdo->prepare('INSERT INTO courses (teacher_id, title, description, image_url) VALUES (?, ?, ?, ?)');
            $stmt->execute([$teacherId, $title, $description, $imageUrl]);
            $message = 'تم إضافة الدورة بنجاح.';
        } else {
            $message = 'يرجى اختيار معلم وإدخال عنوان الدورة.';
        }
    }

    if (isset($_POST['add_course_file'])) {
        $courseId = (int) safeValue('file_course_id');
        $fileTitle = safeValue('file_title');
        $fileType = safeValue('file_type');
        $fileUrl = safeValue('file_url');
        $upload = uploadFile('course_file_upload', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'audio/mpeg', 'video/mp4']);
        if ($upload) {
            $fileUrl = 'admin/' . $upload;
        }
        if ($courseId && $fileUrl) {
            $stmt = $pdo->prepare('INSERT INTO course_files (course_id, title, file_type, file_url) VALUES (?, ?, ?, ?)');
            $stmt->execute([$courseId, $fileTitle, $fileType, $fileUrl]);
            $message = 'تم إضافة ملف الدورة بنجاح.';
        } else {
            $message = 'يرجى اختيار دورة وإضافة رابط أو ملف صالح.';
        }
    }

    if (isset($_POST['add_question_category'])) {
        $categoryName = safeValue('question_category_name');
        $thumbnailUrl = safeValue('question_category_thumbnail_url');
        $upload = uploadFile('question_category_thumbnail_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $thumbnailUrl = 'admin/' . $upload;
        }
        if ($categoryName) {
            $stmt = $pdo->prepare('INSERT INTO question_categories (name, thumbnail_url) VALUES (?, ?)');
            $stmt->execute([$categoryName, $thumbnailUrl]);
            $message = 'تم إضافة قسم الأسئلة بنجاح.';
        } else {
            $message = 'اسم القسم مطلوب.';
        }
    }

    if (isset($_POST['add_twelfth_question'])) {
        $categoryId = (int) safeValue('question_category_id');
        $questionTitle = safeValue('question_title');
        $questionDescription = safeValue('question_description');
        if ($categoryId && $questionTitle) {
            $stmt = $pdo->prepare('INSERT INTO twelfth_questions (category_id, title, description) VALUES (?, ?, ?)');
            $stmt->execute([$categoryId, $questionTitle, $questionDescription]);
            $message = 'تم إضافة سؤال الصف الثاني عشر بنجاح.';
        } else {
            $message = 'يرجى اختيار القسم وإدخال عنوان السؤال.';
        }
    }

    if (isset($_POST['add_question_file'])) {
        $questionId = (int) safeValue('question_file_question_id');
        $fileTitle = safeValue('question_file_title');
        $fileType = safeValue('question_file_type');
        $fileUrl = safeValue('question_file_url');
        $upload = uploadFile('question_file_upload', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'audio/mpeg', 'video/mp4']);
        if ($upload) {
            $fileUrl = 'admin/' . $upload;
        }
        if ($questionId && $fileUrl) {
            $stmt = $pdo->prepare('INSERT INTO twelfth_question_files (question_id, title, file_type, file_url) VALUES (?, ?, ?, ?)');
            $stmt->execute([$questionId, $fileTitle, $fileType, $fileUrl]);
            $message = 'تم إضافة ملف السؤال بنجاح.';
        } else {
            $message = 'يرجى اختيار سؤال وإضافة رابط أو ملف صالح.';
        }
    }

    if (isset($_POST['update_social_links'])) {
        $updated = 0;
        $socialLinks = $pdo->query('SELECT * FROM social_links ORDER BY sort_order, id DESC')->fetchAll();
        foreach ($socialLinks as $link) {
            $linkKey = trim($link['key'] ?? '');
            if ($linkKey === '') {
                continue;
            }
            $fieldName = 'social_' . $linkKey;
            $linkUrl = safeValue($fieldName);
            $stmt = $pdo->prepare('UPDATE social_links SET url = ? WHERE `key` = ?');
            $stmt->execute([$linkUrl, $linkKey]);
            $updated++;
        }
        $message = 'تم تحديث روابط التواصل الاجتماعي بنجاح.';
    }
}

$sliders = $pdo->query('SELECT * FROM sliders ORDER BY id DESC')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_lesson_category'])) {
        $categoryName = safeValue('lesson_category_name');
        $thumbnailUrl = safeValue('lesson_category_thumbnail_url');
        $upload = uploadFile('lesson_category_thumbnail_file', ['image/jpeg', 'image/png', 'image/gif']);
        if ($upload) {
            $thumbnailUrl = 'admin/' . $upload;
        }
        if ($categoryName) {
            $stmt = $pdo->prepare('INSERT INTO lesson_categories (name, thumbnail_url) VALUES (?, ?)');
            $stmt->execute([$categoryName, $thumbnailUrl]);
            $message = 'تم إضافة قسم الدروس بنجاح.';
        } else {
            $message = 'اسم القسم مطلوب.';
        }
    }

    if (isset($_POST['add_school_lesson'])) {
        $categoryId = (int) safeValue('lesson_category_id');
        $lessonTitle = safeValue('lesson_title');
        $lessonDescription = safeValue('lesson_description');
        if ($categoryId && $lessonTitle) {
            $stmt = $pdo->prepare('INSERT INTO school_lessons (category_id, title, description) VALUES (?, ?, ?)');
            $stmt->execute([$categoryId, $lessonTitle, $lessonDescription]);
            $message = 'تم إضافة درس المدرسة بنجاح.';
        } else {
            $message = 'يرجى اختيار القسم وإدخال عنوان الدرس.';
        }
    }

    if (isset($_POST['add_lesson_file'])) {
        $lessonId = (int) safeValue('lesson_file_lesson_id');
        $fileTitle = safeValue('lesson_file_title');
        $fileType = safeValue('lesson_file_type');
        $fileUrl = safeValue('lesson_file_url');
        $upload = uploadFile('lesson_file_upload', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'audio/mpeg', 'video/mp4']);
        if ($upload) {
            $fileUrl = 'admin/' . $upload;
        }
        if ($lessonId && $fileUrl) {
            $stmt = $pdo->prepare('INSERT INTO school_lesson_files (lesson_id, title, file_type, file_url) VALUES (?, ?, ?, ?)');
            $stmt->execute([$lessonId, $fileTitle, $fileType, $fileUrl]);
            $message = 'تم إضافة ملف الدرس بنجاح.';
        } else {
            $message = 'يرجى اختيار درس وإضافة رابط أو ملف صالح.';
        }
    }

    if (isset($_POST['generate_codes'])) {
        $type = safeValue('code_type');
        $quantity = max(1, min(20, (int) safeValue('code_quantity')));
        $durations = [
            'trial' => 1,
            'one_time' => 0,
            'one_month' => 24 * 30,
            'six_months' => 24 * 180,
        ];
        if (!isset($durations[$type])) {
            $message = 'نوع الكود غير صالح.';
        } else {
            $duration = $durations[$type];
            $generatedCodes = [];
            $stmt = $pdo->prepare('INSERT INTO codes (code, type, duration_hours) VALUES (?, ?, ?)');
            for ($i = 0; $i < $quantity; $i++) {
                $newCode = generateCode(12);
                try {
                    $stmt->execute([$newCode, $type, $duration]);
                    $generatedCodes[] = $newCode;
                } catch (PDOException $e) {
                    $i--; // retry duplicate code
                }
            }
            $message = 'تم إنشاء ' . count($generatedCodes) . ' كود بنجاح.';
        }
    }

    if (isset($_POST['assign_code_to_user'])) {
        $codeId = (int) safeValue('assign_code_id');
        $userId = (int) safeValue('assign_user_id');
        if ($codeId && $userId) {
            $stmt = $pdo->prepare('UPDATE codes SET assigned_user_id = ?, assigned_at = NOW() WHERE id = ? AND status = ?');
            $stmt->execute([$userId, $codeId, 'unused']);
            if ($stmt->rowCount() > 0) {
                $message = 'تم تعيين الكود للمستخدم بنجاح.';
            } else {
                $message = 'تعذر تعيين الكود. تأكد أن الكود غير مستخدم.';
            }
        } else {
            $message = 'يرجى اختيار مستخدم وكود صالح.';
        }
    }
}
$teachers = $pdo->query('SELECT * FROM teachers ORDER BY id DESC')->fetchAll();
$courses = $pdo->query('SELECT c.*, t.name AS teacher_name FROM courses c LEFT JOIN teachers t ON c.teacher_id = t.id ORDER BY c.id DESC')->fetchAll();
$files = $pdo->query('SELECT f.*, c.title AS course_title FROM course_files f LEFT JOIN courses c ON f.course_id = c.id ORDER BY f.id DESC')->fetchAll();
$teacherCourseCounts = [];
foreach ($courses as $course) {
    $teacherId = $course['teacher_id'];
    if (!isset($teacherCourseCounts[$teacherId])) {
        $teacherCourseCounts[$teacherId] = 0;
    }
    $teacherCourseCounts[$teacherId]++;
}
$questionCategories = $pdo->query('SELECT * FROM question_categories ORDER BY id DESC')->fetchAll();
$twelfthQuestions = $pdo->query('SELECT q.*, c.name AS category_name FROM twelfth_questions q LEFT JOIN question_categories c ON q.category_id = c.id ORDER BY q.id DESC')->fetchAll();
$questionFiles = $pdo->query('SELECT f.*, q.title AS question_title FROM twelfth_question_files f LEFT JOIN twelfth_questions q ON f.question_id = q.id ORDER BY f.id DESC')->fetchAll();
$lessonCategories = $pdo->query('SELECT * FROM lesson_categories ORDER BY id DESC')->fetchAll();
$schoolLessons = $pdo->query('SELECT l.*, c.name AS category_name FROM school_lessons l LEFT JOIN lesson_categories c ON l.category_id = c.id ORDER BY l.id DESC')->fetchAll();
$lessonFiles = $pdo->query('SELECT f.*, l.title AS lesson_title FROM school_lesson_files f LEFT JOIN school_lessons l ON f.lesson_id = l.id ORDER BY f.id DESC')->fetchAll();
$socialLinks = $pdo->query('SELECT * FROM social_links ORDER BY id DESC')->fetchAll();
$users = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();
$codes = $pdo->query('SELECT c.*, u.email AS assigned_user_email, u.name AS assigned_user_name FROM codes c LEFT JOIN users u ON c.assigned_user_id = u.id ORDER BY c.id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة - مامۆستاكەم</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f6fb; margin: 0; padding: 0; color: #1f2937; }
        .container { max-width: 1100px; margin: 0 auto; padding: 18px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        header h1 { margin: 0; font-size: 24px; }
        .tabs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
        .tab { padding: 12px 18px; background: white; border-radius: 14px; border: 1px solid #e2e8f0; cursor: pointer; text-decoration: none; color: #0f172a; }
        .tab.active { background: #2563eb; color: white; border-color: #2563eb; }
        .card { background: white; border-radius: 18px; padding: 18px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08); margin-bottom: 20px; }
        .card h2 { margin-top: 0; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type=text], textarea, select { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; margin-bottom: 14px; }
        textarea { min-height: 100px; resize: vertical; }
        button { background: #2563eb; color: white; border: none; padding: 12px 18px; border-radius: 12px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: right; }
        th { background: #f8fafc; }
        .message { background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 14px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>لوحة التحكم - مامۆستاكەم</h1>
        <nav class="tabs">
            <a class="tab <?php echo $section === 'slider' ? 'active' : ''; ?>" href="?section=slider">سلايدر</a>
            <a class="tab <?php echo $section === 'teachers' ? 'active' : ''; ?>" href="?section=teachers">المعلمون</a>
            <a class="tab <?php echo $section === 'codes' ? 'active' : ''; ?>" href="?section=codes">أكواد ترويجية</a>
            <a class="tab <?php echo $section === 'users' ? 'active' : ''; ?>" href="?section=users">المستخدمون</a>
            <a class="tab <?php echo $section === 'courses' ? 'active' : ''; ?>" href="?section=courses">دورات اللغات</a>
            <a class="tab <?php echo $section === 'course_files' ? 'active' : ''; ?>" href="?section=course_files">ملفات الدورات</a>
            <a class="tab <?php echo $section === 'twelfth_questions' ? 'active' : ''; ?>" href="?section=twelfth_questions">أسئلة الصف الثاني عشر</a>
            <a class="tab <?php echo $section === 'school_lessons' ? 'active' : ''; ?>" href="?section=school_lessons">دروس المدرسة</a>
            <a class="tab <?php echo $section === 'social_links' ? 'active' : ''; ?>" href="?section=social_links">روابط التواصل</a>
        </nav>
    </header>

    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($section === 'slider'): ?>
        <div class="card">
            <h2><?php echo $editingSlider ? 'تعديل صورة السلايدر' : 'إضافة صورة سلايدر'; ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php if ($editingSlider): ?>
                    <input type="hidden" name="slider_id" value="<?php echo htmlspecialchars($editingSlider['id']); ?>">
                    <input type="hidden" name="current_slider_image_url" value="<?php echo htmlspecialchars($editingSlider['image_url']); ?>">
                <?php endif; ?>
                <label>العنوان (اختياري)</label>
                <input type="text" name="slider_title" placeholder="عنوان صغير لسلايدر" value="<?php echo htmlspecialchars($editingSlider['title'] ?? ''); ?>">
                <label>رابط الصورة</label>
                <input type="text" name="slider_image_url" placeholder="https://..." value="<?php echo htmlspecialchars($editingSlider['image_url'] ?? ''); ?>">
                <label>أو رفع صورة جديدة</label>
                <input type="file" name="slider_image_file" accept="image/*">
                <button type="submit" name="<?php echo $editingSlider ? 'update_slider' : 'add_slider'; ?>"><?php echo $editingSlider ? 'تحديث السلايدر' : 'حفظ السلايدر'; ?></button>
                <?php if ($editingSlider): ?>
                    <a href="?section=slider" style="margin-right: 12px; display: inline-block; text-decoration: none; color: #2563eb; font-weight: bold;">إلغاء</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card">
            <h2>قائمة صور السلايدر</h2>
            <table>
                <thead><tr><th>المعرف</th><th>العنوان</th><th>الصورة</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                <?php foreach ($sliders as $slider): ?>
                    <tr>
                        <td><?php echo $slider['id']; ?></td>
                        <td><?php echo htmlspecialchars($slider['title']); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($slider['image_url']); ?>" target="_blank">
                                <img src="<?php echo htmlspecialchars($slider['image_url']); ?>" alt="Slider" style="max-height:60px; max-width:120px; object-fit:cover; border-radius:8px;">
                            </a>
                        </td>
                        <td><?php echo $slider['active'] ? 'نشط' : 'غير نشط'; ?></td>
                        <td><a href="?section=slider&edit_slider_id=<?php echo $slider['id']; ?>">تعديل</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'teachers'): ?>
        <div class="card">
            <h2><?php echo $editingTeacher ? 'تعديل المعلم' : 'إضافة معلم'; ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php if ($editingTeacher): ?>
                    <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($editingTeacher['id']); ?>">
                <?php endif; ?>
                <label>اسم المعلم</label>
                <input type="text" name="teacher_name" required value="<?php echo htmlspecialchars($editingTeacher['name'] ?? ''); ?>">
                <label>المسمى الوظيفي</label>
                <input type="text" name="teacher_title" value="<?php echo htmlspecialchars($editingTeacher['title'] ?? ''); ?>">
                <label>اسم المدرسة</label>
                <input type="text" name="teacher_school" value="<?php echo htmlspecialchars($editingTeacher['school'] ?? ''); ?>">
                <label>عدد المبيعات</label>
                <input type="number" name="teacher_sales_count" min="0" value="<?php echo htmlspecialchars($editingTeacher['sales_count'] ?? '0'); ?>">
                <label>معلومات عن المعلم</label>
                <textarea name="teacher_bio"><?php echo htmlspecialchars($editingTeacher['bio'] ?? ''); ?></textarea>
                <label>رابط صورة المعلم</label>
                <input type="text" name="teacher_image_url" placeholder="https://..." value="<?php echo htmlspecialchars($editingTeacher['image_url'] ?? ''); ?>">
                <label>أو رفع صورة المعلم</label>
                <input type="file" name="teacher_image_file" accept="image/*">
                <button type="submit" name="<?php echo $editingTeacher ? 'update_teacher' : 'add_teacher'; ?>"><?php echo $editingTeacher ? 'تحديث المعلم' : 'حفظ المعلم'; ?></button>
                <?php if ($editingTeacher): ?>
                    <a href="?section=teachers" style="margin-left:12px; display:inline-block; text-decoration:none; color:#2563eb;">إلغاء</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card">
            <h2>قائمة المعلمين</h2>
            <style>
                .teachers-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                    gap: 20px;
                    margin-top: 16px;
                }
                .teacher-card {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 16px;
                    padding: 16px;
                    display: flex;
                    gap: 16px;
                    align-items: flex-start;
                }
                .teacher-info {
                    flex: 1;
                }
                .teacher-name {
                    font-size: 18px;
                    font-weight: bold;
                    color: #1f2937;
                    margin: 0 0 8px 0;
                }
                .teacher-title {
                    font-size: 12px;
                    color: #6b7280;
                    margin: 0 0 12px 0;
                }
                .teacher-stats {
                    display: flex;
                    gap: 16px;
                    margin-bottom: 12px;
                }
                .stat-item {
                    display: flex;
                    flex-direction: column;
                }
                .stat-value {
                    font-size: 14px;
                    font-weight: bold;
                    color: #2563eb;
                }
                .stat-label {
                    font-size: 11px;
                    color: #9ca3af;
                }
                .teacher-rating {
                    color: #2563eb;
                    font-size: 14px;
                }
                .teacher-image {
                    width: 90px;
                    height: 90px;
                    border-radius: 50%;
                    border: 3px solid #2563eb;
                    object-fit: cover;
                    flex-shrink: 0;
                    background: white;
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
                }
                .teacher-image.placeholder {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f1f5f9;
                    font-size: 40px;
                }
            </style>
            <div class="teachers-grid">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card">
                        <div class="teacher-info">
                            <p class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></p>
                            <?php if ($teacher['title']): ?>
                                <p class="teacher-title"><?php echo htmlspecialchars($teacher['title']); ?></p>
                            <?php endif; ?>
                            <?php if ($teacher['school']): ?>
                                <p class="teacher-title"><?php echo htmlspecialchars($teacher['school']); ?></p>
                            <?php endif; ?>
                            <div class="teacher-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo htmlspecialchars($teacher['sales_count']); ?></span>
                                    <span class="stat-label">المبيعات</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo htmlspecialchars(isset($teacherCourseCounts[$teacher['id']]) ? $teacherCourseCounts[$teacher['id']] : 0); ?></span>
                                    <span class="stat-label">الدورات</span>
                                </div>
                            </div>
                            <div class="teacher-rating">★★★★☆</div>
                            <div style="margin-top:12px;">
                                <a href="?section=teachers&edit_teacher_id=<?php echo $teacher['id']; ?>" class="button-link">تعديل</a>
                            </div>
                        </div>
                        <?php if ($teacher['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($teacher['image_url']); ?>" alt="<?php echo htmlspecialchars($teacher['name']); ?>" class="teacher-image">
                        <?php else: ?>
                            <div class="teacher-image placeholder">👨‍🏫</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($teachers)): ?>
                <p style="text-align: center; color: #9ca3af; margin-top: 20px;">لا توجد معلمين حالياً. أضف معلماً جديداً!</p>
            <?php endif; ?>
        </div>
    <?php elseif ($section === 'codes'): ?>
        <div class="card">
            <h2>إنشاء أكواد ترويجية</h2>
            <form method="post">
                <label>نوع الكود</label>
                <select name="code_type" required>
                    <option value="trial">تجربة ساعة</option>
                    <option value="one_time">مرة واحدة</option>
                    <option value="one_month">شهر واحد</option>
                    <option value="six_months">ستة أشهر</option>
                </select>
                <label>عدد الأكواد</label>
                <input type="number" name="code_quantity" min="1" max="20" value="1" required>
                <button type="submit" name="generate_codes">إنشاء الأكواد</button>
            </form>
        </div>
        <div class="card">
            <h2>تعيين كود لمستخدم</h2>
            <form method="post">
                <label>اختر المستخدم</label>
                <select name="assign_user_id" required>
                    <option value="">-- اختر المستخدم --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>اختر كود غير مستخدم</label>
                <select name="assign_code_id" required>
                    <option value="">-- اختر الكود --</option>
                    <?php foreach ($codes as $code): ?>
                        <?php if ($code['status'] === 'unused'): ?>
                            <option value="<?php echo $code['id']; ?>"><?php echo htmlspecialchars($code['code'] . ' - ' . getCodeTypeLabel($code['type'])); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="assign_code_to_user">تعيين الكود</button>
            </form>
        </div>
        <div class="card">
            <h2>الأكواد غير المستخدمة</h2>
            <table>
                <thead><tr><th>المعرف</th><th>الكود</th><th>النوع</th><th>المدة (ساعة)</th><th>المستخدم المعين</th><th>أنشئ في</th></tr></thead>
                <tbody>
                <?php foreach ($codes as $code): ?>
                    <?php if ($code['status'] === 'unused'): ?>
                    <tr>
                        <td><?php echo $code['id']; ?></td>
                        <td><?php echo htmlspecialchars($code['code']); ?></td>
                        <td><?php echo htmlspecialchars(getCodeTypeLabel($code['type'])); ?></td>
                        <td><?php echo $code['duration_hours']; ?></td>
                        <td><?php echo htmlspecialchars($code['assigned_user_email'] ?? ''); ?></td>
                        <td><?php echo $code['created_at']; ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>الأكواد المستخدمة</h2>
            <table>
                <thead><tr><th>المعرف</th><th>الكود</th><th>النوع</th><th>المدة (ساعة)</th><th>استخدم في</th><th>استخدم بواسطة</th><th>المصدر</th></tr></thead>
                <tbody>
                <?php foreach ($codes as $code): ?>
                    <?php if ($code['status'] === 'used'): ?>
                    <tr>
                        <td><?php echo $code['id']; ?></td>
                        <td><?php echo htmlspecialchars($code['code']); ?></td>
                        <td><?php echo htmlspecialchars(getCodeTypeLabel($code['type'])); ?></td>
                        <td><?php echo $code['duration_hours']; ?></td>
                        <td><?php echo $code['used_at']; ?></td>
                        <td><?php echo htmlspecialchars($code['used_by']); ?></td>
                        <td><?php echo htmlspecialchars($code['source']); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'users'): ?>
        <div class="card">
            <h2>قائمة المستخدمين</h2>
            <table>
                <thead><tr><th>المعرف</th><th>الاسم</th><th>البريد الإلكتروني</th><th>الهاتف</th><th>البلد</th><th>أنشئ في</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['country'] ?? ''); ?></td>
                        <td><?php echo $user['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'courses'): ?>
        <div class="card">
            <h2>إضافة دورة لغات</h2>
            <form method="post" enctype="multipart/form-data">
                <label>اختر المعلم</label>
                <select name="course_teacher_id" required>
                    <option value="">-- اختر المعلم --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>عنوان الدورة</label>
                <input type="text" name="course_title" required>
                <label>وصف الدورة</label>
                <textarea name="course_description"></textarea>
                <label>رابط صورة الدورة المربعة</label>
                <input type="text" name="course_image_url" placeholder="https://...">
                <label>أو رفع صورة الدورة</label>
                <input type="file" name="course_image_file" accept="image/*">
                <button type="submit" name="add_course">حفظ الدورة</button>
            </form>
        </div>
        <div class="card">
            <h2>قائمة الدورات</h2>
            <table>
                <thead><tr><th>المعرف</th><th>عنوان الدورة</th><th>المعلم</th><th>الصورة</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?php echo $course['id']; ?></td>
                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                        <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($course['image_url']); ?>" target="_blank">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'course_files'): ?>
        <div class="card">
            <h2>إضافة ملف دورة</h2>
            <form method="post" enctype="multipart/form-data">
                <label>اختر الدورة</label>
                <select name="file_course_id" required>
                    <option value="">-- اختر الدورة --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>عنوان الملف</label>
                <input type="text" name="file_title" placeholder="عنوان الملف أو الفيديو">
                <label>نوع الملف</label>
                <select name="file_type" required>
                    <option value="video">m3u8 فيديو</option>
                    <option value="audio">mp3 صوت</option>
                    <option value="pdf">PDF</option>
                    <option value="word">Word</option>
                </select>
                <label>رابط الملف</label>
                <input type="text" name="file_url" placeholder="https://... أو m3u8">
                <label>أو رفع ملف</label>
                <input type="file" name="course_file_upload" accept=".pdf,.doc,.docx,.mp3,video/*">
                <button type="submit" name="add_course_file">حفظ الملف</button>
            </form>
        </div>
        <div class="card">
            <h2>قائمة ملفات الدورات</h2>
            <table>
                <thead><tr><th>المعرف</th><th>الدورة</th><th>العنوان</th><th>النوع</th><th>الرابط</th></tr></thead>
                <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo $file['id']; ?></td>
                        <td><?php echo htmlspecialchars($file['course_title']); ?></td>
                        <td><?php echo htmlspecialchars($file['title']); ?></td>
                        <td><?php echo htmlspecialchars($file['file_type']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($file['file_url']); ?>" target="_blank">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'twelfth_questions'): ?>
        <div class="card">
            <h2>إضافة قسم أسئلة الصف الثاني عشر</h2>
            <form method="post" enctype="multipart/form-data">
                <label>اسم القسم</label>
                <input type="text" name="question_category_name" required>
                <label>رابط الصورة المصغرة</label>
                <input type="text" name="question_category_thumbnail_url" placeholder="https://...">
                <label>أو رفع صورة مصغرة</label>
                <input type="file" name="question_category_thumbnail_file" accept="image/*">
                <button type="submit" name="add_question_category">حفظ القسم</button>
            </form>
        </div>
        <div class="card">
            <h2>إضافة سؤال الصف الثاني عشر</h2>
            <form method="post">
                <label>اختر القسم</label>
                <select name="question_category_id" required>
                    <option value="">-- اختر القسم --</option>
                    <?php foreach ($questionCategories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>عنوان السؤال</label>
                <input type="text" name="question_title" required>
                <label>وصف السؤال</label>
                <textarea name="question_description"></textarea>
                <button type="submit" name="add_twelfth_question">حفظ السؤال</button>
            </form>
        </div>
        <div class="card">
            <h2>إضافة ملف لسؤال</h2>
            <form method="post" enctype="multipart/form-data">
                <label>اختر السؤال</label>
                <select name="question_file_question_id" required>
                    <option value="">-- اختر السؤال --</option>
                    <?php foreach ($twelfthQuestions as $question): ?>
                        <option value="<?php echo $question['id']; ?>"><?php echo htmlspecialchars($question['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>عنوان الملف</label>
                <input type="text" name="question_file_title" placeholder="عنوان الملف أو الفيديو">
                <label>نوع الملف</label>
                <select name="question_file_type" required>
                    <option value="m3u8">m3u8 فيديو</option>
                    <option value="video">MP4 فيديو</option>
                    <option value="audio">MP3 صوت</option>
                    <option value="pdf">PDF</option>
                    <option value="word">Word</option>
                </select>
                <label>رابط الملف</label>
                <input type="text" name="question_file_url" placeholder="https://... أو m3u8">
                <label>أو رفع ملف</label>
                <input type="file" name="question_file_upload" accept=".pdf,.doc,.docx,.mp3,video/*">
                <button type="submit" name="add_question_file">حفظ ملف السؤال</button>
            </form>
        </div>
        <div class="card">
            <h2>قائمة أقسام أسئلة الصف الثاني عشر</h2>
            <table>
                <thead><tr><th>المعرف</th><th>القسم</th><th>الصورة المصغرة</th></tr></thead>
                <tbody>
                <?php foreach ($questionCategories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($category['thumbnail_url']); ?>" target="_blank">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>قائمة أسئلة الصف الثاني عشر</h2>
            <table>
                <thead><tr><th>المعرف</th><th>القسم</th><th>العنوان</th><th>الوصف</th></tr></thead>
                <tbody>
                <?php foreach ($twelfthQuestions as $question): ?>
                    <tr>
                        <td><?php echo $question['id']; ?></td>
                        <td><?php echo htmlspecialchars($question['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($question['title']); ?></td>
                        <td><?php echo htmlspecialchars($question['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>قائمة ملفات أسئلة الصف الثاني عشر</h2>
            <table>
                <thead><tr><th>المعرف</th><th>السؤال</th><th>العنوان</th><th>النوع</th><th>الرابط</th></tr></thead>
                <tbody>
                <?php foreach ($questionFiles as $file): ?>
                    <tr>
                        <td><?php echo $file['id']; ?></td>
                        <td><?php echo htmlspecialchars($file['question_title']); ?></td>
                        <td><?php echo htmlspecialchars($file['title']); ?></td>
                        <td><?php echo htmlspecialchars($file['file_type']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($file['file_url']); ?>" target="_blank">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'social_links'): ?>
        <div class="card">
            <h2>تحديث روابط التواصل الاجتماعي</h2>
            <form method="post">
                <?php foreach ($socialLinks as $link): ?>
                    <label><?php echo htmlspecialchars($link['label']); ?></label>
                    <input type="text" name="social_<?php echo htmlspecialchars($link['key']); ?>" placeholder="https://..." value="<?php echo htmlspecialchars($link['url']); ?>">
                <?php endforeach; ?>
                <button type="submit" name="update_social_links">حفظ الروابط</button>
            </form>
        </div>
        <div class="card">
            <h2>روابط التواصل الاجتماعي الحالية</h2>
            <table>
                <thead><tr><th>المعرف</th><th>المفتاح</th><th>الاسم</th><th>الرابط</th></tr></thead>
                <tbody>
                <?php foreach ($socialLinks as $link): ?>
                    <tr>
                        <td><?php echo $link['id']; ?></td>
                        <td><?php echo htmlspecialchars($link['key']); ?></td>
                        <td><?php echo htmlspecialchars($link['label']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"><?php echo htmlspecialchars($link['url']); ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($section === 'school_lessons'): ?>
        <div class="card">
            <h2>إضافة قسم دروس المدرسة</h2>
            <form method="post" enctype="multipart/form-data">
                <label>اسم القسم</label>
                <input type="text" name="lesson_category_name" required>
                <label>رابط الصورة المصغرة</label>
                <input type="text" name="lesson_category_thumbnail_url" placeholder="https://...">
                <label>أو رفع صورة مصغرة</label>
                <input type="file" name="lesson_category_thumbnail_file" accept="image/*">
                <button type="submit" name="add_lesson_category">حفظ القسم</button>
            </form>
        </div>
        <div class="card">
            <h2>إضافة درس المدرسة</h2>
            <form method="post">
                <label>اختر القسم</label>
                <select name="lesson_category_id" required>
                    <option value="">-- اختر القسم --</option>
                    <?php foreach ($lessonCategories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>عنوان الدرس</label>
                <input type="text" name="lesson_title" required>
                <label>وصف الدرس</label>
                <textarea name="lesson_description"></textarea>
                <button type="submit" name="add_school_lesson">حفظ الدرس</button>
            </form>
        </div>
        <div class="card">
            <h2>إضافة ملف للدرس</h2>
            <form method="post" enctype="multipart/form-data">
                <label>اختر الدرس</label>
                <select name="lesson_file_lesson_id" required>
                    <option value="">-- اختر الدرس --</option>
                    <?php foreach ($schoolLessons as $lesson): ?>
                        <option value="<?php echo $lesson['id']; ?>"><?php echo htmlspecialchars($lesson['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>عنوان الملف</label>
                <input type="text" name="lesson_file_title" placeholder="عنوان الملف أو الفيديو">
                <label>نوع الملف</label>
                <select name="lesson_file_type" required>
                    <option value="m3u8">m3u8 فيديو</option>
                    <option value="video">MP4 فيديو</option>
                    <option value="audio">MP3 صوت</option>
                    <option value="pdf">PDF</option>
                    <option value="word">Word</option>
                </select>
                <label>رابط الملف</label>
                <input type="text" name="lesson_file_url" placeholder="https://... أو m3u8">
                <label>أو رفع ملف</label>
                <input type="file" name="lesson_file_upload" accept=".pdf,.doc,.docx,.mp3,video/*">
                <button type="submit" name="add_lesson_file">حفظ الملف</button>
            </form>
        </div>
        <div class="card">
            <h2>قائمة أقسام دروس المدرسة</h2>
            <table>
                <thead><tr><th>المعرف</th><th>القسم</th><th>الصورة المصغرة</th></tr></thead>
                <tbody>
                <?php foreach ($lessonCategories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($category['thumbnail_url']); ?>" target="_blank">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>قائمة دروس المدرسة</h2>
            <table>
                <thead><tr><th>المعرف</th><th>القسم</th><th>العنوان</th><th>الوصف</th></tr></thead>
                <tbody>
                <?php foreach ($schoolLessons as $lesson): ?>
                    <tr>
                        <td><?php echo $lesson['id']; ?></td>
                        <td><?php echo htmlspecialchars($lesson['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                        <td><?php echo htmlspecialchars($lesson['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>قائمة ملفات دروس المدرسة</h2>
            <table>
                <thead><tr><th>المعرف</th><th>الدرس</th><th>العنوان</th><th>النوع</th><th>الرابط</th></tr></thead>
                <tbody>
                <?php foreach ($lessonFiles as $file): ?>
                    <tr>
                        <td><?php echo $file['id']; ?></td>
                        <td><?php echo htmlspecialchars($file['lesson_title']); ?></td>
                        <td><?php echo htmlspecialchars($file['title']); ?></td>
                        <td><?php echo htmlspecialchars($file['file_type']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($file['file_url']); ?>" target="_blank">عرض</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
