<?php
session_start();
require_once 'db.php';
require_once 'includes/stats.php';

$stats = getTicketStats($pdo, $userId);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header("Location: login.php"); exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// заявки
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM tickets WHERE user_id = ?";
$params = [$userId];

if ($search) {
    $sql .= " AND (subject LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заявки</title>
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
            <a href="user_profile.php" class="btn btn-secondary btn-sm">На главную</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Выход</a>
        </div>
    </div>
</header>

<div class="container" style="margin-top:24px;">
    <div class="card">
        <h3 style="margin-bottom:16px;">Мои заявки</h3>

        <form method="GET" id="search-form">
            <div class="search-bar">
                <div class="input-group mt">
                    <label>Поиск</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск по теме или описанию" onkeydown="searchOnEnter(event, 'search-form')">
                </div>
                <button type="submit" class="btn btn-primary">Поиск</button>
                <?php if ($search): ?>
                    <a href="my_tickets.php" class="btn btn-secondary">Сбросить</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Номер заявки</th>
                        <th>Тема заявки</th>
                        <th>Статус</th>
                        <th>Дата и время подачи</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="4" class="empty-state">Заявки не найдены</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr class="clickable" onclick="openModal('modal-view-<?= $t['id'] ?>')">
                                <td>#<?= $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['subject']) ?></td>
                                <td>
                                    <?php
                                    $statusMap = ['new' => 'Новая', 'in_progress' => 'В работе', 'resolved' => 'Завершена'];
                                    $badgeMap = ['new' => 'badge-new', 'in_progress' => 'badge-progress', 'resolved' => 'badge-resolved'];
                                    ?>
                                    <span class="badge <?= $badgeMap[$t['status']] ?>"><?= $statusMap[$t['status']] ?></span>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="hotline">Горячая линия IT-отдела: +7 (912) 000-00-00 (моб.) | +7 (343) 000-00-00 (стац.)</div>
    <div>© 2026 - ИТ поддержка</div>
</footer>

<!-- модалка просмотра заявки -->
<?php foreach ($tickets as $t): ?>
<div class="modal-overlay" id="modal-view-<?= $t['id'] ?>" onclick="closeModalOutside(event, 'modal-view-<?= $t['id'] ?>')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Заявка #<?= $t['id'] ?></h2>
            <button class="modal-close" onclick="closeModal('modal-view-<?= $t['id'] ?>')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="profile-info-grid">
                <div class="label">Номер заявки</div><div class="value">#<?= $t['id'] ?></div>
                <div class="label">Статус</div>
                <div class="value">
                    <?php
                    $statusMap = ['new' => 'Новая', 'in_progress' => 'В работе', 'resolved' => 'Завершена'];
                    $badgeMap = ['new' => 'badge-new', 'in_progress' => 'badge-progress', 'resolved' => 'badge-resolved'];
                    ?>
                    <span class="badge <?= $badgeMap[$t['status']] ?>"><?= $statusMap[$t['status']] ?></span>
                </div>
                <div class="label">Логин</div><div class="value"><?= htmlspecialchars($user['login']) ?></div>
                <div class="label">ФИО</div><div class="value"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="label">Кабинет/Расположение</div><div class="value"><?= htmlspecialchars($t['office'] ?? '—') ?> / <?= htmlspecialchars($t['building'] ?? '—') ?></div>
                <div class="label">Тема проблемы</div><div class="value"><?= htmlspecialchars($t['subject']) ?></div>
                <div class="label">Подробное описание</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($t['description'])) ?></div>
                <div class="label">Дата и время подачи</div><div class="value"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></div>
                <?php if ($t['status'] === 'resolved' && $t['solution_description']): ?>
                    <div class="label">Описание проделанных работ</div><div class="value" style="grid-column: span 2;"><?= nl2br(htmlspecialchars($t['solution_description'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- модалка создания заявки -->
<div class="modal-overlay" id="modal-create-ticket" onclick="closeModalOutside(event, 'modal-create-ticket')">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2>Создать заявку</h2>
            <button class="modal-close" onclick="closeModal('modal-create-ticket')">&times;</button>
        </div>
        <form method="POST" action="user_profile.php">
            <div class="modal-body">
                <div class="input-group">
                    <label>Логин</label>
                    <input type="text" value="<?= htmlspecialchars($user['login']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="input-group">
                    <label>ФИО</label>
                    <input type="text" value="<?= htmlspecialchars($user['full_name']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="input-group">
                    <label>Кабинет / Расположение</label>
                    <input type="text" name="office" value="<?= htmlspecialchars($user['office'] ?? '') ?>">
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

<script src="app.js"></script>
</body>
</html>