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

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT * FROM devices WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (inventory_code LIKE ? OR device_type LIKE ? OR description LIKE ?)";
    $params = array_fill(0, 3, "%$search%");
}
if ($statusFilter && $statusFilter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = ceil($total / $perPage);

$sql .= " ORDER BY inventory_code LIMIT $perPage OFFSET " . (($page - 1) * $perPage);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

// добавление устр
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_device'])) {
    $inv = trim($_POST['inventory_code']);
    $type = trim($_POST['device_type']);
    $status = $_POST['status'];
    $arrival = trim($_POST['arrival_date']);
    $desc = trim($_POST['description']);

    if ($inv && $type) {
        $stmt = $pdo->prepare("INSERT INTO devices (inventory_code, device_type, status, arrival_date, description) VALUES (?,?,?,?,?)");
        $stmt->execute([$inv, $type, $status, $arrival ?: null, $desc]);
        header("Location: devices_list.php"); exit;
    }
}

// редактирование устр
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_device'])) {
    $devId = $_POST['device_id'];
    $inv = trim($_POST['inventory_code']);
    $type = trim($_POST['device_type']);
    $status = $_POST['status'];
    $arrival = trim($_POST['arrival_date']);
    $desc = trim($_POST['description']);

    if ($inv && $type) {
        $stmt = $pdo->prepare("UPDATE devices SET inventory_code=?, device_type=?, status=?, arrival_date=?, description=? WHERE id=?");
        $stmt->execute([$inv, $type, $status, $arrival ?: null, $desc, $devId]);
        header("Location: devices_list.php" . ($_SERVER['QUERY_STRING'] ? "?" . $_SERVER['QUERY_STRING'] : "")); exit;
    }
}

