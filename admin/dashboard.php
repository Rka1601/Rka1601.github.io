<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php';

$db = (new Database())->getConnection();
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Главная панель</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <h5 class="card-title">Сотрудники</h5>
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'");
                $count = $stmt->fetchColumn();
                ?>
                <p class="card-text display-4"><?php echo $count; ?></p>
                <a href="<?php echo SITE_URL; ?>/admin/employees.php" class="text-white">Подробнее <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <h5 class="card-title">Пришли вовремя сегодня</h5>
                <?php
                $today = date('Y-m-d');
                $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date = :date AND status = 'present'");
                $stmt->bindParam(':date', $today);
                $stmt->execute();
                $count = $stmt->fetchColumn();
                ?>
                <p class="card-text display-4"><?php echo $count; ?></p>
                <a href="<?php echo SITE_URL; ?>/admin/attendance.php" class="text-white">Подробнее <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <h5 class="card-title">Опоздавших сегодня</h5>
                <?php
                $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date = :date AND status = 'late'");
                $stmt->bindParam(':date', $today);
                $stmt->execute();
                $count = $stmt->fetchColumn();
                ?>
                <p class="card-text display-4"><?php echo $count; ?></p>
                <a href="<?php echo SITE_URL; ?>/admin/attendance.php" class="text-white">Подробнее <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Последние отметки</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Сотрудник</th>
                                <th>Дата</th>
                                <th>Пришел</th>
                                <th>Ушел</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $db->query("
                                SELECT a.*, e.first_name, e.last_name 
                                FROM attendance a
                                JOIN employees e ON a.employee_id = e.id
                                ORDER BY a.date DESC, a.time_in DESC
                                LIMIT 10
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>
                                    <td>{$row['first_name']} {$row['last_name']}</td>
                                    <td>{$row['date']}</td>
                                    <td>{$row['time_in']}</td>
                                    <td>{$row['time_out']}</td>
                                    <td>";
                                switch ($row['status']) {
                                    case 'present':
                                        echo '<span class="badge bg-success">Присутствовал</span>';
                                        break;
                                    case 'absent':
                                        echo '<span class="badge bg-danger">Отсутствовал</span>';
                                        break;
                                    case 'late':
                                        echo '<span class="badge bg-warning">Опоздал</span>';
                                        break;
                                    case 'leave':
                                        echo '<span class="badge bg-info">Ушел раньше</span>';
                                        break;
                                }
                                echo "</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>