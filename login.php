<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($login && $pass) {
        $stmt = $pdo->prepare("SELECT id, login, password, full_name, role FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin_profile.php");
            } else {
                header("Location: user_profile.php");
            }
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <h1>ИТ <span>Поддержка</span></h1>
        <p>Введите данные для входа в систему</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Логин</label>
                <input type="text" name="login" placeholder="Введите логин" required autofocus>
            </div>
            <div class="input-group">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="Введите пароль" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Войти</button>
        </form>

        <span class="login-link" onclick="openModal('modal-hotline')">Забыли пароль или логин?</span>
    </div>
</div>

<!-- модалка горяч линии -->
<div class="modal-overlay" id="modal-hotline" onclick="closeModalOutside(event, 'modal-hotline')">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2>Горячая линия ИТ-отдела</h2>
            <button class="modal-close" onclick="closeModal('modal-hotline')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:16px; color: var(--text-secondary);">Обратитесь за помощью в восстановлении доступа</p>
            <div class="hotline-grid">
                <div class="hotline-item">
                    <h4>Мобильный</h4>
                    <div class="phone">+7 (912) 000-00-00</div>
                </div>
                <div class="hotline-item">
                    <h4>Стационарный (Екатеринбург)</h4>
                    <div class="phone">+7 (343) 000-00-00</div>
                    <div class="hours">Режим работы: Пн–Пт, 9:00–18:00</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>