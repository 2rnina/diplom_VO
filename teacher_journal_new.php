<?php
session_start();
require_once 'db.php';
require_once 'save_new_lesson.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

$teaching_id = (int)$_GET['teaching_id'] ?? 0;
$date = $_GET['date'] ?? null;
if (empty($date)) {
    $date = $_POST['date'] ?? null;
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = htmlspecialchars(trim($_POST['topic']));
    $selected_marks = $_POST['marks'] ?? [];
    $date = $_POST['date'];

    if (empty($date)) {
        die("Ошибка: дата не указана");
    }
    
    $result = saveLesson(
        $pdo,
        $teaching_id,
        $date,
        $topic,
        $selected_marks
    );

        if ($result === true) {
        header('Location: teacher_journal.php?teaching_id='. $teaching_id. '&success=true');
        exit();
    } else {
        die($result);
    }
}

// Получаем все отметки
try {
    $stmt = $pdo->prepare("SELECT mark_id, name_mark FROM Marks");
    $stmt->execute();
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $marksArray = [];
    foreach ($marks as $mark) {
        $marksArray[$mark['mark_id']] = $mark['name_mark'];
    }

    // Получаем информацию о дисциплине
    $stmt = $pdo->prepare("
        SELECT 
            sg.name_study_group AS group_name,
            su.name_subjects AS subject_name
        FROM Teaching t
        JOIN SubjectsPlan sp ON t.subject_plan_id = sp.subject_plan_id
        JOIN SubjectsSemester ss ON sp.shs_id = ss.subject_semester_id
        JOIN Subjects su ON ss.subject_id = su.subject_id
        JOIN Study_Group sg ON sp.plan_id = sg.plan_id
        WHERE t.teaching_id = :teaching_id
    ");
    $stmt->execute(['teaching_id' => $teaching_id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем список студентов
    $stmt = $pdo->prepare("
        SELECT student_id, full_name
        FROM Students
        WHERE student_id IN (
            SELECT student_id
            FROM StudentsSubjectsPlan
            WHERE subject_plan_id = (
                SELECT subject_plan_id 
                FROM Teaching 
                WHERE teaching_id = :teaching_id
            )
        )
    ");
    $stmt->execute(['teaching_id' => $teaching_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении данных: ". $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание записи в журнале</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Основные стили для страницы */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
            font-family: Arial, sans-serif;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .header-title {
            font-size: 24px;
            margin: 0;
        }

        .back-button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .back-button:hover {
            background-color: #0056b3;
        }

        .form-container {
            background-color: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 16px;
            text-align: center;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .save-btn {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .save-btn:hover {
            background-color: #0056b3;
        }

        .students-list {
            margin-top: 24px;
        }

        .students-list th {
            text-align: left;
        }

        .topic-input {
            height: 150px;
            padding: 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            resize: vertical;
        }

        .date-input {
            width: 200px;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="header-title">Создание новой записи в журнале</h1>
            <a href="teacher_journal.php?teaching_id=<?php echo htmlspecialchars($teaching_id); ?>">
                <button class="back-button">Назад к журналу</button>
            </a>
        </div>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="teaching_id" value="<?php echo htmlspecialchars($teaching_id); ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">

                <div class="form-group">
                    <label for="topic">Тема занятия:</label>
                    <textarea 
                        id="topic" 
                        name="topic" 
                        required
                        rows="4"
                        class="topic-input"
                        style="
                            width: calc(100% - 4px); /* Отступ от контейнера */
                            max-width: 1200px; /* Максимальная ширина */
                            box-sizing: border-box;
                        "
                    ></textarea>
                </div>

                <div class="form-group">
                    <label>Дата занятия:</label>
                    <input 
                        type="date" 
                        name="date" 
                        value="<?php echo htmlspecialchars($date); ?>" 
                        required
                        class="date-input"
                    >
                </div>

                <div class="students-list">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>ФИО студента</th>
                                <th>Отметка</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $key => $student): ?>
                                <tr>
                                    <td><?php echo $key + 1; ?></td>
                                    <td class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td>
                                        <select 
                                            name="marks[<?php echo $student['student_id']; ?>]" 
                                            class="mark-select"
                                        >
                                            <?php foreach ($marksArray as $mark_id => $mark_name): ?>
                                                <option value="<?php echo $mark_id; ?>">
                                                    <?php echo htmlspecialchars($mark_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="save-btn">
                        Создать запись
                    </button>
                                    </div>
            </form>
        </div>
    </div>

    <!-- Стили для формы -->
    <style>
        .topic-input {
                width: 100%;
                max-width: 600px; /* Ограничение максимальной ширины */
                min-width: 300px; /* Минимальная ширина */
                height: 150px;
                padding: 16px;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-size: 16px;
                resize: vertical;
                box-sizing: border-box; /* Учёт padding и border в ширине */
            }

            @media (max-width: 768px) {
                .topic-input {
                    max-width: 100%;
                    min-width: auto;
                }
            }

        .date-input {
            width: 200px;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .mark-select {
            width: 100px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 24px;
        }
    </style>
<footer style="margin-top: 40px;">
    <p>&copy; 2025 Система электронного журнала</p>
</footer>
</body>
</html>
