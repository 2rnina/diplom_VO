<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: html_desktop_authorization.php');
    exit();
}

// Проверяем корректность получения параметров
$teaching_id = (int)$_GET['teaching_id'] ?? 0;
$date = $_GET['date'] ?? null;

if (empty($date)) {
    die("Ошибка: не указана дата занятия");
}

try {
    // Получаем все отметки
    $stmt = $pdo->prepare("SELECT mark_id, name_mark FROM Marks");
    $stmt->execute();
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $marksArray = [];
    foreach ($marks as $mark) {
        $marksArray[$mark['mark_id']] = $mark['name_mark'];
    }

    // Получаем информацию о занятии
    $stmt = $pdo->prepare("
        SELECT 
            date,
            topic 
        FROM Journals 
        WHERE date = :date
        AND ssp_id IN (
            SELECT ssp_id 
            FROM StudentsSubjectsPlan 
            WHERE subject_plan_id = (
                SELECT subject_plan_id 
                FROM Teaching 
                WHERE teaching_id = :teaching_id
            )
        )
    ");
    $stmt->execute([
        'teaching_id' => $teaching_id,
        'date' => $date
    ]);
    $current_lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем студентов
    $stmt = $pdo->prepare("
        SELECT 
            s.student_id,
            s.full_name,
            ss.ssp_id
        FROM Students s
        JOIN StudentsSubjectsPlan ss ON s.student_id = ss.student_id
        WHERE ss.subject_plan_id = (
            SELECT subject_plan_id 
            FROM Teaching 
            WHERE teaching_id = :teaching_id
        )
    ");
    $stmt->execute(['teaching_id' => $teaching_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Исправленный запрос для получения отметок
    $stmt = $pdo->prepare("
        SELECT 
            ssp.student_id,
            j.mark_id
        FROM Journals j
        JOIN StudentsSubjectsPlan ssp ON j.ssp_id = ssp.ssp_id
        WHERE ssp.subject_plan_id = (
            SELECT subject_plan_id 
            FROM Teaching 
            WHERE teaching_id = :teaching_id
        )
        AND j.date = :date
    ");
    $stmt->execute([
        'teaching_id' => $teaching_id,
        'date' => $date
    ]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Создаем массив для быстрого доступа к отметкам
    $marks_by_student = [];
    foreach ($attendance as $row) {
        $marks_by_student[$row['student_id']] = [
            'mark_id' => $row['mark_id']
        ];
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
    <title>Редактирование журнала</title>
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

        .info-block {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        table {
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

        .student-name {
            text-align: left;
        }

        .mark-absent {
            color: #dc3545;
        }

        .mark-present {
            color: #28a745;
        }

        .action-buttons {
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
        }

        .form-container {
            margin-bottom: 24px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 4px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
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

        .message {
            margin: 20px 0;
            padding: 16px;
            border-radius: 8px;
        }

        .success {
            background-color: #d1e7dd;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
Давайте выровняем кнопку “Назад к журналу” по правому краю относительно заголовка:

<!DOCTYPE html>
<html lang="ru">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Редактирование журнала</title>
 <link rel="stylesheet" href="styles.css">
 <style>
 /* Стили для выравнивания заголовка и кнопки */
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

.back-button-wrapper {
 display: flex;
 align-items: center;
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

@media (max-width: 768px) {
 .header {
 flex-direction: column;
 align-items: center;
 }
 
 .back-button-wrapper {
 margin-top: 16px;
 }
}
    </style>
</head>
<body>
    <div class="container">
       <!-- Заголовок страницы с выровненной кнопкой -->
 <div class="header">
 <div class="header-title">
 <h1>Редактирование журнала посещаемости</h1>
 </div>
 
 <div class="back-button-wrapper">
 <a href="teacher_journal.php?teaching_id=<?php echo htmlspecialchars($teaching_id); ?>">
 <button class="back-button">
 Назад к журналу
 </button>
 </a>
 </div>
 </div>

<!-- Информация о дисциплине -->
<div class="info-block">
    <h2>
        Редактирование отметок
    </h2>
</div>

<!-- Блок с сообщениями об успехе/ошибке -->
<div class="message-container">
    <?php if (isset($_GET['success'])): ?>
        <div class="message success">
            Изменения сохранены!
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="message error">
            Произошла ошибка при сохранении отметки
        </div>
    <?php endif; ?>
</div>

<!-- Форма редактирования отметок -->
<div class="form-container">
    <!-- Здесь будет таблица с отметками -->
     <!-- Таблица посещаемости -->
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>ФИО студента</th>
                    <th>Текущая отметка</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <?php 
                    // Получаем отметку для студента
                    $student_mark_info = $marks_by_student[$student['student_id']] ?? [];
                    $student_mark_id = $student_mark_info['mark_id'] ?? null;
                    $student_mark = isset($marksArray[$student_mark_id]) ? 
                        $marksArray[$student_mark_id] : '--';
                    ?>
                    <tr>
                        <td class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td class="mark-cell">
                            <?php 
                            if ($student_mark === 'НБ') {
                                echo '<span class="mark-absent">' . htmlspecialchars($student_mark) . '</span>';
                            } else {
                                echo htmlspecialchars($student_mark);
                            }
                            ?>
                        </td>
                        <td class="action-cell">
                            <form method="POST" action="save_mark.php" class="mark-form">
                                <input type="hidden" name="teaching_id" value="<?php echo htmlspecialchars($teaching_id); ?>">
                                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
                                
                                <select name="mark" class="mark-select">
                                    <?php foreach ($marksArray as $mark_id => $mark_name): ?>
                                        <option value="<?php echo $mark_id; ?>" 
                                            <?php echo ($student_mark_id === $mark_id ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($mark_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" class="save-btn" style="padding-bottom: 7px;padding-top: 7px;padding-left: 7px;padding-right: 7px;">Сохранить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
</div>

<div class="info-block">
    <h2>
        Редактирование темы занятия
    </h2>
</div>

<!-- Форма редактирования темы занятия -->
<div class="form-container topic-editor">
        <form method="POST" action="save_topic.php">
        <input type="hidden" name="teaching_id" value="<?php echo htmlspecialchars($teaching_id); ?>">
        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
        
        <div class="form-group">
            <label for="topic">Тема занятия:</label>
            <textarea 
                id="topic" 
                name="topic" 
                rows="4" 
                cols="50"
                class="topic-input"
            ><?php 
                if (isset($current_lesson['topic'])) {
                    echo htmlspecialchars($current_lesson['topic']);
                }
            ?></textarea>
        </div>
        
        <button type="submit" class="save-btn">
            Сохранить тему
        </button>
    </form>
</div>


<!-- Стили для страницы -->
<style>

    /* Стили для выравнивания элементов */
.mark-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mark-select {
    padding: 4px 4px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    min-width: 50px;
    max-width: 50px; /* Ограничиваем максимальную ширину */
}

.save-btn.small {
    padding: 4px 4px; /* Уменьшаем отступы */
    font-size: 3px;
    min-width: auto;
    height: auto;
}

/* Адаптивные стили */
@media (max-width: 768px) {
    .mark-select {
        min-width: 60px;
        max-width: 100px;
    }
    
    .save-btn.small {
        padding: 4px 8px;
        font-size: 14px;
    }
}

    /* Стили для поля ввода темы занятия */
.topic-editor {
    margin: 24px 0;
}

.topic-editor label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

.topic-editor textarea {
    width: 100%;
    height: 150px;
    padding: 16px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 16px;
    resize: vertical;
    box-sizing: border-box;
}

.topic-editor textarea:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

.topic-editor .save-btn {
    margin-top: 16px;
    padding: 12px 24px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .topic-editor textarea {
        height: 100px;
    }
}

.message-container {
    margin: 20px 0;
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

textarea {
    resize: vertical;
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

@media (max-width: 768px) {
    .form-container {
        padding: 16px;
    }
}
</style>

</div> <!-- Закрываем container -->

<!-- Нижний колонтитул -->
<footer style="margin-top: 40px;">
    <p>&copy; 2025 Система электронного журнала</p>
</footer>
</body>
</html>

