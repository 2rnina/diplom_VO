<?php
session_start();
require_once 'db.php';

// Проверка авторизации преподавателя
if (!isset($_SESSION['entity_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

$group_id = $_GET['group_id'] ?? null;

try {
    // Получение информации о группе и специальности
    $stmt = $pdo->prepare("
        SELECT 
            sg.name_study_group,
            sp.name_specialties
        FROM Study_Group sg
        JOIN Plan p ON sg.plan_id = p.plan_id
        JOIN Study_Terms st ON p.study_terms_id = st.study_terms_id
        JOIN Specialties sp ON st.specialties_id = sp.specialties_id
        WHERE sg.study_group_id = :group_id
    ");
    $stmt->execute(['group_id' => $group_id]);
    $group_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получение списка студентов с контактами
    $stmt = $pdo->prepare("
        SELECT 
            s.student_id,
            s.full_name,
            ci.phone_number,
            ci.email,
            ci.address
        FROM Students s
        LEFT JOIN Contact_Information ci ON s.student_id = ci.student_id
        WHERE s.study_group_id = :group_id
        ORDER BY s.full_name
    ");
    $stmt->execute(['group_id' => $group_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение статистики успеваемости
    $stmt = $pdo->prepare("
        SELECT 
            s.full_name,
            COUNT(j.journal_id) as total_lessons,
            SUM(CASE WHEN j.mark_id IN (2,3,4,5,6) THEN 1 ELSE 0 END) as attended_lessons,
            SUM(CASE WHEN j.mark_id = 1 THEN 1 ELSE 0 END) as missed_lessons,
            ROUND((SUM(CASE WHEN j.mark_id IN (2,3,4,5,6) THEN 1 ELSE 0 END) * 100 / COUNT(j.journal_id)), 2) as attendance_percent,
            ROUND(AVG(CASE WHEN j.mark_id IN (2,3,4,5) THEN j.mark_id END), 2) as average_grade
        FROM Students s
        LEFT JOIN StudentsSubjectsPlan ssp ON s.student_id = ssp.student_id
        LEFT JOIN Journals j ON ssp.ssp_id = j.ssp_id
        WHERE s.study_group_id = :group_id
        AND j.date BETWEEN '2025-09-01' AND '2026-08-31'
        GROUP BY s.student_id, s.full_name
        ORDER BY s.full_name
    ");
    $stmt->execute(['group_id' => $group_id]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика группы</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
        }

        .group-info {
            margin-bottom: 30px;
        }

        .student-list {
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .chart-container {
            margin: 20px 0;
            position: relative;
        }

        canvas {
            max-width: 100%;
        }

        .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: #f8f9fa;
        margin-bottom: 20px;
    }

    .header-title h1 {
        margin: 0;
    }

    .header-buttons {
        display: flex;
        gap: 15px;
    }

    .btn {
        display: inline-block;
        padding: 10px 15px;
        background-color: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        transition: background-color 0.3s;
    }

    .btn:hover {
        background-color: #0056b3;
    }
    </style>
    <!-- Подключение Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <h1>Статистика группы</h1>
            </div>
            <div class="header-buttons">
                <a href="curator_groups.php" class="btn">Назад к списку групп</a>
            </div>
        </div>
    </div>

        <div class="group-info">
            <h2>Группа: <?php echo htmlspecialchars($group_info['name_study_group']); ?></h2>
            <p>Специальность: <?php echo htmlspecialchars($group_info['name_specialties']); ?></p>
        </div>

        <div class="student-list">
            <h3>Список студентов</h3>
            <table>
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Адрес</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td><?php echo htmlspecialchars($student['address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="stats">
            <h3>Статистика успеваемости студентов</h3>
<table class="stats-table">
    <thead>
        <tr>
            <th>ФИО студента</th>
            <th>Количество занятий</th>
            <th>Посещено</th>
            <th>% посещаемости</th>
            <th>Средний балл</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($stats as $stat): ?>
        <tr>
            <td><?php echo htmlspecialchars($stat['full_name']); ?></td>
            <td class="text-center"><?php echo $stat['total_lessons']; ?></td>
            <td class="text-center"><?php echo $stat['attended_lessons']; ?></td>
            <td class="text-center"><?php echo $stat['attendance_percent']; ?>%</td>
            <td class="text-center"><?php echo $stat['average_grade']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Стили для красивой таблицы -->
<style>
.stats-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stats-table th,
.stats-table td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: left;
    vertical-align: middle;
}

.stats-table th {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #333;
}

.stats-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.text-center {
    text-align: center;
}

.stats-table td:nth-child(4) {
    color: #007bff; /* Красный цвет для процента посещаемости */
}

.stats-table td:nth-child(5) {
    color: #28a745; /* Зеленый цвет для среднего балла */
}
</style>

<!-- Продолжение графика -->
<div class="chart-container">
    <canvas id="attendanceChart" width="800" height="400"></canvas>
</div>

<script>
// Подготовка данных для графика
const labels = <?php echo json_encode(array_column($stats, 'full_name')); ?>;
const attended = <?php echo json_encode(array_column($stats, 'attended_lessons')); ?>;
const missed = <?php 
$missedData = array_map(function($stat) {
    return $stat['total_lessons'] - $stat['attended_lessons'];
}, $stats); 
echo json_encode($missedData);
?>;

const ctx = document.getElementById('attendanceChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Посещенные занятия',
                data: attended,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            },
            {
                label: 'Пропущенные занятия (НБ)',
                data: missed,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Количество занятий'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Студенты'
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        responsive: true,
        maintainAspectRatio: false,
        elements: {
            line: {
                tension: 0 // Отключаем сглаживание линий
            }
        },
        layout: {
            padding: {
                left: 0,
                right: 0,
                top: 0,
                bottom: 0
            }
        }
    }
});
</script>

</div> <!-- Закрываем chart-container -->

<!-- Дополнительные стили для графика -->
<style>
.chart-container {
    position: relative;
    margin: 20px 0;
    padding-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .chart-container {
        margin: 20px;
    }
}
</style>

</div> <!-- Закрываем container -->
</body>
</html>
