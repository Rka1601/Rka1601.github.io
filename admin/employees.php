<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

$db = (new Database())->getConnection();

// Обработка добавления сотрудника
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $positionId = $_POST['position_id'];
    
    $stmt = $db->prepare("INSERT INTO employees (first_name, last_name, position_id) VALUES (?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $positionId]);
    
    header("Location: employees.php");
    exit();
}

// Обработка удаления сотрудника
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: employees.php");
    exit();
}

// Получение списка сотрудников
$stmt = $db->query("
    SELECT e.*, p.name as position_name 
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    ORDER BY e.last_name, e.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение списка должностей
$stmt = $db->query("SELECT * FROM positions ORDER BY name");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Управление сотрудниками</h1>
    </div>
</div>

<?php if ($auth->isAdmin()): ?>
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Добавить сотрудника</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">Имя</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Фамилия</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="position_id" class="form-label">Должность</label>
                            <select class="form-select" id="position_id" name="position_id" required>
                                <option value="">Выберите должность</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo $position['id']; ?>"><?php echo $position['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="add_employee" class="btn btn-primary">Добавить</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>



<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Список сотрудников</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ФИО</th>
                                <th>Должность</th>

                                <th>Статус</th>
                                <?php if ($auth->isAdmin()): ?>
                                <th>Действия</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo $employee['last_name'] . ' ' . $employee['first_name']; ?></td>
                                    <td><?php echo $employee['position_name']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $employee['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $employee['status'] == 'active' ? 'Активен' : 'Неактивен'; ?>
                                        </span>
                                    </td>
<?php if ($auth->isAdmin()): ?>
    <td>
        <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-primary">Редактировать</a>
        <a href="employees.php?delete=<?php echo $employee['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены?')">Удалить</a>
    </td>
<?php endif; ?>
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