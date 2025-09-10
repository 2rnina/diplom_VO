<?php
session_start();

// Проверка выхода из системы
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $message = 'Вы успешно вышли из системы';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.role,
                CASE 
                    WHEN u.role = 'student' THEN s.student_id
                    WHEN u.role = 'teacher' THEN t.teacher_id
                END as entity_id,
                CASE 
                    WHEN u.role = 'student' THEN s.full_name
                    WHEN u.role = 'teacher' THEN t.full_name
                END as full_name,
                u.password
            FROM users u
            LEFT JOIN students s ON u.user_id = s.user_id
            LEFT JOIN teachers t ON u.user_id = t.user_id
            WHERE u.login = :login
        ");
        
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Временная проверка без хеширования
        if ($user && $user['password'] === $password) {
            // Сохраняем данные в сессию
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['entity_id'] = $user['entity_id'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Перенаправление
            if ($user['role'] === 'student') {
                header('Location: student_dashboard.php');
            } elseif ($user['role'] === 'teacher') {
                header('Location: teacher_dashboard.php');
            } else {
                $error = 'Недостаточно прав доступа';
            }
            exit();
        } else {
            $error = '*Неверный логин или пароль';
        }
    } catch (PDOException $e) {
        $error = 'Произошла ошибка при авторизации: ' . $e->getMessage();
    }
}
?>







<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <style>
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            background-color: #f0f0f0;
        }
       
        nav {
            background-color: #ffffff;
            overflow: hidden;
        }
        nav a {
            float: left;
            display: block;
            color: rgb(0, 0, 0);
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }
        nav a:hover {
            background-color: #ddd;
            color: rgb(112, 15, 15);
        }
        
        .navbar {
             text-align: center;
        }

      nav {
    display: flex;
    align-items: center;
    height: 60px; /* задайте нужную высоту */
    background-color: #fff;
}

    .error {
            color: rgb(112, 15, 15);
            font-size: 10px;
            margin-bottom: 10px;
            display: none; /* Скрываем по умолчанию */
        }
        
        .show-error {
            display: block !important; /* Показываем при необходимости */
        }
.form-container {
            max-width: 300px;
            margin: 0 auto;
            text-align: center;}       

.nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 20px;
}

    .nav-links a,
    .nav-login {
        display: flex;
        align-items: center;
        padding: 10px 16px;
    }
    .nav-links {
    display: flex;
    align-items: center;
    gap: 20px;
}

           .container {
            max-width: 300px;
            margin: 50px auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            width: 50px;
        }
        input[type="text"],
        input[type="password"] {
            width: 93%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: "Manrope", sans-serif;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #9A0105;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: "Manrope", sans-serif;
        }
        button:hover {
            background-color: #9A0105;
        }

        .alert {
    padding: 5px;
    margin-bottom: 10px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #3c763d;
    background-color: #dff0d8;
    border-color: #d6e9c6;
}

    </style>
    <?php if (isset($message)): ?>
<div class="alert alert-success">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
</head>
<body>
    <header>
        
    </header>
    <nav>
    <div class="nav-container">
        <a href="#"><img src="image/logo.svg" alt="Логотип"></a>
        <div class="nav-links">
            <a href="#">Журнал</a>
            <a href="#">Расписание</a>
            <a href="#">Электронный университет</a>
        </div>
        <a href="#" class="nav-login"><img style="width: 15px; padding-right: 5px;" src="image/login icon.svg" alt="logo">  Вход</a>
    </div>
</nav>
    <div class="container">
        <div class="logo">
            <img src="image/login icon.svg" alt="Онлайн-журнал">
            <div>Авторизация</div>
        </div>
         <div class="form-container">
            <div class="error" id="error-message">
                <?php if (!empty($error)): ?>
                    <?php echo htmlspecialchars($error); ?>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Вход</button>
            </form>
        </div>
    </div>

  <script>
        // Проверяем, была ли страница загружена через кнопку "назад"
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Если страница была загружена из кэша
                document.location.reload();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const errorMessage = document.getElementById('error-message');
            if (errorMessage.textContent.trim() !== '') {
                errorMessage.classList.add('show-error');
            }
        });
    </script>
</body>
</html>
