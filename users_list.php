<?php
session_start();
require_once 'db.php';
require_once 'includes/stats.php';
require_once 'includes/pagination.php';

$stats = getTicketStats($pdo);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php"); exit;
}

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// поиск
$search = trim($_GET['search'] ?? '');
$sql = "SELECT id, login, full_name, position, building, office FROM users WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (login LIKE ? OR full_name LIKE ? OR position LIKE ? OR building LIKE ? OR office LIKE ?)";
    $params = array_fill(0, 5, "%$search%");
}

// пагинация
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$countStmt = $pdo->prepare(str_replace("SELECT id, login, full_name, position, building, office", "SELECT COUNT(*)", $sql));
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = ceil($total / $perPage);

$sql .= " ORDER BY full_name LIMIT $perPage OFFSET " . (($page - 1) * $perPage);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

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
        $stmt = $pdo->prepare("INSERT INTO users
        (login, password, full_name, role, position, birth_date, mobile, email, building, floor, office) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$login, $hash, $fullName, $role, $position, $birthDate, $mobile, $email, $building, $floor, $office]);
        $newUserId = $pdo->lastInsertId();

        // привязка устройств
        if (!empty($_POST['devices'])) {
            $deviceIds = explode(',', $_POST['devices']);
            $ustmt = $pdo->prepare("UPDATE devices SET user_id = ?, status = 'issued' WHERE id = ? AND status = 'warehouse'");
            foreach ($deviceIds as $devId) {
                if ($devId) {
                    $devId = (int)$devId;
                    $ustmt->execute([$newUserId, $devId]);
                }
            }
        }
        header("Location: users_list.php" . ($search ? "?search=" . urlencode($search) : "")); exit;
    }
}