$statusMap = [
    'warehouse' => 'На складе', 'issued' => 'Выдан',
    'repair' => 'В ремонте', 'written_off' => 'Списан'
];
$badgeMap = [
    'warehouse' => 'badge-warehouse', 'issued' => 'badge-issued',
    'repair' => 'badge-repair', 'written_off' => 'badge-written_off'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Устройства</title>
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
            <a href="users_list.php" class="btn btn-secondary btn-sm">Сотрудники</a>
            <a href="tickets_list.php" class="btn btn-secondary btn-sm">Заявки</a>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-device')">Добавить устройство</button>
            <a href="logout.php" class="btn btn-secondary btn-sm">Выход</a>
        </div>
    </div>
</header>

<div class="container" style="margin-top:24px;">
    <div class="card">
        <h3 style="margin-bottom:16px;">Список устройств</h3>

        <form method="GET" id="search-form">
            <div class="search-bar">
                <div class="input-group left">
                    <label>Поиск</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск по инв. номеру, типу..." onkeydown="searchOnEnter(event, 'search-form')">
                </div>
                <div class="input-group right">
                    <label>Статус</label>
                    <select name="status">
                        <option value="">Все</option>
                        <option value="warehouse" <?= $statusFilter === 'warehouse' ? 'selected' : '' ?>>На складе</option>
                        <option value="issued" <?= $statusFilter === 'issued' ? 'selected' : '' ?>>Выдан</option>
                        <option value="repair" <?= $statusFilter === 'repair' ? 'selected' : '' ?>>В ремонте</option>
                        <option value="written_off" <?= $statusFilter === 'written_off' ? 'selected' : '' ?>>Списан</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Найти</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="devices_list.php" class="btn btn-secondary">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>

        <p style="margin-bottom:12px; font-size:14px; color:var(--text-secondary);">Найдено: <?= $total ?> устройств</p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Инв. номер</th>
                        <th>Тип устройства</th>
                        <th>Статус</th>
                        <th>Дата поступления</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                        <tr><td colspan="4" class="empty-state">Устройства не найдены</td></tr>
                    <?php else: ?>
                        <?php foreach ($devices as $d): ?>
                            <tr class="clickable" onclick="openModal('modal-device-info-<?= $d['id'] ?>')">
                                <td><?= htmlspecialchars($d['inventory_code']) ?></td>
                                <td><?= htmlspecialchars($d['device_type']) ?></td>
                                <td><span class="badge <?= $badgeMap[$d['status']] ?>"><?= $statusMap[$d['status']] ?></span></td>
                                <td><?= $d['arrival_date'] ? date('d.m.Y', strtotime($d['arrival_date'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?= renderPagination($page, $pages, ['search' => $search, 'status' => $statusFilter]) ?>
    </div>
</div>

<footer class="footer">
    <div>© 2026 - ИТ поддержка</div>
</footer>

<!-- модалки устр -->
<?php foreach ($devices as $d): ?>
<div class="modal-overlay" id="modal-device-info-<?= $d['id'] ?>" onclick="closeModalOutside(event, 'modal-device-info-<?= $d['id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Профиль устройства</h2>
            <button class="modal-close" onclick="closeModal('modal-device-info-<?= $d['id'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="profile-info-grid">
                <div class="label">Инвентарный номер</div><div class="value"><?= htmlspecialchars($d['inventory_code']) ?></div>
                <div class="label">Тип устройства</div><div class="value"><?= htmlspecialchars($d['device_type']) ?></div>
                <div class="label">Статус</div><div class="value"><span class="badge <?= $badgeMap[$d['status']] ?>"><?= $statusMap[$d['status']] ?></span></div>
                <div class="label">Дата поступления</div><div class="value"><?= $d['arrival_date'] ? date('d.m.Y', strtotime($d['arrival_date'])) : '—' ?></div>
                <div class="label">Описание</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($d['description'] ?? '—')) ?></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-device-info-<?= $d['id'] ?>')">Закрыть</button>
            <button class="btn btn-primary" onclick="openModal('modal-edit-device-<?= $d['id'] ?>')">Редактировать</button>
        </div>
    </div>
</div>

<!-- редакт устр -->
<div class="modal-overlay" id="modal-edit-device-<?= $d['id'] ?>" onclick="closeModalOutside(event, 'modal-edit-device-<?= $d['id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Редактирование устройства</h2>
            <button class="modal-close" onclick="closeModal('modal-edit-device-<?= $d['id'] ?>')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
            <div class="modal-body">
                <div class="input-group">
                    <label>Инвентарный номер</label>
                    <input type="text" name="inventory_code" value="<?= htmlspecialchars($d['inventory_code']) ?>" required>
                </div>
                <div class="input-group">
                    <label>Тип устройства</label>
                    <input type="text" name="device_type" value="<?= htmlspecialchars($d['device_type']) ?>" required>
                </div>
                <div class="input-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="warehouse" <?= $d['status'] === 'warehouse' ? 'selected' : '' ?>>На складе</option>
                        <option value="issued" <?= $d['status'] === 'issued' ? 'selected' : '' ?>>Выдан</option>
                        <option value="repair" <?= $d['status'] === 'repair' ? 'selected' : '' ?>>В ремонте</option>
                        <option value="written_off" <?= $d['status'] === 'written_off' ? 'selected' : '' ?>>Списан</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Дата поступления</label>
                    <input type="date" name="arrival_date" value="<?= $d['arrival_date'] ?>">
                </div>
                <div class="input-group">
                    <label>Описание</label>
                    <textarea name="description"><?= htmlspecialchars($d['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-device-<?= $d['id'] ?>')">Отмена</button>
                <button type="submit" name="edit_device" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- модалка добав устр -->
<div class="modal-overlay" id="modal-create-device" onclick="closeModalOutside(event, 'modal-create-device')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Добавить устройство</h2>
            <button class="modal-close" onclick="closeModal('modal-create-device')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="input-group">
                    <label>Инвентарный номер</label>
                    <input type="text" name="inventory_code" required>
                </div>
                <div class="input-group">
                    <label>Тип устройства</label>
                    <input type="text" name="device_type" required>
                </div>
                <div class="input-group">
                    <label>Статус</label>
                    <select name="status">
                        <option value="warehouse">На складе</option>
                        <option value="issued">Выдан</option>
                        <option value="repair">В ремонте</option>
                        <option value="written_off">Списан</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Дата поступления</label>
                    <input type="date" name="arrival_date">
                </div>
                <div class="input-group">
                    <label>Описание</label>
                    <textarea name="description"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-device')">Отмена</button>
                <button type="submit" name="add_device" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>

<script src="app.js"></script>
</body>
</html>