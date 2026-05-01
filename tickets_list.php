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
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$activeTab = $_GET['tab'] ?? 'new';

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$statusMap = ['new' => 'Новые', 'in_progress' => 'В работе', 'resolved' => 'Решенные'];

// заявки для вкладки
function getTicketsForTab($pdo, $status, $search, $dateFrom, $dateTo, $page, $perPage) {
    $sql = "SELECT t.*, u.full_name, u.office, u.login 
            FROM tickets t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.status = ?";
    $params = [$status];
    
    if ($search) {
        $sql .= " AND (t.subject LIKE ? OR t.description LIKE ? OR u.full_name LIKE ? OR u.office LIKE ?)";
        $params = array_merge($params, array_fill(0, 4, "%$search%"));
    }
    if ($dateFrom && $dateTo) {
        $sql .= " AND t.created_at BETWEEN ? AND ?";
        $params[] = "$dateFrom 00:00:00";
        $params[] = "$dateTo 23:59:59";
    }
    
    $countSql = str_replace("SELECT t.*, u.full_name, u.office, u.login", "SELECT COUNT(*)", $sql);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $pages = ceil($total / $perPage);
    
    $sql .= " ORDER BY t.created_at ASC LIMIT $perPage OFFSET " . (($page - 1) * $perPage);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['tickets' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages];
}

