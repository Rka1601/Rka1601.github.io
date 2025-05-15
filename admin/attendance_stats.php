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

// Обработка экспорта в Excel
if (isset($_GET['export'])) {
    $month = $_GET['month'] ?? date('Y-m');
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Статистика_посещаемости_' . $month . '.xls"');
    
    $stmt = $db->prepare("
        SELECT 
            e.id,
            e.last_name,
            e.first_name,
            p.name as position_name,
            COUNT(a.id) as days_worked,
            SUM(TIME_TO_SEC(TIMEDIFF(a.time_out, a.time_in))) as total_seconds,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN attendance a ON a.employee_id = e.id AND DATE_FORMAT(a.date, '%Y-%m') = ?
        WHERE e.status = 'active'
        GROUP BY e.id
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$month]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th>
            <th>Фамилия</th>
            <th>Имя</th>
            <th>Должность</th>
            <th>Отработано дней</th>
            <th>Всего часов</th>
            <th>Опозданий</th>
          </tr>";
    
    foreach ($stats as $row) {
        $hours = floor($row['total_seconds'] / 3600);
        $minutes = floor(($row['total_seconds'] % 3600) / 60);
        $total_time = sprintf("%02d:%02d", $hours, $minutes);
        
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['last_name']}</td>
                <td>{$row['first_name']}</td>
                <td>{$row['position_name']}</td>
                <td>{$row['days_worked']}</td>
                <td>{$total_time}</td>
                <td>{$row['late_count']}</td>
              </tr>";
    }
    echo "</table>";
    exit();
}

// Получение статистики за текущий месяц
$currentMonth = date('Y-m');
$month = $_GET['month'] ?? $currentMonth;

$stmt = $db->prepare("
    SELECT 
        e.id,
        e.last_name,
        e.first_name,
        p.name as position_name,
        COUNT(a.id) as days_worked,
        SUM(TIME_TO_SEC(TIMEDIFF(a.time_out, a.time_in))) as total_seconds,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN attendance a ON a.employee_id = e.id AND DATE_FORMAT(a.date, '%Y-%m') = ?
    WHERE e.status = 'active'
    GROUP BY e.id
    ORDER BY e.last_name, e.first_name
");
$stmt->execute([$month]);
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Статистика посещаемости</h2>
        
        <!-- Фильтр по месяцу -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="month" class="form-label">Месяц:</label>
                        <input type="month" class="form-control" id="month" name="month" 
                               value="<?= htmlspecialchars($month) ?>" max="<?= date('Y-m') ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Показать</button>
                        <a href="attendance_stats.php?export=1&month=<?= $month ?>" 
                           class="btn btn-success">
                            <i class="bi bi-file-excel"></i> Экспорт в Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Таблица статистики -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Фамилия</th>
                                <th>Имя</th>
                                <th>Должность</th>
                                <th>Отработано дней</th>
                                <th>Всего часов</th>
                                <th>Опозданий</th>
                                <th>Детали</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $row): 
                                $hours = floor($row['total_seconds'] / 3600);
                                $minutes = floor(($row['total_seconds'] % 3600) / 60);
                                $total_time = sprintf('%02d:%02d', abs($hours), abs($minutes));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['first_name']) ?></td>
                                <td><?= htmlspecialchars($row['position_name']) ?></td>
                                <td><?= $row['days_worked'] ?></td>
                                <td><?= $total_time ?></td>
                                <td><?= $row['late_count'] ?></td>
<td>
    <a href="attendance_details.php?employee_id=<?= $row['id'] ?>&month=<?= urlencode($month) ?>" 
       class="btn btn-sm btn-primary me-2">
        <i class="bi bi-eye"></i> Подробнее
    </a>
</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>