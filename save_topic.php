<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

// Получаем данные из формы
$teaching_id = (int)$_POST['teaching_id'] ?? 0;
$date = $_POST['date'] ?? null;
$new_topic = trim($_POST['topic']) ?? null;

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();

    // Проверяем, что все данные получены
    if (empty($teaching_id) || empty($date) || empty($new_topic)) {
        throw new Exception('Не все обязательные поля заполнены');
    }

    // Обновляем тему занятия для всех записей
    $stmt = $pdo->prepare("
        UPDATE Journals 
        SET topic = :new_topic
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
        'new_topic' => $new_topic,
        'date' => $date,
        'teaching_id' => $teaching_id
    ]);

    // Если обновление прошло успешно - подтверждаем транзакцию
    $pdo->commit();

    // Перенаправляем обратно с сообщением об успехе
    header("Location: teacher_journal_edit.php?teaching_id=$teaching_id&date=$date&success=true");
    exit();

} catch (Exception $e) {
    // Откатываем изменения при ошибке
    $pdo->rollBack();
    error_log("Ошибка при сохранении темы: " . $e->getMessage());
    
    // Перенаправляем обратно с сообщением об ошибке
    header("Location: teacher_journal_edit.php?teaching_id=$teaching_id&date=$date&error=true");
    exit();
}
?>