// данные для активной вкладки во имя облегченяия
$ticketsData = [];
foreach (['new', 'in_progress', 'resolved'] as $s) {
    if ($s === $activeTab) {
        $ticketsData[$s] = getTicketsForTab($pdo, $s, $search, $dateFrom, $dateTo, $page, $perPage);
    } else {
        $ticketsData[$s] = ['tickets' => [], 'total' => 0, 'pages' => 1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_ticket'])) {
    $ticketId = $_POST['ticket_id'];
    $pdo->prepare("UPDATE tickets SET status = 'in_progress', assigned_to = ? WHERE id = ? AND status = 'new'")
        ->execute([$adminId, $ticketId]);
    header("Location: tickets_list.php?tab=new" . ($_SERVER['QUERY_STRING']
    ? "&" . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : "")); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_ticket'])) {
    $ticketId = $_POST['ticket_id'];
    $solution = trim($_POST['solution_description']);
    if ($solution) {
        $pdo->prepare("UPDATE tickets SET status = 'resolved', solution_description =
        ?, resolved_at = NOW() WHERE id = ? AND status = 'in_progress'")
            ->execute([$solution, $ticketId]);
    }
    header("Location: tickets_list.php?tab=in_progress" . ($_SERVER['QUERY_STRING']
    ? "&" . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : "")); exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки</title>
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
            <a href="devices_list.php" class="btn btn-secondary btn-sm">Устройства</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Выход</a>
        </div>
    </div>
</header>

<div class="container" style="margin-top:24px;">
    <div class="card">
        <h3 style="margin-bottom:16px;">Заявки</h3>

        <form method="GET" id="search-form">
            <div class="filter-bar">
                <div class="input-group">
                    <label>Поиск</label>
                    <input style="width: 800px;" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск..." onkeydown="searchOnEnter(event, 'search-form')">
                </div>
                <div class="input-group">
                    <label>С</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="input-group">
                    <label>По</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Найти</button>
                <?php if ($search || $dateFrom || $dateTo): ?>
                    <a href="tickets_list.php" class="btn btn-secondary">Сбросить</a>
                <?php endif; ?>
                <input type="hidden" name="tab" value="<?= $activeTab ?>">
            </div>
        </form>

        <div class="tabs" data-tabs="tickets">
            <?php foreach ($statusMap as $key => $label): 
                $cntSql = "SELECT COUNT(*) FROM tickets WHERE status = ?";
                $cntParams = [$key];
                if ($search) {
                    $cntSql .= " AND (subject LIKE ? OR description LIKE ?)";
                    $cntParams = array_merge($cntParams, ["%$search%", "%$search%"]);
                }
                if ($dateFrom && $dateTo) {
                    $cntSql .= " AND created_at BETWEEN ? AND ?";
                    $cntParams[] = "$dateFrom 00:00:00";
                    $cntParams[] = "$dateTo 23:59:59";
                }
                $cntStmt = $pdo->prepare($cntSql);
                $cntStmt->execute($cntParams);
                $tabCount = $cntStmt->fetchColumn();
            ?>
                <div class="tab <?= $key === $activeTab ? 'active' : '' ?>" 
                     data-tab="<?= $key ?>" 
                     onclick="location.href='?tab=<?= $key ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $dateFrom ? '&date_from='.$dateFrom : '' ?><?= $dateTo ? '&date_to='.$dateTo : '' ?>'">
                    <?= $label ?>
                    <?php if ($tabCount > 0): ?>
                        <span style="margin-left:6px; background:var(--lavender-light); padding:2px 8px; border-radius:10px; font-size:12px;"><?= $tabCount ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($statusMap as $key => $label): ?>
            <div class="tab-content" data-tab="<?= $key ?>" style="display: <?= $key === $activeTab ? 'block' : 'none' ?>;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Номер заявки</th>
                                <th>ФИО</th>
                                <th>Тема заявки</th>
                                <th>Кабинет</th>
                                <th>Дата подачи</th>
                                <?php if ($key === 'resolved'): ?>
                                    <th>Дата решения</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ticketsData[$key]['tickets'])): ?>
                                <tr><td colspan="<?= $key === 'resolved' ? 6 : 5 ?>" class="empty-state">Заявки не найдены</td></tr>
                            <?php else: ?>
                                <?php foreach ($ticketsData[$key]['tickets'] as $t): ?>
                                    <tr class="clickable" onclick="openModal('modal-ticket-<?= $t['id'] ?>')">
                                        <td>#<?= $t['id'] ?></td>
                                        <td><?= htmlspecialchars($t['full_name']) ?></td>
                                        <td><?= htmlspecialchars($t['subject']) ?></td>
                                        <td><?= htmlspecialchars($t['office'] ?? '—') ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                                        <?php if ($key === 'resolved'): ?>
                                            <td><?= $t['resolved_at'] ? date('d.m.Y H:i', strtotime($t['resolved_at'])) : '—' ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- пагинация -->
                <?php if ($key === $activeTab && $ticketsData[$key]['pages'] > 1): ?>
                    <?= renderPagination($page, $ticketsData[$key]['pages'], [
                        'tab' => $key,
                        'search' => $search,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo
                    ]) ?>
                    <p style="margin-top:12px; font-size:13px; color:var(--text-secondary);">
                        Показано <?= count($ticketsData[$key]['tickets']) ?> из <?= $ticketsData[$key]['total'] ?> заявок
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<footer class="footer">
    <div>© 2026 - ИТ поддержка</div>
</footer>

<!-- инфа о заявке -->
<?php foreach ($ticketsData[$activeTab]['tickets'] as $t): ?>
<div class="modal-overlay" id="modal-ticket-<?= $t['id'] ?>" onclick="closeModalOutside(event, 'modal-ticket-<?= $t['id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Заявка #<?= $t['id'] ?></h2>
            <button class="modal-close" onclick="closeModal('modal-ticket-<?= $t['id'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="profile-info-grid">
                <div class="label">Логин</div><div class="value"><a href="#" onclick="openModal('modal-user-info-<?= $t['user_id'] ?>'); return false;"><?= htmlspecialchars($t['login']) ?></a></div>
                <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($t['full_name']) ?></div>
                <div class="label">Кабинет</div><div class="value"><?= htmlspecialchars($t['office'] ?? '—') ?></div>
                <div class="label">Дата подачи</div><div class="value"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></div>
                <?php if ($t['status'] === 'resolved' && $t['resolved_at']): ?>
                    <div class="label">Дата решения</div><div class="value"><?= date('d.m.Y H:i', strtotime($t['resolved_at'])) ?></div>
                <?php endif; ?>
                <div class="label">Тема проблемы</div><div class="value"><?= htmlspecialchars($t['subject']) ?></div>
                <div class="label">Описание проблемы</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
                <?php if ($t['status'] === 'in_progress' || $t['status'] === 'resolved'): ?>
                    <?php if ($t['status'] === 'in_progress'): ?>
                        <div class="label" style="grid-column: span 2;">
                            <label>Описание решения проблемы *</label>
                            <textarea id="solution-<?= $t['id'] ?>" placeholder="Опишите проделанные работы..." style="margin-top:4px; max-width:550px; min-width:550px; padding:10px; border:1px solid var(--border); border-radius:6px; font-family:inherit; min-height:80px;"></textarea>
                        </div>
                    <?php else: ?>
                        <div class="label">Описание решения проблемы</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($t['solution_description'] ?? '—')) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-ticket-<?= $t['id'] ?>')">Закрыть</button>
            <?php if ($t['status'] === 'new'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="tab" value="<?= $activeTab ?>">
                    <button type="submit" name="take_ticket" class="btn btn-primary">Взять в работу</button>
                </form>
            <?php elseif ($t['status'] === 'in_progress'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="tab" value="<?= $activeTab ?>">
                    <input type="hidden" name="solution_description" id="solution-hidden-<?= $t['id'] ?>">
                    <input type="hidden" name="resolve_ticket" value="1">
                    <button type="button" class="btn btn-primary" onclick="submitResolve(<?= $t['id'] ?>)">Завершить</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- инфа о пользователе -->
<?php
$seenUsers = [];
foreach ($ticketsData[$activeTab]['tickets'] as $t) {
    if (!isset($seenUsers[$t['user_id']])) {
        $seenUsers[$t['user_id']] = true;
        $us = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $us->execute([$t['user_id']]);
        $ud = $us->fetch();
        $devs = $pdo->prepare("SELECT id, inventory_code, device_type, description FROM devices WHERE user_id = ? AND status = 'issued'");
        $devs->execute([$t['user_id']]);
        $userDevs = $devs->fetchAll();
        ?>
        <div class="modal-overlay" id="modal-user-info-<?= $t['user_id'] ?>" onclick="closeModalOutside(event, 'modal-user-info-<?= $t['user_id'] ?>')">
            <div class="modal modal-md">
                <div class="modal-header">
                    <h2>Информация о пользователе</h2>
                    <button class="modal-close" onclick="closeModal('modal-user-info-<?= $t['user_id'] ?>')">&times;</button>
                </div>
                <div class="modal-body">
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
                                        <span class="device-tag-info" style="cursor:pointer;" onclick="openModal('modal-dev-info-<?= $t['user_id'] ?>-<?= $d['id'] ?>')">
                                            <?= htmlspecialchars($d['inventory_code']) ?> - <?= htmlspecialchars($d['device_type']) ?>
                                        </span>
                                        <div class="modal-overlay" id="modal-dev-info-<?= $t['user_id'] ?>-<?= $d['id'] ?>" onclick="closeModalOutside(event, 'modal-dev-info-<?= $t['user_id'] ?>-<?= $d['id'] ?>')">
                                            <div class="modal modal-sm">
                                                <div class="modal-header">
                                                    <h2>Устройство <?= htmlspecialchars($d['inventory_code']) ?></h2>
                                                    <button class="modal-close" onclick="closeModal('modal-dev-info-<?= $t['user_id'] ?>-<?= $d['id'] ?>')">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Инвентарный номер:</strong> <?= htmlspecialchars($d['inventory_code']) ?></p>
                                                    <p><strong>Тип:</strong> <?= htmlspecialchars($d['device_type']) ?></p>
                                                    <p><strong>Статус:</strong> Выдан</p>
                                                    <p><strong>Описание:</strong> <?= htmlspecialchars($d['description'] ?? '—') ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('modal-user-info-<?= $t['user_id'] ?>')">Закрыть</button>
                </div>
            </div>
        </div>
        <?php
    }
}
?>

<script>
function submitResolve(ticketId) {
    const textarea = document.getElementById('solution-' + ticketId);
    const hidden = document.getElementById('solution-hidden-' + ticketId);
    if (!textarea.value.trim()) {
        alert('Заполните описание проделанных работ');
        return;
    }
    hidden.value = textarea.value;
    hidden.closest('form').submit();
}
</script>
<script src="app.js"></script>
</body>
</html>