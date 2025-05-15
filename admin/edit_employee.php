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
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получение данных сотрудника
$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['error'] = "Сотрудник не найден";
    header("Location: " . SITE_URL . "/admin/employees.php");
    exit();
}

// Получение списка должностей
$positions = $db->query("SELECT * FROM positions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $positionId = (int)$_POST['position_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $db->prepare("UPDATE employees SET first_name = ?, last_name = ?, position_id = ?, status = ? WHERE id = ?");
        $stmt->execute([$firstName, $lastName, $positionId, $status, $employeeId]);
        
        $_SESSION['success'] = "Данные сотрудника обновлены";
        header("Location: " . SITE_URL . "/admin/employees.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка при обновлении: " . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4>Редактирование сотрудника</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">Имя</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($employee['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Фамилия</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($employee['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="position_id" class="form-label">Должность</label>
                        <select class="form-select" id="position_id" name="position_id" required>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= $pos['id'] ?>" <?= $pos['id'] == $employee['position_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pos['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Статус</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="status_active" 
                                   value="active" <?= $employee['status'] == 'active' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_active">Активен</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="status_inactive" 
                                   value="inactive" <?= $employee['status'] == 'inactive' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status_inactive">Неактивен</label>
                        </div>
                    </div>
                    <button type="submit" name="update_employee" class="btn btn-primary">Сохранить</button>
                    <a href="<?= SITE_URL ?>/admin/employees.php" class="btn btn-secondary">Отмена</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>