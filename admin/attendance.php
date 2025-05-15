<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Установка временной зоны (Барнаул, UTC+7)
date_default_timezone_set('Asia/Barnaul');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

$db = (new Database())->getConnection();
$today = date('Y-m-d');

// API: Получение текущего времени
if (isset($_GET['get_current_time'])) {
    header('Content-Type: application/json');
    echo json_encode(['time' => date('H:i')]);
    exit();
}

// API: Обработка отметок посещаемости
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $employeeId = (int)$_POST['employee_id'];
    $action = $_POST['action'];
    
    try {
        if ($action == 'check_in') {
            // Получаем данные о должности сотрудника
            $stmt = $db->prepare("
                SELECT p.work_start 
                FROM employees e
                JOIN positions p ON e.position_id = p.id
                WHERE e.id = ?
            ");
            $stmt->execute([$employeeId]);
            $position = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$position) {
                throw new Exception("Должность сотрудника не найдена");
            }
            
            $workStart = $position['work_start'];
            $currentTime = date('H:i');
            $status = 'present';
            
            // Проверяем опоздание (если текущее время больше времени начала работы + 15 минут)
            $lateTime = date('H:i', strtotime($workStart) + 15 * 60);
            if (strtotime($currentTime) > strtotime($lateTime)) {
                $status = 'late';
            }
            
            $stmt = $db->prepare("
                INSERT INTO attendance (employee_id, date, time_in, status) 
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE time_in = NOW(), status = ?
            ");
            $stmt->execute([$employeeId, $today, $status, $status]);
            
            echo json_encode([
                'success' => true, 
                'time' => $currentTime,
                'status' => $status,
                'work_start' => $workStart
            ]);
        }
        elseif ($action == 'check_out') {
            $stmt = $db->prepare("
                UPDATE attendance 
                SET time_out = NOW() 
                WHERE employee_id = ? AND date = ?
            ");
            $stmt->execute([$employeeId, $today]);
            
            echo json_encode(['success' => true, 'time' => date('H:i')]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// API: Удаление записи посещаемости
if (isset($_GET['delete_attendance'])) {
    $attendanceId = (int)$_GET['delete_attendance'];
    
    try {
        $stmt = $db->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// API: Поиск сотрудников
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $stmt = $db->prepare("
        SELECT 
            e.id, 
            e.first_name, 
            e.last_name, 
            p.name as position_name,
            p.work_start,
            a.id as attendance_id, 
            DATE_FORMAT(a.time_in, '%H:%i') as time_in, 
            DATE_FORMAT(a.time_out, '%H:%i') as time_out,
            a.status
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = CURDATE()
        WHERE e.status = 'active' AND 
              (e.first_name LIKE ? OR e.last_name LIKE ? OR p.name LIKE ?)
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$search, $search, $search]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($employees);
    exit();
}

// Получение списка сотрудников для текущего дня
$stmt = $db->query("
    SELECT 
        e.id, 
        e.first_name, 
        e.last_name, 
        p.name as position_name,
        p.work_start,
        a.id as attendance_id, 
        DATE_FORMAT(a.time_in, '%H:%i') as time_in, 
        DATE_FORMAT(a.time_out, '%H:%i') as time_out,
        a.status
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = CURDATE()
    WHERE e.status = 'active'
    ORDER BY e.last_name, e.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Отметка посещаемости: <?= date('d.m.Y') ?></h2>
        
        <!-- Информация о временной зоне -->
        <div class="alert alert-info mb-4">
            <i class="bi bi-clock"></i> Текущее время: <strong id="currentTime"><?= date('H:i') ?></strong> (Барнаул, UTC+7)
        </div>
        
        <!-- Панель поиска -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="input-group">
                    <input type="text" id="employeeSearch" class="form-control" 
                           placeholder="Поиск по имени, фамилии или должности...">
                    <button class="btn btn-primary" type="button" id="searchBtn">
                        <i class="bi bi-search"></i> Найти
                    </button>
                    <button class="btn btn-secondary" type="button" id="resetSearch">
                        <i class="bi bi-arrow-counterclockwise"></i> Сбросить
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Таблица посещаемости -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Сотрудник</th>
                                <th>Должность</th>
                                <th>Начало работы</th>
                                <th>Пришел</th>
                                <th>Ушел</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php foreach ($employees as $employee): ?>
                            <?php
                                // Определение статуса
                                $statusBadge = '';
                                if ($employee['time_in']) {
                                    if ($employee['status'] == 'late') {
                                        $statusBadge = '<span class="badge bg-warning">Опоздал</span>';
                                    } elseif (!$employee['time_out']) {
                                        $statusBadge = '<span class="badge bg-success">На работе</span>';
                                    } else {
                                        $statusBadge = '<span class="badge bg-info">Завершил</span>';
                                    }
                                } else {
                                    $statusBadge = '<span class="badge bg-secondary">Не отметился</span>';
                                }
                            ?>
                            <tr data-employee-id="<?= $employee['id'] ?>" 
                                data-attendance-id="<?= $employee['attendance_id'] ?? 0 ?>">
                                <td><?= htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']) ?></td>
                                <td><?= htmlspecialchars($employee['position_name']) ?></td>
                                <td><?= htmlspecialchars($employee['work_start']) ?></td>
                                <td class="check-in-time"><?= $employee['time_in'] ?? '--:--' ?></td>
                                <td class="check-out-time"><?= $employee['time_out'] ?? '--:--' ?></td>
                                <td class="attendance-status"><?= $statusBadge ?></td>
                                <td class="attendance-actions">
                                    <?php if (!$employee['time_in']): ?>
                                        <button class="btn btn-sm btn-success check-in-btn">
                                            <i class="bi bi-box-arrow-in-right"></i> Пришел
                                        </button>
                                    <?php elseif (!$employee['time_out']): ?>
                                        <button class="btn btn-sm btn-danger check-out-btn">
                                            <i class="bi bi-box-arrow-right"></i> Ушел
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($employee['attendance_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger delete-attendance-btn ms-2">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обновление текущего времени каждую минуту
    function updateCurrentTime() {
        fetch('attendance.php?get_current_time=1')
            .then(response => response.json())
            .then(data => {
                document.getElementById('currentTime').textContent = data.time;
            });
    }
    setInterval(updateCurrentTime, 60000);
    updateCurrentTime();

    // Обработка отметки "Пришел"
    document.getElementById('attendanceTableBody').addEventListener('click', async function(e) {
        if (e.target.closest('.check-in-btn')) {
            const btn = e.target.closest('.check-in-btn');
            const row = btn.closest('tr');
            const employeeId = row.getAttribute('data-employee-id');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            
            try {
                const response = await fetch('attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `employee_id=${employeeId}&action=check_in`
                });
                
                const data = await response.json();
                if (data.success) {
                    row.querySelector('.check-in-time').textContent = data.time;
                    
                    // Обновляем статус в зависимости от опоздания
                    if (data.status === 'late') {
                        row.querySelector('.attendance-status').innerHTML = '<span class="badge bg-warning">Опоздал</span>';
                    } else {
                        row.querySelector('.attendance-status').innerHTML = '<span class="badge bg-success">На работе</span>';
                    }
                    
                    // Обновляем кнопки действий
                    const actionsCell = row.querySelector('.attendance-actions');
                    actionsCell.innerHTML = `
                        <button class="btn btn-sm btn-danger check-out-btn">
                            <i class="bi bi-box-arrow-right"></i> Ушел
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-attendance-btn ms-2">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                }
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при отметке');
            } finally {
                btn.disabled = false;
            }
        }
    });

    // Обработка отметки "Ушел"
    document.getElementById('attendanceTableBody').addEventListener('click', async function(e) {
        if (e.target.closest('.check-out-btn')) {
            const btn = e.target.closest('.check-out-btn');
            const row = btn.closest('tr');
            const employeeId = row.getAttribute('data-employee-id');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            
            try {
                const response = await fetch('attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `employee_id=${employeeId}&action=check_out`
                });
                
                const data = await response.json();
                if (data.success) {
                    row.querySelector('.check-out-time').textContent = data.time;
                    row.querySelector('.attendance-status').innerHTML = '<span class="badge bg-info">Завершил</span>';
                    
                    const actionsCell = row.querySelector('.attendance-actions');
                    actionsCell.innerHTML = `
                        <button class="btn btn-sm btn-outline-danger delete-attendance-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                }
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при отметке');
            } finally {
                btn.disabled = false;
            }
        }
    });

    // Обработка удаления записи
    document.getElementById('attendanceTableBody').addEventListener('click', async function(e) {
        if (e.target.closest('.delete-attendance-btn')) {
            const btn = e.target.closest('.delete-attendance-btn');
            const row = btn.closest('tr');
            const attendanceId = row.getAttribute('data-attendance-id');
            
            if (!confirm('Вы уверены, что хотите удалить эту запись посещаемости?')) {
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            
            try {
                const response = await fetch(`attendance.php?delete_attendance=${attendanceId}`);
                const data = await response.json();
                
                if (data.success) {
                    row.querySelector('.check-in-time').textContent = '--:--';
                    row.querySelector('.check-out-time').textContent = '--:--';
                    row.querySelector('.attendance-status').innerHTML = '<span class="badge bg-secondary">Не отметился</span>';
                    
                    const actionsCell = row.querySelector('.attendance-actions');
                    actionsCell.innerHTML = `
                        <button class="btn btn-sm btn-success check-in-btn">
                            <i class="bi bi-box-arrow-in-right"></i> Пришел
                        </button>
                    `;
                    
                    // Разблокируем кнопку через 2 секунды
                    setTimeout(() => {
                        const deleteBtn = actionsCell.querySelector('.delete-attendance-btn');
                        if (deleteBtn) deleteBtn.disabled = false;
                    }, 2000);
                }
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при удалении');
            } finally {
                btn.disabled = false;
            }
        }
    });

    // Обработка поиска
    document.getElementById('searchBtn').addEventListener('click', function() {
        const searchQuery = document.getElementById('employeeSearch').value.trim();
        
        if (searchQuery.length < 2) {
            alert('Введите минимум 2 символа для поиска');
            return;
        }
        
        fetch(`attendance.php?search=${encodeURIComponent(searchQuery)}`)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('attendanceTableBody');
                tbody.innerHTML = '';
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">Сотрудники не найдены</td></tr>';
                    return;
                }
                
                data.forEach(employee => {
                    const row = document.createElement('tr');
                    row.setAttribute('data-employee-id', employee.id);
                    row.setAttribute('data-attendance-id', employee.attendance_id || 0);
                    
                    // Определение статуса
                    let statusBadge = '';
                    if (employee.time_in) {
                        if (employee.status === 'late') {
                            statusBadge = '<span class="badge bg-warning">Опоздал</span>';
                        } else if (!employee.time_out) {
                            statusBadge = '<span class="badge bg-success">На работе</span>';
                        } else {
                            statusBadge = '<span class="badge bg-info">Завершил</span>';
                        }
                    } else {
                        statusBadge = '<span class="badge bg-secondary">Не отметился</span>';
                    }
                    
                    row.innerHTML = `
                        <td>${employee.last_name} ${employee.first_name}</td>
                        <td>${employee.position_name}</td>
                        <td>${employee.work_start}</td>
                        <td class="check-in-time">${employee.time_in || '--:--'}</td>
                        <td class="check-out-time">${employee.time_out || '--:--'}</td>
                        <td class="attendance-status">${statusBadge}</td>
                        <td class="attendance-actions">
                            ${!employee.time_in ? 
                              '<button class="btn btn-sm btn-success check-in-btn"><i class="bi bi-box-arrow-in-right"></i> Пришел</button>' : 
                              (!employee.time_out ? 
                              '<button class="btn btn-sm btn-danger check-out-btn"><i class="bi bi-box-arrow-right"></i> Ушел</button>' : '')}
                            
                            ${employee.attendance_id ? 
                              '<button class="btn btn-sm btn-outline-danger delete-attendance-btn ms-2"><i class="bi bi-trash"></i></button>' : ''}
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
            });
    });

    // Сброс поиска
    document.getElementById('resetSearch').addEventListener('click', function() {
        document.getElementById('employeeSearch').value = '';
        location.reload();
    });

    // Поиск при нажатии Enter
    document.getElementById('employeeSearch').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchBtn').click();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>