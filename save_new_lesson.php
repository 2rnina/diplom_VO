<?php
require_once 'db.php';

function saveLesson($pdo, $teaching_id, $date, $topic, $selected_marks) {
    try {
        // Получаем subject_plan_id
        $stmt = $pdo->prepare("
            SELECT subject_plan_id 
            FROM Teaching 
            WHERE teaching_id = :teaching_id
        ");
        $stmt->execute(['teaching_id' => $teaching_id]);
        $subject_plan_id = $stmt->fetchColumn();

        // Получаем список студентов
        $stmt = $pdo->prepare("
            SELECT ssp_id, student_id 
            FROM StudentsSubjectsPlan 
            WHERE subject_plan_id = :subject_plan_id
        ");
        $stmt->execute(['subject_plan_id' => $subject_plan_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Создаем запись для каждого студента
        foreach ($students as $student) {
            $ssp_id = $student['ssp_id'];
            $student_id = $student['student_id'];
            
            // Определяем отметку
            $mark_id = isset($selected_marks[$student_id]) ? 
                (int)$selected_marks[$student_id] : 1;

            // Создаем запись в журнале
            $stmt = $pdo->prepare("
                INSERT INTO Journals (date, topic, mark_id, ssp_id)
                VALUES (:date, :topic, :mark_id, :ssp_id)
            ");
            $stmt->execute([
                'date' => $date,
                'topic' => $topic,
                'mark_id' => $mark_id,
                'ssp_id' => $ssp_id
            ]);
        }

        return true;
    } catch (PDOException $e) {
        return "Ошибка при сохранении данных: " . $e->getMessage();
    }
}

