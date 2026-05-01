<?php
session_start();
require_once 'db.php';
require_once 'includes/stats.php';

$stats = getTicketStats($pdo, $userId);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: login.php"); exit;
}

$userId = $_SESSION['user_id'];

// профиль пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// последняя заявка
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$lastTicket = $stmt->fetch();

// устр пользователя
$stmt = $pdo->prepare("SELECT inventory_code, device_type FROM devices WHERE user_id = ? AND status = 'issued'");
$stmt->execute([$userId]);
$devices = $stmt->fetchAll();

// обработка редакт профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $building = trim($_POST['building'] ?? '');
    $floor = trim($_POST['floor'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("UPDATE users SET building=?, floor=?, office=?, mobile=?, email=? WHERE id=?");
    $stmt->execute([$building, $floor, $office, $mobile, $email, $userId]);
    header("Location: user_profile.php"); exit;
}

// обработка создания заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $office = trim($_POST['office'] ?? $user['office']);
    $building = trim($_POST['building'] ?? $user['building']);

    if ($subject && $desc) {
        $stmt = $pdo->prepare("INSERT INTO tickets
        (user_id, subject, description, office, building, status) VALUES (?, ?, ?, ?, ?, 'new')");
        $stmt->execute([$userId, $subject, $desc, $office, $building]);
        header("Location: user_profile.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<header class="header">
    <div class="header-inner">
        <div class="header-logo">ИТ <span>Поддержка</span></div>
        <div class="header-stats">
            <span class="stat-new">Новые: <?= $stats['new'] ?></span>
            <span class="stat-progress">В работе: <?= $stats['in_progress'] ?></span>
            <span class="stat-resolved">Выполненные: <?= $stats['resolved'] ?></span>
        </div>
        <div class="header-nav">
            <span class="header-user"><?= htmlspecialchars($user['full_name']) ?></span>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-ticket')">Создать заявку</button>
            <a href="my_tickets.php" class="btn btn-secondary btn-sm">Мои заявки</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Выход</a>
        </div>
    </div>
</header>

<div class="container">
    <div class="profile-grid">
        <div class="card tdm">
            <h3 style="margin-bottom:16px;">Профиль сотрудника</h3>
            <div class="profile-info-grid">
                <div class="label">Логин</div><div class="value"><?= htmlspecialchars($user['login']) ?></div>
                <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="label">Должность</div><div class="value"><?= htmlspecialchars($user['position'] ?? '—') ?></div>
                <div class="label">Дата рождения</div><div class="value"><?= $user['birth_date'] ? date('d.m.Y', strtotime($user['birth_date'])) : '—' ?></div>
                <div class="label">Здание/Цех</div><div class="value"><?= htmlspecialchars($user['building'] ?? '—') ?></div>
                <div class="label">Этаж</div><div class="value"><?= htmlspecialchars($user['floor'] ?? '—') ?></div>
                <div class="label">Кабинет</div><div class="value"><?= htmlspecialchars($user['office'] ?? '—') ?></div>
                <div class="label">Мобильный телефон</div><div class="value"><?= htmlspecialchars($user['mobile'] ?? '—') ?></div>
                <div class="label">Электронная почта</div><div class="value"><?= htmlspecialchars($user['email'] ?? '—') ?></div>
                <div class="label">Инвентарные коды</div>
                <div class="value">
                    <div class="device-tags">
                        <?php if (empty($devices)): ?>
                            <span style="color:#999; font-size:13px;">Нет оборудования</span>
                        <?php else: ?>
                            <?php foreach ($devices as $d): ?>
                                <span class="device-tag device-tag-lock" style="cursor:default;"><?= htmlspecialchars($d['inventory_code']) ?> - <?= htmlspecialchars($d['device_type']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:16px;">
                <button class="btn btn-secondary btn-sm" onclick="openModal('modal-edit-profile')">Редактировать</button>
            </div>
        </div>

        <div class="card tdm">
            <h3 style="margin-bottom:16px;">Последняя заявка</h3>
            <?php if ($lastTicket): ?>
                <div class="profile-info-grid">
                    <div class="label">Номер заявки</div><div class="value">#<?= $lastTicket['id'] ?></div>
                    <div class="label">Логин</div><div class="value"><?= htmlspecialchars($user['login']) ?></div>
                    <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="label">Кабинет/Расположение</div><div class="value"><?= htmlspecialchars($lastTicket['office'] ?? '—') ?> / <?= htmlspecialchars($lastTicket['building'] ?? '—') ?></div>
                    <div class="label">Тема</div><div class="value"><?= htmlspecialchars($lastTicket['subject']) ?></div>
                    <div class="label">Описание</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($lastTicket['description'])) ?></div>
                    <div class="label">Время подачи</div><div class="value"><?= date('d.m.Y H:i', strtotime($lastTicket['created_at'])) ?></div>
                    <div class="label">Статус</div>
                    <div class="value">
                        <?php
                        $statusMap = ['new' => 'Новая', 'in_progress' => 'В работе', 'resolved' => 'Завершена'];
                        $badgeMap = ['new' => 'badge-new', 'in_progress' => 'badge-progress', 'resolved' => 'badge-resolved'];
                        ?>
                        <span class="badge <?= $badgeMap[$lastTicket['status']] ?>"><?= $statusMap[$lastTicket['status']] ?></span>
                    </div>
                    <?php if ($lastTicket['status'] === 'resolved' && $lastTicket['solution_description']): ?>
                        <div class="label">Описание работ</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($lastTicket['solution_description'])) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="margin: 0 auto;">
                    <p>У вас ещё нет заявок</p>
                    <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-ticket')" style="margin-top:12px;">Создать первую заявку</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="hotline">Горячая линия IT-отдела: +7 (912) 000-00-00 (моб.) | +7 (343) 000-00-00 (стац.)</div>
    <div>© 2026 - ИТ поддержка</div>
</footer>

<!-- модалка создать заявку -->
<div class="modal-overlay" id="modal-create-ticket" onclick="closeModalOutside(event, 'modal-create-ticket')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Создать заявку</h2>
            <button class="modal-close" onclick="closeModal('modal-create-ticket')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="input-group">
                    <label>Логин</label>
                    <input type="text" value="<?= htmlspecialchars($user['login']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="input-group">
                    <label>ФИО</label>
                    <input type="text" name="full_name_display" value="<?= htmlspecialchars($user['full_name']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="input-group">
                    <label>Кабинет / Расположение</label>
                    <input type="text" name="office" value="<?= htmlspecialchars($user['office'] ?? '') ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="input-group">
                    <label>Тема проблемы</label>
                    <input type="text" name="subject" placeholder="Опишите кратко проблему" required>
                </div>
                <div class="input-group">
                    <label>Подробное описание</label>
                    <textarea name="description" placeholder="Подробно опишите проблему..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-ticket')">Отмена</button>
                <button type="submit" name="create_ticket" class="btn btn-primary">Отправить</button>
            </div>
        </form>
    </div>
</div>

<!-- модалка редактировать профиль -->
<div class="modal-overlay" id="modal-edit-profile" onclick="closeModalOutside(event, 'modal-edit-profile')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Редактировать профиль</h2>
            <button class="modal-close" onclick="closeModal('modal-edit-profile')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="input-group">
                    <label>Здание/Цех</label>
                    <input type="text" name="building" value="<?= htmlspecialchars($user['building'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Этаж</label>
                    <input type="text" name="floor" value="<?= htmlspecialchars($user['floor'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Кабинет</label>
                    <input type="text" name="office" value="<?= htmlspecialchars($user['office'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Мобильный телефон</label>
                    <input type="text" name="mobile" value="<?= htmlspecialchars($user['mobile'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Электронная почта</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-profile')">Отмена</button>
                <button type="submit" name="edit_profile" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- модалка горячая линия -->
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