<?php
// Начинаем сессию
session_start();

// Проверяем, авторизован ли пользователь
if (isset($_SESSION['user_id'])) {
    // Очищаем все данные сессии
    $_SESSION = array();
    
    // Уничтожаем файлы сессии
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Завершаем сессию
    session_destroy();
}

// Перенаправляем пользователя на страницу авторизации
// с сообщением об успешном выходе
header("Location: html_desktop_authorization.php?logout=success");
exit();
?>
