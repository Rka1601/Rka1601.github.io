<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

$db = (new Database())->getConnection();

// Получаем ID сотрудника и месяц из GET-параметров
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Проверяем валидность месяца
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

// Получаем информацию о сотруднике
$stmt = $db->prepare("
    SELECT e.id, e.first_name, e.last_name, p.name as position_name 
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['error'] = "Сотрудник не найден";
    header("Location: attendance_stats.php");
    exit();
}

// Получаем детализацию посещаемости
$stmt = $db->prepare("
    SELECT 
        a.date,
        DATE_FORMAT(a.time_in, '%H:%i') as time_in,
        DATE_FORMAT(a.time_out, '%H:%i') as time_out,
        a.status,
        TIMEDIFF(a.time_out, a.time_in) as work_time,
        p.work_start
    FROM attendance a
    LEFT JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE a.employee_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?
    ORDER BY a.date
");
$stmt->execute([$employeeId, $month]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Рассчитываем общую статистику
$totalDays = count($attendance);
$totalLate = 0;
$totalHours = 0;
$totalMinutes = 0;

foreach ($attendance as $day) {
    if ($day['status'] == 'late') $totalLate++;
    if ($day['work_time']) {
        list($h, $m, $s) = explode(':', $day['work_time']);
        $totalHours += (int)$h;
        $totalMinutes += (int)$m;
    }
}

$totalHours += floor($totalMinutes / 60);
$totalMinutes = $totalMinutes % 60;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">
            <i class="bi bi-person-lines-fill"></i> Детализация посещаемости
        </h2>
        
        <!-- Информация о сотруднике -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4><?= htmlspecialchars($employee['last_name']) ?> <?= htmlspecialchars($employee['first_name']) ?></h4>
                        <p class="mb-1"><strong>Должность:</strong> <?= htmlspecialchars($employee['position_name']) ?></p>
                        <p class="mb-1"><strong>Месяц:</strong> <?= date('F Y', strtotime($month . '-01')) ?></p>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <strong>Статистика за месяц:</strong><br>
                            Отработано дней: <?= $totalDays ?><br>
                            Всего часов: <?= $totalHours ?> ч <?= $totalMinutes ?> мин<br>
                            Опозданий: <?= $totalLate ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Таблица с детализацией -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Посещаемость по дням</h5>
                <a href="attendance_stats.php?month=<?= urlencode($month) ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Назад
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($attendance)): ?>
                    <div class="alert alert-warning">Нет данных о посещаемости за выбранный период</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Начало работы</th>
                                    <th>Пришел</th>
                                    <th>Ушел</th>
                                    <th>Отработано</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $day): ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($day['date'])) ?></td>
                                    <td><?= htmlspecialchars($day['work_start']) ?></td>
                                    <td><?= htmlspecialchars($day['time_in']) ?></td>
                                    <td><?= htmlspecialchars($day['time_out']) ?></td>
                                    <td><?= htmlspecialchars($day['work_time']) ?></td>
                                    <td>
                                        <?php if ($day['status'] == 'late'): ?>
                                            <span class="badge bg-warning">Опоздал</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Присутствовал</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>