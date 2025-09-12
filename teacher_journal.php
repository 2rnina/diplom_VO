<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

$teaching_id = (int)$_GET['teaching_id'] ?? 0;
$date = $_GET['date'] ?? null;

try {

     // Получаем все отметки посещаемости
    $stmt = $pdo->prepare("SELECT mark_id, name_mark FROM Marks");
    $stmt->execute();
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Создаем массив для быстрого доступа
    $marksArray = [];
    foreach ($marks as $mark) {
        $marksArray[$mark['mark_id']] = $mark['name_mark'];
    }

    // Получаем основную информацию
    $stmt = $pdo->prepare("
        SELECT 
            sg.name_study_group AS group_name,
            su.name_subjects AS subject_name
        FROM Teaching t
        JOIN SubjectsPlan sp ON t.subject_plan_id = sp.subject_plan_id
        JOIN SubjectsSemester ss ON sp.shs_id = ss.subject_semester_id
        JOIN Subjects su ON ss.subject_id = su.subject_id
        JOIN StudentsSubjectsPlan ssp ON sp.subject_plan_id = ssp.subject_plan_id
        JOIN Students st ON ssp.student_id = st.student_id
        JOIN Study_Group sg ON st.study_group_id = sg.study_group_id
        WHERE t.teaching_id = :teaching_id
        GROUP BY sg.name_study_group, su.name_subjects
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

    // Получаем все даты занятий через StudentsSubjectsPlan
    $stmt = $pdo->prepare("
        SELECT DISTINCT j.date
        FROM Journals j
        JOIN StudentsSubjectsPlan ssp ON j.ssp_id = ssp.ssp_id
        WHERE ssp.subject_plan_id = (
            SELECT subject_plan_id 
            FROM Teaching 
            WHERE teaching_id = :teaching_id
        )
        ORDER BY j.date
    ");
    $stmt->execute(['teaching_id' => $teaching_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

  

// В начале скрипта, где получаем параметры
$date = $_GET['date'] ?? null;

// Модифицируем запрос получения информации о занятии
if ($date) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
            j.date,
            m.name_mark,
            j.topic,
            j.comment
            FROM Journals j
            LEFT JOIN Marks m ON j.mark_id = m.mark_id
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
        $current_lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Если дата пустая, не выполняем этот запрос
    }
}

// Модифицируем запрос получения отметок
if ($date) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
            student_id,
            mark_id,
            date
            FROM Journals j
            JOIN StudentsSubjectsPlan ssp ON j.ssp_id = ssp.ssp_id
            WHERE ssp.subject_plan_id = :teaching_id
            AND j.date = :date
        ");
        $stmt->execute([
            'teaching_id' => $teaching_id,
            'date' => $date
        ]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Обработка ошибки
    }
}

     // Получаем общую статистику посещаемости
    $stmt = $pdo->prepare("
        SELECT 
            s.full_name,
            COUNT(j.journal_id) as total_lessons,
            SUM(CASE WHEN m.name_mark != 'НБ' THEN 1 ELSE 0 END) as present_count,
            ROUND((SUM(CASE WHEN m.name_mark != 'НБ' THEN 1 ELSE 0 END) / COUNT(j.journal_id) * 100), 2) as present_percent,
            ROUND(
                COALESCE(
                    SUM(
                        CASE 
                            WHEN m.name_mark = '2' THEN 2
                            WHEN m.name_mark = '3' THEN 3
                            WHEN m.name_mark = '4' THEN 4
                            WHEN m.name_mark = '5' THEN 5
                        END
                    ) / 
                    NULLIF(
                        SUM(
                            CASE 
                                WHEN m.name_mark = '2' THEN 1
                                WHEN m.name_mark = '3' THEN 1
                                WHEN m.name_mark = '4' THEN 1
                                WHEN m.name_mark = '5' THEN 1
                            END
                        ), 0
                    ), 
                    0
                ), 
                2
            ) as average_mark
        FROM Students s
        LEFT JOIN StudentsSubjectsPlan ssp ON s.student_id = ssp.student_id
        LEFT JOIN Journals j ON ssp.ssp_id = j.ssp_id
        LEFT JOIN Marks m ON j.mark_id = m.mark_id
        WHERE ssp.subject_plan_id = (
            SELECT subject_plan_id 
            FROM Teaching 
            WHERE teaching_id = :teaching_id
        )
        GROUP BY s.student_id
    ");
    $stmt->execute(['teaching_id' => $teaching_id]);
    $attendance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал преподавателя</title>
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

        .table-container {
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

        .date-filter {
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Заголовок страницы -->
        <div class="header">
            <div class="header-title">
                <h1>Журнал преподавателя</h1>
            </div>
            <div class="back-button-wrapper">
                <a href="teacher_dashboard.php">
                    <button class="back-button">
                        Назад к дисциплинам
                    </button>
                </a>
            </div>
        </div>

        <!-- Информация о дисциплине -->
        <div class="info-block">
            <h2>
                <?php echo htmlspecialchars($info['subject_name']); ?>
                <span>(Группа: <?php echo htmlspecialchars($info['group_name']); ?>)</span>
            </h2>
        </div>

       

        



<!-- Стили для выравнивания -->
<style>
.filter-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.filter-label {
    
    margin-right: 16px;
}

.filter-spacing {
    width: 16px;
}

.date-picker {
    padding: 12px;
    min-width: 200px;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        gap: 8px;
    }
}
</style>


<!-- Блок статистики (показывается когда дата не выбрана) -->
<div class="stats-container <?php echo !empty($date) ? 'hidden' : ''; ?>">
    <!-- Весь существующий код статистики -->
    <!-- Общая статистика посещаемости -->
<div class="stats-container">
    <div class="info-block">
    <h2>Общая статистика посещаемости</h2>
    
    <?php 
    // Подсчет общих показателей
    $total_students = count($attendance_stats);
    $total_lessons = count($dates);
    $total_present = 0;
    $total_absent = 0;
    
    foreach ($attendance_stats as $stat) {
        $total_present += $stat['present_count'];
        $total_absent += ($stat['total_lessons'] - $stat['present_count']);
    }
    
    $overall_percent = ($total_lessons > 0) ? 
        round(($total_present / ($total_present + $total_absent)) * 100, 2) : 0;
    ?>
    
    <div class="stats-summary">
        <div class="stat-item">
                        <p><strong>Всего студентов:</strong> <?php echo $total_students; ?></p>
            <p><strong>Проведено занятий:</strong> <?php echo $total_lessons; ?></p>
            <p><strong>Общая посещаемость:</strong> <?php echo $overall_percent; ?>%</p>
        </div>
    </div>
    </div>
        <table class="stats-table">
        <thead>
            <tr>
                <th>ФИО студента</th>
                <th>Всего занятий</th>
                <th>Присутствовал</th>
                <th>Процент посещаемости</th>
                <th>Средний балл</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_stats as $stat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($stat['full_name']); ?></td>
                    <td><?php echo $stat['total_lessons']; ?></td>
                    <td><?php echo $stat['present_count']; ?></td>
                    <td><?php echo $stat['present_percent']; ?>%</td>
                    <td><?php echo $stat['average_mark']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <!-- ... остальной код таблицы статистики ... -->
</div>


     
                            
      


<!-- Блок посещаемости (показывается при выборе даты) -->
<div class="attendance-container <?php echo empty($date) ? 'hidden' : ''; ?>">
    <h2>Посещаемость на <?php echo htmlspecialchars($date); ?></h2>
    
    <table class="attendance-table">
        <thead>
            <tr>
                <th>ФИО студента</th>
                <th>Отметка</th>
                <th>Тема занятия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
                <?php 
                $mark = null;
                foreach ($attendance as $att) {
                    if ($att['student_id'] == $student['student_id'] && $att['date'] == $date) {
                        $mark = isset($marksArray[$att['mark_id']]) ? $marksArray[$att['mark_id']] : '--';
                        break;
                    }
                }
                ?>
                <tr>
                    <td class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td>
                        <?php if ($mark === 'НБ'): ?>
                            <span class="mark-absent"><?php echo htmlspecialchars($mark); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($mark); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($current_lesson['topic'] ?? 'Тема не указана'); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Добавляем стили для скрытия блоков -->
<style>
    .hidden {
        display: none;
    }
    
    .attendance-table {
        width: 100%;
        margin: 20px 0;
    }
    
    .mark-absent {
        color: red;
        font-weight: bold;
    }
</style>


<!-- Контейнер для всех элементов управления -->
<div class="control-panel">
    <!-- Форма фильтрации -->
    <div class="filter-section">
        <div class="filter-container">
            <div class="filter-row">
                <strong style="margin-bottom: 15px;">Фильтр по датам:</strong>
                
                <!-- Отступ между текстом и формой -->
                <div class="filter-spacing"></div>
                
                <!-- Форма с элементами фильтрации -->
                <form method="GET" action="teacher_journal.php" class="filter-form">
                    <input type="hidden" name="teaching_id" value="<?php echo htmlspecialchars($teaching_id); ?>">
                    
                    <!-- Выпадающий список дат -->
                    <div class="filter-element">
                        <select class="date-picker" name="date" onchange="this.form.submit()">
                            <option value="" <?php echo (empty($date) ? 'selected' : ''); ?>>Выберите дату</option>
                            <?php foreach ($dates as $dateItem): ?>
                                <option value="<?php echo htmlspecialchars($dateItem); ?>" 
                                <?php echo ($date === $dateItem ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($dateItem); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- Кнопка статистики -->
                        <button class="primary-btn" onclick="toggleStats()">
                            Статистика
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Кнопки управления -->
    <div class="action-buttons">
        <a href="teacher_journal_edit.php?teaching_id=<?php echo htmlspecialchars($teaching_id); ?>&date=<?php echo htmlspecialchars($date); ?>">
            <button class="primary-btn">Внести изменения</button>
        </a>
        
        <a href="teacher_journal_new.php?teaching_id=<?php echo htmlspecialchars($teaching_id); ?>&date=<?php echo htmlspecialchars($date); ?>">
            <button class="primary-btn">Добавить занятие</button>
        </a>
    </div>
</div>

<!-- Обновленные стили -->
<style>
.control-panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 24px;
}

.filter-section {
    display: flex;
    align-items: center;
    gap: 16px;
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.filter-container {
    display: flex;
    align-items: center;
}

.action-buttons {
    display: flex;
    gap: 16px;
}

.date-picker {
    padding: 12px;
    min-width: 200px;
    border: 1px solid #ccc;
    border-radius: 8px;
}

.primary-btn {
    background-color: #007bff;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.primary-btn:hover {
    background-color: #0056b3;
}

@media (max-width: 768px) {
    .control-panel {
        flex-direction: column;
        gap: 16px;
    }
    
    .filter-section {
        width: 100%;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: space-between;
    }
}
</style>


<script>
function toggleStats() {
    // Получаем необходимые элементы
    const attendance = document.querySelector('.attendance-container');
    const stats = document.querySelector('.stats-container');
    const datePicker = document.querySelector('.date-picker');
    
    // Устанавливаем значение селекта в пустую строку
    datePicker.value = '';
    
    // Переключаем классы hidden
    attendance.classList.toggle('hidden');
    stats.classList.toggle('hidden');
    
    // Обновляем форму
    datePicker.form.submit();
    
    return false;
}

</script>
</div>
</div>
<!-- Нижний колонтитул -->
<footer style="margin-top: 40px;">
    <p>&copy; 2025 Система электронного журнала</p>
</footer>
</body>
</html>