// обработка редакт пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $editId = $_POST['edit_id'];
    $login = trim($_POST['login']);
    $role = $_POST['role'];
    $fullName = trim($_POST['full_name']);
    $position = trim($_POST['position']);
    $birthDate = trim($_POST['birth_date']);
    $mobile = trim($_POST['mobile']);
    $email = trim($_POST['email']);
    $building = trim($_POST['building']);
    $floor = trim($_POST['floor']);
    $office = trim($_POST['office']);
    $newPass = trim($_POST['new_password']);

    if ($login && $fullName && $role && $position && $birthDate) {
        $sql = "UPDATE users SET login=?, role=?, full_name=?, position=?, birth_date=?, mobile=?, email=?,
        building=?, floor=?, office=? WHERE id=?";
        $p = [$login, $role, $fullName, $position, $birthDate, $mobile, $email, $building, $floor, $office, $editId];
        if ($newPass) {
            $sql = "UPDATE users SET login=?, password=?, role=?, full_name=?, position=?, birth_date=?, mobile=?,
            email=?, building=?, floor=?, office=? WHERE id=?";
            $p = [$login, password_hash($newPass, PASSWORD_DEFAULT),
            $role, $fullName, $position, $birthDate, $mobile, $email, $building, $floor, $office, $editId];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);

        if (!empty($_POST['devices'])) {
            $deviceIds = explode(',', $_POST['devices']);
            $ustmt = $pdo->prepare("UPDATE devices SET user_id = ?, status = 'issued' WHERE id = ? AND status = 'warehouse'");
            foreach ($deviceIds as $devId) {
                if ($devId) {
                    $devId = (int)$devId;
                    $ustmt->execute([$editId, $devId]);
                }
            }
        }
        
        header("Location: users_list.php" . ($search ? "?search=" . urlencode($search) : "")); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сотрудники</title>
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
            <a href="admin_profile.php" class="btn btn-secondary btn-sm">На главную</a>
            <a href="devices_list.php" class="btn btn-secondary btn-sm">Устройства</a>
            <a href="tickets_list.php" class="btn btn-secondary btn-sm">Заявки</a>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-user')">Создать пользователя</button>
            <a href="logout.php" class="btn btn-secondary btn-sm">Выход</a>
        </div>
    </div>
</header>

<div class="container" style="margin-top:24px;">
    <div class="card">
        <h3 style="margin-bottom:16px;">Список пользователей</h3>

        <form method="GET" id="search-form">
            <div class="search-bar">
                <div class="input-group" style="width: 60%;">
                    <label>Поиск пользователей</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск по логину, ФИО, должности..." onkeydown="searchOnEnter(event, 'search-form')">
                </div>
                <button type="submit" class="btn btn-primary">Найти</button>
                <?php if ($search): ?>
                    <a href="users_list.php" class="btn btn-secondary">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>

        <p style="margin-bottom:12px; font-size:14px; color:var(--text-secondary);">Найдено: <?= $total ?> результатов</p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Логин</th>
                        <th>ФИО</th>
                        <th>Должность</th>
                        <th>Здание/Цех</th>
                        <th>Кабинет</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="5" class="empty-state">Пользователи не найдены</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr class="clickable" onclick="openModal('modal-info-about-<?= $u['id'] ?>')">
                                <td><?= htmlspecialchars($u['login']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['position'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($u['building'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($u['office'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= renderPagination($page, $pages, ['search' => $search]) ?>
    </div>
</div>

<footer class="footer">
    <div>© 2026 - ИТ поддержка</div>
</footer>

<!-- модалка информация о пользователе -->
<?php foreach ($users as $u): ?>
<div class="modal-overlay" id="modal-info-about-<?= $u['id'] ?>" onclick="closeModalOutside(event, 'modal-info-about-<?= $u['id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Карточка пользователя</h2>
            <button class="modal-close" onclick="closeModal('modal-info-about-<?= $u['id'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <?php
            $us = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $us->execute([$u['id']]);
            $ud = $us->fetch();
            $devs = $pdo->prepare("
                SELECT id, inventory_code, device_type, description 
                FROM devices 
                WHERE user_id = ? AND status = 'issued'
            ");
            $devs->execute([$u['id']]);
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
                        <?php if (empty($userDevs)): ?>
                            <span style="color:#999; font-size:13px;">Нет оборудования</span>
                        <?php else: ?>
                            <?php foreach ($userDevs as $d): ?>
                                <span class="device-tag-info" style="cursor:pointer;" onclick="openModal('modal-dev-info-<?= $u['id'] ?>-<?= $d['inventory_code'] ?>')"><?= htmlspecialchars($d['inventory_code']) ?> - <?= htmlspecialchars($d['device_type']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger" onclick="deleteUser(<?= $u['id'] ?>)">Удалить</button>
            <div style="flex:1;"></div>
            <button class="btn btn-secondary" onclick="closeModal('modal-info-about-<?= $u['id'] ?>')">Закрыть</button>
            <button class="btn btn-primary" onclick="openModal('modal-edit-user-<?= $u['id'] ?>'); refreshUserDevices(<?= $u['id'] ?>)">
            Редактировать
            </button>
        </div>
    </div>
</div>

<!-- модалка устр пользователя -->
<?php foreach ($userDevs as $d): ?>
<div class="modal-overlay" id="modal-dev-info-<?= $u['id'] ?>-<?= $d['inventory_code'] ?>" onclick="closeModalOutside(event, 'modal-dev-info-<?= $u['id'] ?>-<?= $d['inventory_code'] ?>')">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2>Устройство <?= htmlspecialchars($d['inventory_code']) ?></h2>
            <button class="modal-close" onclick="closeModal('modal-dev-info-<?= $u['id'] ?>-<?= $d['inventory_code'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <p><strong>Инвентарный номер:</strong> <?= htmlspecialchars($d['inventory_code']) ?></p>
            <p><strong>Тип:</strong> <?= htmlspecialchars($d['device_type']) ?></p>
            <p><strong>Статус:</strong> Выдан</p>
            <p><strong>Описание:</strong> <?= htmlspecialchars($d['description']) ?></p>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- модалка редакт пользователя -->
<div class="modal-overlay" id="modal-edit-user-<?= $u['id'] ?>" onclick="closeModalOutside(event, 'modal-edit-user-<?= $u['id'] ?>')">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h2>Редактирование пользователя</h2>
            <button class="modal-close" onclick="closeModal('modal-edit-user-<?= $u['id'] ?>')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="edit_id" value="<?= $u['id'] ?>">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="input-group">
                        <label>Логин *</label>
                        <input type="text" name="login" value="<?= htmlspecialchars($ud['login']) ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Роль *</label>
                        <select name="role" required>
                            <option value="employee" <?= $ud['role'] === 'employee' ? 'selected' : '' ?>>Сотрудник</option>
                            <option value="admin" <?= $ud['role'] === 'admin' ? 'selected' : '' ?>>Админ</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>ФИО *</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($ud['full_name']) ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Должность *</label>
                        <input type="text" name="position" value="<?= htmlspecialchars($ud['position'] ?? '') ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Дата рождения *</label>
                        <input type="date" name="birth_date" value="<?= $ud['birth_date'] ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Мобильный</label>
                        <input type="text" name="mobile" value="<?= htmlspecialchars($ud['mobile'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Почта</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($ud['email'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Здание/Цех</label>
                        <input type="text" name="building" value="<?= htmlspecialchars($ud['building'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Этаж</label>
                        <input type="text" name="floor" value="<?= htmlspecialchars($ud['floor'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Кабинет</label>
                        <input type="text" name="office" value="<?= htmlspecialchars($ud['office'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Новый пароль</label>
                        <input type="password" name="new_password" placeholder="Оставьте пустым, если не меняете">
                    </div>
                    <div class="input-group">
                        <label>Подтверждение пароля</label>
                        <input type="password" name="confirm_password" placeholder="Повторите пароль">
                    </div>
                    <div class="input-group">
                    <label>Выданные устройства</label>
                    <div class="device-tags" id="device-tags-<?= $u['id'] ?>">
                        <span style="color:#999; font-size:13px;">Устройства не выбраны</span>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="margin-top:8px;" onclick="openAddDeviceModal(<?= $u['id'] ?>)">
                        Выдать устройство
                    </button>
                    <input type="hidden" name="devices" id="devices-<?= $u['id'] ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-user-<?= $u['id'] ?>')">Отмена</button>
                <button type="submit" name="edit_user" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- модалка создания пользователя -->
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

<!-- модалка добавления устр -->
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

<div class="modal-overlay" id="modal-confirm">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Подтверждение</h3>
        </div>
        <div class="modal-body">
            <p id="confirm-text"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-confirm')">Отмена</button>
            <button class="btn btn-primary" id="confirm-ok">Да</button>
        </div>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>