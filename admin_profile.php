<?php
session_start();
require_once 'db.php';
require_once 'includes/stats.php';

$stats = getTicketStats($pdo);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php"); exit;
}

$adminId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// нов заявки
$stmt = $pdo->query("SELECT t.*, u.full_name, u.office, u.building, u.login FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.status = 'new' ORDER BY t.created_at ASC LIMIT 20");
$newTickets = $stmt->fetchAll();

// устр 
$stmt = $pdo->prepare("SELECT id, inventory_code, device_type, description FROM devices WHERE user_id = ? AND status = 'issued'");
$stmt->execute([$adminId]);
$adminDevices = $stmt->fetchAll();

// фильтр статистики по дате
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$dateSql = "";
$dateParams = [];
if ($dateFrom && $dateTo) {
    $dateSql = " AND created_at BETWEEN ? AND ?";
    $dateParams = ["$dateFrom 00:00:00", "$dateTo 23:59:59"];
}
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE 1=1$dateSql");
$stmt->execute($dateParams);
$totalTickets = $stmt->fetchColumn();

// создание пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $login = trim($_POST['login']);
    $pass = trim($_POST['password']);
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    $position = trim($_POST['position']);
    $birthDate = trim($_POST['birth_date']);
    $mobile = trim($_POST['mobile']);
    $email = trim($_POST['email']);
    $building = trim($_POST['building']);
    $floor = trim($_POST['floor']);
    $office = trim($_POST['office']);

    if ($login && $pass && $fullName && $role && $position && $birthDate) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (login, password, full_name, role, position, birth_date, mobile, email, building, floor, office) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$login, $hash, $fullName, $role, $position, $birthDate, $mobile, $email, $building, $floor, $office]);
        $newUserId = $pdo->lastInsertId();

        // привязка устр
        if (!empty($_POST['devices'])) {
            $deviceIds = explode(',', $_POST['devices']); // Разбиваем строку "1,2,3" на массив
            $ustmt = $pdo->prepare("UPDATE devices SET user_id = ?, status = 'issued' WHERE id = ? AND status = 'warehouse'");
            foreach ($deviceIds as $devId) {
                if ($devId) {
                    $devId = (int)$devId;
                    $ustmt->execute([$newUserId, $devId]);
                }
            }
        }
        header("Location: admin_profile.php"); exit;
    }
}

