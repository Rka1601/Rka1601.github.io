<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

$db = (new Database())->getConnection();

// Отображение сообщений
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            '.$_SESSION['error_message'].'
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            '.$_SESSION['success_message'].'
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success_message']);
}

// Обработка добавления должности
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_position'])) {
    $name = $_POST['name'];
    $description = $_POST['description'] ?? '';
    $workStart = $_POST['work_start'];
    $workEnd = $_POST['work_end'];
    
    try {
        $stmt = $db->prepare("INSERT INTO positions (name, description, work_start, work_end) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $workStart, $workEnd]);
        
        $_SESSION['success_message'] = "Должность успешно добавлена";
        header("Location: settings.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при добавлении должности: " . $e->getMessage();
        header("Location: settings.php");
        exit();
    }
}

// Обработка удаления должности
if (isset($_GET['delete_position'])) {
    $id = $_GET['delete_position'];
    try {
        $stmt = $db->prepare("DELETE FROM positions WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success_message'] = "Должность успешно удалена";
        header("Location: settings.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при удалении должности: " . $e->getMessage();
        header("Location: settings.php");
        exit();
    }
}

// Получение списка должностей
$stmt = $db->query("SELECT * FROM positions ORDER BY name");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Управление должностями</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPositionModal">
                    <i class="bi bi-plus-circle"></i> Добавить должность
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($positions)): ?>
                    <div class="alert alert-info">Нет добавленных должностей</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Название</th>
                                    <th>Рабочие часы</th>
                                    <th>Описание</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($positions as $position): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($position['name']) ?></td>
                                        <td><?= htmlspecialchars($position['work_start']) ?> - <?= htmlspecialchars($position['work_end']) ?></td>
                                        <td><?= htmlspecialchars($position['description']) ?></td>
                                        <td>
                                            <a href="edit_position.php?id=<?= $position['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="settings.php?delete_position=<?= $position['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Вы уверены, что хотите удалить эту должность?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
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

<!-- Модальное окно добавления должности -->
<div class="modal fade" id="addPositionModal" tabindex="-1" aria-labelledby="addPositionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPositionModalLabel">Добавить новую должность</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="positionName" class="form-label">Название должности *</label>
                        <input type="text" class="form-control" id="positionName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="positionDesc" class="form-label">Описание</label>
                        <textarea class="form-control" id="positionDesc" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="workStart" class="form-label">Начало рабочего дня *</label>
                            <input type="time" class="form-control" id="workStart" name="work_start" value="09:00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="workEnd" class="form-label">Конец рабочего дня *</label>
                            <input type="time" class="form-control" id="workEnd" name="work_end" value="18:00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="add_position" class="btn btn-primary">Добавить должность</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>