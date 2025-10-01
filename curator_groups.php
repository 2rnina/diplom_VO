<?php
session_start();
require_once 'db.php';

// Проверка авторизации преподавателя
if (!isset($_SESSION['entity_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

$teacher_id = $_SESSION['entity_id'];
$teacher_full_name = $_SESSION['full_name'] ?? '';

try {
    // Получаем группы, которые курирует преподаватель
    $stmt = $pdo->prepare("
        SELECT 
            sg.study_group_id,
            sg.name_study_group,
            sp.name_specialties,
            COUNT(s.student_id) as student_count
        FROM Study_Group sg
        JOIN Plan p ON sg.plan_id = p.plan_id
        JOIN Study_Terms st ON p.study_terms_id = st.study_terms_id
        JOIN Specialties sp ON st.specialties_id = sp.specialties_id
        LEFT JOIN Students s ON sg.study_group_id = s.study_group_id
        WHERE sg.curator_id IN (
            SELECT c.curator_id 
            FROM Curators c 
            WHERE c.teacher_id = :teacher_id
        )
        GROUP BY sg.study_group_id, sg.name_study_group, sp.name_specialties
    ");
    
    $stmt->execute(['teacher_id' => $teacher_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($groups)) {
        die("Вы не курируете группы");
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
    <title>Кураторские группы</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .curator-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .group-card {
            background-color: #f9f9f9;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .group-card:hover {
            background-color: #f0f0f0;
        }

        .group-details {
            margin-top: 10px;
            color: #666;
        }

        .back-button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
   <div class="curator-container">
        <div class="page-header">
            <h1>Кураторские группы</h1>
            <a href="teacher_dashboard.php" class="back-button">Назад в личный кабинет</a>
        </div>

        <?php if (!empty($groups)): ?>
            <?php foreach ($groups as $group): ?>
                <div class="group-card">
                    <a href="curator_stats.php?group_id=<?php echo htmlspecialchars($group['study_group_id']); ?>">
                        <h2><?php echo htmlspecialchars($group['name_study_group']); ?></h2>
                        <div class="group-details">
                            <span>Специальность: <?php echo htmlspecialchars($group['name_specialties']); ?></span><br>
                            <span>Количество студентов: <?php echo htmlspecialchars($group['student_count']); ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Нет кураторских групп</p>
        <?php endif; ?>
 <footer class="dashboard-footer">
        <p>&copy; 2025 Система электронного журнала</p>
    </footer>
           </div>

    <script src="scripts.js"></script>
</body>
</html>
