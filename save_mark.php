<?php
session_start();
require_once 'db.php';

// Проверяем права доступа
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: html_desktop_authorization.php');
    exit();
}

// Получаем данные из формы
$teaching_id = (int)$_POST['teaching_id'] ?? 0;
$date = $_POST['date'] ?? null;
$student_id = (int)$_POST['student_id'] ?? 0;
$mark_id = (int)$_POST['mark'] ?? null;

try {
    // Проверяем существование отметки
    $stmt = $pdo->prepare("SELECT mark_id FROM Marks WHERE mark_id = :mark_id");
    $stmt->execute(['mark_id' => $mark_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Неверная отметка");
    }

    // Получаем ssp_id для студента
    $stmt = $pdo->prepare("
        SELECT ssp.ssp_id
        FROM StudentsSubjectsPlan ssp
        WHERE ssp.student_id = :student_id
        AND ssp.subject_plan_id = (
            SELECT subject_plan_id 
            FROM Teaching 
            WHERE teaching_id = :teaching_id
        )
    ");
    $stmt->execute([
        'student_id' => $student_id,
        'teaching_id' => $teaching_id
    ]);
    $ssp_data = $stmt->fetch();

    if (!$ssp_data) {
        throw new Exception("Не удалось определить связь студента с предметом");
    }

    // Обновляем отметку в журнале
    $stmt = $pdo->prepare("
        UPDATE Journals
        SET mark_id = :mark_id
        WHERE date = :date
        AND ssp_id = :ssp_id
    ");
    $stmt->execute([
        'mark_id' => $mark_id,
        'date' => $date,
        'ssp_id' => $ssp_data['ssp_id']
    ]);

    // Перенаправляем с сообщением об успехе
    header("Location: teacher_journal_edit.php?teaching_id=$teaching_id&date=$date&success=true");
    exit();

} catch (Exception $e) {
    // Логируем ошибку
    error_log("Ошибка сохранения отметки: " . $e->getMessage());
    header("Location: teacher_journal_edit.php?teaching_id=$teaching_id&date=$date&error=true");
    exit();
}
?>
