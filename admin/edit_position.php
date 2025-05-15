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
$positionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получение данных должности
$stmt = $db->prepare("SELECT * FROM positions WHERE id = ?");
$stmt->execute([$positionId]);
$position = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$position) {
    $_SESSION['error'] = "Должность не найдена";
    header("Location: " . SITE_URL . "/admin/settings.php");
    exit();
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_position'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $workStart = $_POST['work_start'];
    $workEnd = $_POST['work_end'];
    
    try {
        $stmt = $db->prepare("UPDATE positions SET name = ?, description = ?, work_start = ?, work_end = ? WHERE id = ?");
        $stmt->execute([$name, $description, $workStart, $workEnd, $positionId]);
        
        $_SESSION['success'] = "Должность успешно обновлена";
        header("Location: " . SITE_URL . "/admin/settings.php");
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
                <h4>Редактирование должности</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= htmlspecialchars($position['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3"><?= htmlspecialchars($position['description']) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="work_start" class="form-label">Начало работы</label>
                            <input type="time" class="form-control" id="work_start" name="work_start" 
                                   value="<?= htmlspecialchars($position['work_start']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="work_end" class="form-label">Конец работы</label>
                            <input type="time" class="form-control" id="work_end" name="work_end" 
                                   value="<?= htmlspecialchars($position['work_end']) ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="update_position" class="btn btn-primary">Сохранить</button>
                    <a href="<?= SITE_URL ?>/admin/settings.php" class="btn btn-secondary">Отмена</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>