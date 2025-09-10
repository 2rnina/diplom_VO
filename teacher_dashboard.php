<?php
session_start();
require_once 'db.php';

// Обновленная проверка авторизации для преподавателя
if (!isset($_SESSION['entity_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: html_desktop_authorization.php');
    exit();
}

$teacher_id = $_SESSION['entity_id'];
$teacher_full_name = $_SESSION['full_name'] ?? '';

try {
    // Получаем дисциплины и группы, которые ведет преподаватель
    $stmt = $pdo->prepare("
        SELECT 
            t.teaching_id,
            su.code_name,
            su.name_subjects,
            sg.name_study_group,
            sp.subject_plan_id
        FROM Teaching t
        JOIN SubjectsPlan sp ON t.subject_plan_id = sp.subject_plan_id
        JOIN SubjectsHoursSemester shs ON sp.shs_id = shs.shs_id
        JOIN SubjectsHours sh ON shs.subject_hours_id = sh.subject_hours_id
        JOIN Subjects su ON sh.subject_id = su.subject_id
        JOIN StudentsSubjectsPlan ssp ON sp.subject_plan_id = ssp.subject_plan_id
        JOIN Students s ON ssp.student_id = s.student_id
        JOIN Study_Group sg ON s.study_group_id = sg.study_group_id
        WHERE t.teacher_id = :teacher_id
        GROUP BY t.teaching_id, su.code_name, su.name_subjects, sg.name_study_group
    ");
    
    $stmt->execute(['teacher_id' => $teacher_id]);
    $disciplines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($disciplines)) {
        die("Дисциплин не найдено");
    }
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет преподавателя</title>
    
    <!-- Подключение основных стилей -->
    <link rel="stylesheet" href="styles.css">
    
    <!-- Специфические стили для страницы -->
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Шапка страницы -->
        <div class="page-header">
            <h1 class="page-title">Личный кабинет преподавателя</h1>
        </div>

        <!-- Профиль преподавателя -->
        <div class="profile-card">
            <h2>Преподаватель </strong> <?php echo htmlspecialchars($teacher_full_name); ?></h2>
            
        </div>

        <!-- Список дисциплин -->
        <div class="subject-list">
            <h2>Список дисциплин</h2>
            <div class="subject-items">
                <?php foreach ($disciplines as $discipline): ?>
                    <div class="subject-item">
                        <a href="teacher_journal.php?teaching_id=<?php echo htmlspecialchars($discipline['teaching_id']); ?>">
                            <?php echo htmlspecialchars($discipline['code_name']); ?> - 
                            <?php echo htmlspecialchars($discipline['name_subjects']); ?> 
                            (Группа: <?php echo htmlspecialchars($discipline['name_study_group']); ?>)
                        </a>
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
         <footer class="dashboard-footer">
        <p>&copy; 2025 Система электронного журнала</p>
    </footer>
    </div>
    </div>

    <!-- Подключение скриптов -->
    <script src="scripts.js"></script>
</body>
</html>