// взять заявку в работу
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_ticket'])) {
    $ticketId = $_POST['ticket_id'];
    $pdo->prepare("UPDATE tickets SET status = 'in_progress', assigned_to = ? WHERE id = ?")->execute([$adminId, $ticketId]);
    header("Location: admin_profile.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Администратор</title>
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
            <span class="header-user"><?= htmlspecialchars($admin['full_name']) ?></span>
            <a href="users_list.php" class="btn btn-secondary btn-sm">Сотрудники</a>
            <a href="devices_list.php" class="btn btn-secondary btn-sm">Устройства</a>
            <a href="tickets_list.php" class="btn btn-secondary btn-sm">Заявки</a>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-user')">Создать пользователя</button>
            <a href="logout.php" class="btn btn-secondary btn-sm">Выход</a>
        </div>
    </div>
</header>

<div class="container">
    <div class="admin-grid">
        <div>
            <h3 style="margin-bottom:16px;">Новые заявки</h3>
            <?php if (empty($newTickets)): ?>
                <div class="card empty-state admtdm">
                    <p>Нет новых заявок</p>
                </div>
            <?php else: ?>
                <div class="new-tickets-list">
                    <?php foreach ($newTickets as $t): ?>
                        <div class="new-ticket-card" onclick="openModal('modal-ticket-info-<?= $t['id'] ?>')">
                            <div class="nt-number">Заявка #<?= $t['id'] ?></div>
                            <div class="nt-subject"><?= htmlspecialchars($t['subject']) ?></div>
                            <div class="nt-meta"><?= htmlspecialchars($t['office'] ?? '—') ?> / <?= htmlspecialchars($t['building'] ?? '—') ?> — <?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <div class="card tdm">
                <h3 style="margin-bottom:16px;">Профиль администратора</h3>
                <div class="profile-info-grid">
                    <div class="label">Логин</div><div class="value"><?= htmlspecialchars($admin['login']) ?></div>
                    <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($admin['full_name']) ?></div>
                    <div class="label">Должность</div><div class="value"><?= htmlspecialchars($admin['position'] ?? '—') ?></div>
                    <div class="label">Дата рождения</div><div class="value"><?= $admin['birth_date'] ? date('d.m.Y', strtotime($admin['birth_date'])) : '—' ?></div>
                    <div class="label">Мобильный</div><div class="value"><?= htmlspecialchars($admin['mobile'] ?? '—') ?></div>
                    <div class="label">Почта</div><div class="value"><?= htmlspecialchars($admin['email'] ?? '—') ?></div>
                    <div class="label">Здание/Цех</div><div class="value"><?= htmlspecialchars($admin['building'] ?? '—') ?></div>
                    <div class="label">Этаж</div><div class="value"><?= htmlspecialchars($admin['floor'] ?? '—') ?></div>
                    <div class="label">Кабинет</div><div class="value"><?= htmlspecialchars($admin['office'] ?? '—') ?></div>
                    <div class="label">Инвентарные коды</div>
                    <div class="value">
                        <div class="device-tags">
                            <?php if (empty($adminDevices)): ?>
                                <span style="color:#999; font-size:13px;">Нет оборудования</span>
                            <?php else: ?>
                                <?php foreach ($adminDevices as $d): ?>
                                    <span class="device-tag device-tag-lock">
                                        <?= htmlspecialchars($d['inventory_code']) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card tdm">
                <h3 style="margin-bottom:16px;">Статистика заявок</h3>
                <form method="GET">
                    <div class="filter-bar">
                        <div class="input-group">
                            <label>С</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="input-group">
                            <label>По</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Показать</button>
                        <?php if ($dateFrom && $dateTo): ?>
                            <a href="admin_profile.php" class="btn btn-secondary">Сбросить</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div style="text-align:center; padding:16px; background:var(--lavender-light); border-radius:var(--radius-sm);">
                    <div style="font-size:32px; font-weight:700; color:var(--lavender-hover);"><?= $totalTickets ?></div>
                    <div style="font-size:13px; color:var(--text-secondary);">заявок за выбранный период</div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <div>© 2026 - ИТ поддержка</div>
</footer>

<!-- модалка для каждой новой заявки -->
<?php foreach ($newTickets as $t): ?>
<div class="modal-overlay" id="modal-ticket-info-<?= $t['id'] ?>" onclick="closeModalOutside(event, 'modal-ticket-info-<?= $t['id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Заявка #<?= $t['id'] ?></h2>
            <button class="modal-close" onclick="closeModal('modal-ticket-info-<?= $t['id'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="profile-info-grid">
                <div class="label">Номер заявки</div><div class="value">#<?= $t['id'] ?></div>
                <div class="label">Логин</div><div class="value"><a href="#" onclick="openModal('modal-user-info-<?= $t['id'] ?>-<?= $t['user_id'] ?>'); return false;"><?= htmlspecialchars($t['login']) ?></a></div>
                <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($t['full_name']) ?></div>
                <div class="label">Кабинет/Здание</div><div class="value"><?= htmlspecialchars($t['office'] ?? '—') ?> / <?= htmlspecialchars($t['building'] ?? '—') ?></div>
                <div class="label">Дата и время</div><div class="value"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></div>
                <div class="label">Тема</div><div class="value"><?= htmlspecialchars($t['subject']) ?></div>
                <div class="label">Описание</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-ticket-info-<?= $t['id'] ?>')">Закрыть</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                <button type="submit" name="take_ticket" class="btn btn-primary">Взять в работу</button>
            </form>
        </div>
    </div>
</div>

<!-- модалка инфа о пользователе из заявки -->
<div class="modal-overlay" id="modal-user-info-<?= $t['id'] ?>-<?= $t['user_id'] ?>" onclick="closeModalOutside(event, 'modal-user-info-<?= $t['id'] ?>-<?= $t['user_id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Информация о пользователе</h2>
            <button class="modal-close" onclick="closeModal('modal-user-info-<?= $t['id'] ?>-<?= $t['user_id'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <?php
            $us = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $us->execute([$t['user_id']]);
            $ud = $us->fetch();
            $devs = $pdo->prepare("SELECT inventory_code FROM devices WHERE user_id = ? AND status = 'issued'");
            $devs->execute([$t['user_id']]);
            $userDevs = $devs->fetchAll();
            ?>
            <div class="profile-info-grid">
                <div class="label">Логин</div><div class="value"><?= htmlspecialchars($ud['login']) ?></div>
                <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($ud['full_name']) ?></div>
                <div class="label">Должность</div><div class="value"><?= htmlspecialchars($ud['position'] ?? '—') ?></div>
                <div class="label">Дата рождения</div><div class="value"><?= $ud['birth_date'] ? date('d.m.Y', strtotime($ud['birth_date'])) : '—' ?></div>
                <div class="label">Мобильный</div><div class="value"><?= htmlspecialchars($ud['mobile'] ?? '—') ?></div>
                <div class="label">Почта</div><div class="value"><?= htmlspecialchars($ud['email'] ?? '—') ?></div>
                <div class="label">Здание/Цех</div><div class="value"><?= htmlspecialchars($ud['building'] ?? '—') ?></div>
                <div class="label">Этаж</div><div class="value"><?= htmlspecialchars($ud['floor'] ?? '—') ?></div>
                <div class="label">Кабинет</div><div class="value"><?= htmlspecialchars($ud['office'] ?? '—') ?></div>
                <div class="label">Инвентарные коды</div>
                <div class="value">
                    <div class="device-tags">
                        <?php foreach ($userDevs as $ud2): ?>
                            <?php
                            $devInfo = $pdo->prepare("SELECT inventory_code, device_type, status, description FROM devices WHERE inventory_code = ?");
                            $devInfo->execute([$ud2['inventory_code']]);
                            $di = $devInfo->fetch();
                            ?>
                            <span class="device-tag device-tag-lock" style="cursor:pointer;" onclick="openModal('modal-dev-info-<?= $ud2['inventory_code'] ?>-<?= $t['id'] ?>')"><?= htmlspecialchars($ud2['inventory_code']) ?> - <?= htmlspecialchars($di['device_type']) ?></span>

                            <!-- Полная модалка устройства (тоже с уникальным ID) -->
                            <div class="modal-overlay" id="modal-dev-info-<?= $ud2['inventory_code'] ?>-<?= $t['id'] ?>" onclick="closeModalOutside(event, 'modal-dev-info-<?= $ud2['inventory_code'] ?>-<?= $t['id'] ?>')">
                                <div class="modal modal-sm">
                                    <div class="modal-header">
                                        <h2>Устройство <?= htmlspecialchars($ud2['inventory_code']) ?></h2>
                                        <button class="modal-close" onclick="closeModal('modal-dev-info-<?= $ud2['inventory_code'] ?>-<?= $t['id'] ?>')">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>Инвентарный номер:</strong> <?= htmlspecialchars($di['inventory_code']) ?></p>
                                        <p><strong>Тип:</strong> <?= htmlspecialchars($di['device_type']) ?></p>
                                        <p><strong>Статус:</strong> Выдан</p>
                                        <p><strong>Описание:</strong> <?= htmlspecialchars($di['description'] ?? '—') ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-user-info-<?= $t['id'] ?>-<?= $t['user_id'] ?>')">Закрыть</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- модалка создать пользователя -->
<div class="modal-overlay" id="modal-create-user" onclick="closeModalOutside(event, 'modal-create-user')">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2>Создание пользователя</h2>
            <button class="modal-close" onclick="closeModal('modal-create-user')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="input-group">
                        <label>Логин *</label>
                        <input type="text" name="login" required>
                    </div>
                    <div class="input-group">
                        <label>Пароль *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="input-group">
                        <label>ФИО *</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="input-group">
                        <label>Роль *</label>
                        <select name="role" required>
                            <option value="employee">Сотрудник</option>
                            <option value="admin">Админ</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Должность *</label>
                        <input type="text" name="position" required>
                    </div>
                    <div class="input-group">
                        <label>Дата рождения *</label>
                        <input type="date" name="birth_date" required>
                    </div>
                    <div class="input-group">
                        <label>Мобильный</label>
                        <input type="text" name="mobile">
                    </div>
                    <div class="input-group">
                        <label>Почта</label>
                        <input type="email" name="email">
                    </div>
                    <div class="input-group">
                        <label>Здание/Цех</label>
                        <input type="text" name="building">
                    </div>
                    <div class="input-group">
                        <label>Этаж</label>
                        <input type="text" name="floor">
                    </div>
                    <div class="input-group">
                        <label>Кабинет</label>
                        <input type="text" name="office">
                    </div>
                    <div class="input-group">
                        <label>Выданные устройства</label>
                        <div class="device-tags" id="create-user-dev-tags">
                            <span style="color:#999; font-size:13px;">Устройства не выбраны</span>
                        </div>
                        <button class="btn btn-secondary btn-sm" type="button" style="margin-top:8px;" onclick="openAddDeviceModal(0)">Выдать устройство</button>
                        <input type="hidden" name="devices" id="create-user-devices">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-user')">Отмена</button>
                <button type="submit" name="create_user" class="btn btn-primary">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- модалка добавить устройство -->
<div class="modal-overlay" id="modal-add-device" onclick="closeModalOutside(event, 'modal-add-device')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Выбор устройств со склада</h2>
            <button class="modal-close" onclick="closeModal('modal-add-device')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="input-group">
                <label>Поиск</label>
                <input type="text" placeholder="Поиск по коду или типу..." oninput="filterDeviceSelect(this.value)">
            </div>
            <div id="device-select-container">
                <p style="padding:20px; text-align:center; color:#999;">Загрузка устройств...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-add-device')">Отмена</button>
            <button class="btn btn-primary" onclick="addSelectedDevices()">Добавить выбранные</button>
        </div>
    </div>
</div>

<script>
// автообновление
setTimeout(function() {
    const activeElement = document.activeElement
    const isTyping = activeElement && (
        activeElement.tagName === 'INPUT' ||
        activeElement.tagName === 'TEXTAREA' ||
        activeElement.tagName === 'SELECT' ||
        activeElement.isContentEditable
    )
    
    const isModalOpen = document.querySelector('.modal-overlay.active') !== null
    
    if (!isTyping && !isModalOpen) {
        location.reload()
    }
}, 30000)
</script>
<script src="app.js"></script>
</body>
</html>