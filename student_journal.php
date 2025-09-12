<?php
session_start();
require_once 'db.php';

// Проверяем авторизацию
if (!isset($_SESSION['entity_id']) || $_SESSION['role'] !== 'student') {
    header('Location: html_desktop_authorization.php');
    exit();
}

// Получаем ssp_id из GET-параметров
$ssp_id = (int)($_GET['ssp_id'] ?? 0);
$student_id = $_SESSION['entity_id'];

try {
    // Проверяем наличие ssp_id
    if ($ssp_id <= 0) {
        header('Location: student_dashboard.php');
        exit();
    }

    // Получаем информацию о студенте
    $stmt = $pdo->prepare("
        SELECT full_name
        FROM Students
        WHERE student_id = :student_id
    ");
    $stmt->execute(['student_id' => $_SESSION['entity_id']]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем основную информацию о дисциплине
    $stmt = $pdo->prepare("
        SELECT 
            su.name_subjects AS subject_name,
            t.full_name AS teacher_name
        FROM StudentsSubjectsPlan ssp
        JOIN SubjectsPlan sp ON ssp.subject_plan_id = sp.subject_plan_id
        JOIN Teaching te ON sp.subject_plan_id = te.subject_plan_id
        JOIN Teachers t ON te.teacher_id = t.teacher_id
        JOIN SubjectsSemester ss ON sp.shs_id = ss.subject_semester_id
        JOIN Subjects su ON ss.subject_id = su.subject_id
        WHERE ssp.ssp_id = :ssp_id
    ");
    
    $stmt->execute(['ssp_id' => $ssp_id]);
    $subject_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject_info) {
        die("Дисциплина не найдена");
    }

    $subject_name = $subject_info['subject_name'];
    $teacher_name = $subject_info['teacher_name'];

    // Получаем параметры периода
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;

    // Основной запрос с фильтрацией по датам
    $stmt = $pdo->prepare("
        SELECT 
            j.date,
            m.name_mark,
            j.topic
        FROM Journals j
        LEFT JOIN Marks m ON j.mark_id = m.mark_id
        WHERE j.ssp_id = :ssp_id
        AND (
            :date_from IS NULL OR j.date >= :date_from
        )
        AND (
            :date_to IS NULL OR j.date <= :date_to
        )
        ORDER BY j.date
    ");
    
    $stmt->execute([
        'ssp_id' => $ssp_id,
        'date_from' => $date_from,
        'date_to' => $date_to
    ]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал посещаемости студента</title>
    
    <!-- Подключение основных стилей -->
    <link rel="stylesheet" href="styles.css">
    
    <!-- Специфические стили для данной страницы -->
    <style>
        .dashboard-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .journal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .subject-info {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .filter-section {
            margin-bottom: 20px;
        }

        .stats-container {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Заголовок страницы -->
        <div class="journal-header">
            <h1 class="page-title">Журнал посещаемости</h1>
            <a href="student_dashboard.php" class="back-button">Назад к дисциплинам</a>
        </div>

        <!-- Информация о дисциплине -->
        <div class="subject-info">
            <h2>
                <?php echo htmlspecialchars($subject_name); ?> 
                <span>(преподаватель: <?php echo htmlspecialchars($teacher_name); ?>)</span>
            </h2>
        </div>

        <!-- Форма фильтрации -->
        <div class="filter-section">
            <form method="GET" action="student_journal.php" id="date-filter-form">
                <input type="hidden" name="ssp_id" value="<?php echo htmlspecialchars($ssp_id); ?>">
                
                <div class="date-filter">
                    <label for="date_from">Период:</label>
                    <input type="date" name="date_from" id="date_from" 
                           value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                    <input type="date" name="date_to" id="date_to" 
                           value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                    
                    <button type="submit" class="back-button">Показать</button>
                    <button type="button" class="back-button" onclick="setFullPeriod()">Вся посещаемость</button>
                </div>
            </form>
        </div>

        <!-- Сообщение об отсутствии данных или таблица -->
        <?php if (empty($data)): ?>
            <div class="no-data-message">
                <p>Данные о посещаемости за выбранный период не найдены</p>
                <p>Попробуйте изменить даты или нажмите кнопку "Вся посещаемость"</p>
                <button onclick="resetPeriod()">Сбросить фильтр</button>
            </div>
        <?php else: ?>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Посещаемость</th>
                        <th>Тема занятия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td>
    <?php 
    // Добавляем условное форматирование для отметок
    $mark = htmlspecialchars($row['name_mark']);
    $markClass = '';
    
    if ($mark === 'НБ') {
        $markClass = 'mark-absent';
    } elseif ($mark === '5') {
        $markClass = 'mark-present';
    }
    ?>
    <span class="<?php echo $markClass; ?>"><?php echo $mark; ?></span>
</td>
<td><?php echo htmlspecialchars($row['topic']); ?></td>
</tr>
<?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Добавляем стили для форматирования отметок -->
        <style>
            /* Стили для отметок в журнале */
            .mark-absent {
                color: #dc3545;
                font-weight: bold;
            }

            .mark-present {
                color: #28a745;
                font-weight: bold;
            }

            /* Дополнительно стилизуем таблицу */
            .attendance-table td {
                vertical-align: middle;
            }

            .attendance-table tr:nth-child(even) {
                background-color: #f8f9fa;
            }

            .attendance-table tr:hover {
                background-color: #e9ecef;
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
        </style>
        <!-- Статистика посещаемости -->
    <div class="stats-container">
        <h2>Статистика посещаемости</h2>
        <?php
        $total_lessons = count($data);
        $present_count = 0;
        
        foreach ($data as $row) {
            if ($row['name_mark'] !== 'НБ') {
                $present_count++;
            }
        }
        
        $present_percent = ($total_lessons > 0) ? 
            round(($present_count / $total_lessons) * 100, 2) : 0;
        ?>
        
        <p>Всего занятий: <?php echo $total_lessons; ?></p>
        <p>Присутствовали: <?php echo $present_count; ?></p>
        <p>Процент посещаемости: <?php echo $present_percent; ?>%</p>
    </div>
    </div>

    


        <!-- JavaScript для функционала -->
        <script>
            function setFullPeriod() {
                document.getElementById('date_from').value = '2025-09-01';
                document.getElementById('date_to').value = '2026-09-01';
                document.getElementById('date-filter-form').submit();
            }

            function resetPeriod() {
                document.getElementById('date_from').value = '2025-09-01';
                document.getElementById('date_to').value = '2026-09-01';
                document.getElementById('date-filter-form').submit();
            }
        </script>
    </body>
</html>