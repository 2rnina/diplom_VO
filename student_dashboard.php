<?php
session_start();

try {
    require_once 'db.php';
    
    // Правильная проверка сессии
    if (!isset($_SESSION['entity_id']) || $_SESSION['role'] !== 'student') {
        header('Location: index.php');
        exit();
    }

    $student_id = $_SESSION['entity_id'];
    $student_full_name = $_SESSION['full_name'];

    // Получаем информацию о студенте
    $stmt = $pdo->prepare("
        SELECT 
            s.full_name,
            sg.name_study_group,
            c.course,
            sem.semester
        FROM Students s
        JOIN Study_Group sg ON s.study_group_id = sg.study_group_id
        JOIN CourseSemestr cs ON sg.course_semester_id = cs.course_semester_id
        JOIN Course c ON cs.course_id = c.course_id
        JOIN Semester sem ON cs.semester_id = sem.semester_id
        WHERE s.student_id = :student_id
    ");
    $stmt->execute(['student_id' => $student_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Изменяем запрос для получения дисциплин
$stmt = $pdo->prepare("
    SELECT 
        ssp.ssp_id, -- Добавляем получение ssp_id
        su.code_name AS subject_code,
        su.name_subjects AS subject_name,
        t.full_name AS teacher_name,
        su.subject_id
    FROM StudentsSubjectsPlan ssp
    JOIN SubjectsPlan sp ON ssp.subject_plan_id = sp.subject_plan_id
    JOIN Teaching te ON sp.subject_plan_id = te.subject_plan_id
    JOIN Teachers t ON te.teacher_id = t.teacher_id
    JOIN SubjectsSemester ss ON sp.shs_id = ss.subject_semester_id
    JOIN Subjects su ON ss.subject_id = su.subject_id
    WHERE ssp.student_id = :student_id
");


    $stmt->execute(['student_id' => $student_id]);
    $disciplines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}
?>



<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет студента</title>
    
    <!-- Подключение основных стилей -->
    <link rel="stylesheet" href="styles.css">
    
    <!-- Специфические стили для данной страницы -->
    <style>
        .dashboard-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .profile-card {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .subject-list {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }

        .subject-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .subject-item:hover {
            background-color: #f0f0f0;
        }

        .teacher-name {
            color: #555;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Шапка страницы -->
        <div class="page-header">
            <h1 class="page-title">Личный кабинет студента</h1>
        </div>

        <!-- Профиль студента -->
        <div class="profile-card">
                        <div class="profile-info">
                <p><strong>ФИО:</strong> <?php echo htmlspecialchars($student_full_name); ?></p>
                <p><strong>Группа:</strong> <?php echo htmlspecialchars($student_info['name_study_group'] ?? ''); ?></p>
                <p><strong>Курс:</strong> <?php echo htmlspecialchars($student_info['course'] ?? ''); ?></p>
                <p><strong>Семестр:</strong> <?php echo htmlspecialchars($student_info['semester'] ?? ''); ?></p>
            </div>
        </div>

        <!-- Список дисциплин -->
        <div class="subject-list">
            <h2>Список дисциплин</h2>
            <div class="subject-items">
                <?php foreach ($disciplines as $discipline): ?>
                    <div class="subject-item">
                        <a href="student_journal.php?ssp_id=<?php echo htmlspecialchars($discipline['ssp_id']); ?>">
                            <?php echo htmlspecialchars($discipline['subject_code']); ?> - 
                            <?php echo htmlspecialchars($discipline['subject_name']); ?>
                        </a>
                        <span class="teacher-name">(преподаватель: 
                            <?php echo htmlspecialchars($discipline['teacher_name']); ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
   
   <!-- Кнопка выхода -->
        <div class="logout-button-container">
            <form action="logout.php" method="post">
                <button type="submit" class="logout-button">Выход</button>
            </form>
        </div>
    </div>
    <!-- Футер -->
    <footer class="dashboard-footer">
        <p>&copy; 2025 Система электронного журнала</p>
    </footer>
    </div>


    <!-- Подключение скриптов -->
    <script src="scripts.js"></script>
</body>
</html>
