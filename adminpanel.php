<?php
date_default_timezone_set('Asia/Dhaka');

require_once 'db.php';

session_start();

$admin_username = 'admin';
$admin_password = 'admin123@bracu@bd';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
    } else {
        $error = "Invalid credentials. Please try again.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: adminpanel.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getDBConnection();
        
        switch ($_POST['action']) {
            case 'complete_appointment':
                $appointment_id = $_POST['appointment_id'] ?? '';
                if (!empty($appointment_id)) {
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
                    $stmt->execute([$appointment_id]);
                    $message = "Appointment marked as completed successfully!";
                }
                break;
            case 'delete_appointment':
                $appointment_id = $_POST['appointment_id'] ?? '';
                if (!empty($appointment_id)) {
                    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$appointment_id]);
                    $message = "Appointment cancelled successfully!";
                }
                break;
            case 'swap_appointment':
                $appointment_id = $_POST['appointment_id'] ?? '';
                $new_mechanic_id = $_POST['new_mechanic_id'] ?? '';
                $new_time_slot = $_POST['new_time_slot'] ?? '';
                $new_date = $_POST['new_date'] ?? '';
                
                if (!empty($appointment_id) && !empty($new_mechanic_id) && !empty($new_time_slot) && !empty($new_date)) {
                    $stmt = $pdo->prepare("SELECT check_mechanic_availability(?, ?, ?) as is_available");
                    $stmt->execute([$new_mechanic_id, $new_date, $new_time_slot]);
                    $result = $stmt->fetch();
                    
                    if ($result['is_available']) {
                        $stmt = $pdo->prepare("UPDATE appointments SET mechanic_id = ?, appointment_date = ?, time_slot = ? WHERE id = ?");
                        $stmt->execute([$new_mechanic_id, $new_date, $new_time_slot, $appointment_id]);
                        $message = "Appointment swapped successfully!";
                    } else {
                        $error = "The selected time slot is not available for the new mechanic.";
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT mechanic_id, name FROM mechanics WHERE is_active = TRUE ORDER BY mechanic_id");
    $mechanics = $stmt->fetchAll();
} catch (Exception $e) {
    $mechanics = [];
}

$current_date = $_GET['date'] ?? date('Y-m-d');
$selected_mechanic = $_GET['mechanic_id'] ?? '';

$appointments = [];
$mechanic_status = [];

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    try {
        $pdo = getDBConnection();
        
        $where_conditions = ["a.appointment_date = ?"];
        $params = [$current_date];
        
        if (!empty($selected_mechanic)) {
            $where_conditions[] = "a.mechanic_id = ?";
            $params[] = $selected_mechanic;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $stmt = $pdo->prepare("
            SELECT a.*, m.name as mechanic_name 
            FROM appointments a 
            JOIN mechanics m ON a.mechanic_id = m.mechanic_id 
            WHERE $where_clause AND a.status != 'cancelled'
            ORDER BY a.time_slot, a.mechanic_id
        ");
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT 
                m.mechanic_id,
                m.name,
                COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN a.status = 'confirmed' THEN 1 END) as confirmed_count,
                COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed_count,
                CASE 
                    WHEN COUNT(CASE WHEN a.status IN ('pending', 'confirmed') THEN 1 END) >= 4 THEN 'Fully Booked'
                    WHEN COUNT(CASE WHEN a.status IN ('pending', 'confirmed') THEN 1 END) > 0 THEN 'Partially Booked'
                    ELSE 'Available'
                END as status
            FROM mechanics m
            LEFT JOIN appointments a ON m.mechanic_id = a.mechanic_id 
                AND a.appointment_date = ? 
                AND a.status != 'cancelled'
            WHERE m.is_active = TRUE
            GROUP BY m.mechanic_id, m.name
            ORDER BY m.mechanic_id
        ");
        $stmt->execute([$current_date]);
        $mechanic_status = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $error = "Error loading data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Car Mechanic</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    
</head>
<body>

    <div class="page-container">
          <nav class="navbar">
      <div class="nav-container">
        <ul class="nav-menu">
          <li class="nav-item">
            <a href="/" class="nav-link">Home</a>
          </li>
          <li class="nav-item">
            <a href="book-appointment.php" class="nav-link">Book an appointment</a>
          </li>
          <li class="nav-item">
            <a href="track-appointment.php" class="nav-link">Track appointment</a>
          </li>
        
          <li class="nav-item">
            <a href="adminpanel.php" class="nav-link">Admin Panel</a>
          </li>
          
        </ul>
       
      </div>
    </nav>

       
        <main class="body">
            
            <?php if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']): ?>
                
                <div class="login-form">
                    <h1>Admin Login</h1>
                    
                    <?php if ($error): ?>
                        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn">Login</button>
                    </form>
                </div>
                
            <?php else: ?>
                <div class="admin-container">
                    <div class="header">
                        <h1>Admin Panel</h1>
                        <div>
                            
                            <a href="?logout=1" class="btn btn-danger">Logout</a>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <div class="filters">
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($current_date); ?>" 
                                   onchange="window.location.href='?date=' + this.value + '<?php echo !empty($selected_mechanic) ? '&mechanic_id=' . $selected_mechanic : ''; ?>'">
                        </div>
                        
                        
                    </div>
                    
                    <h2>Mechanic Status for <?php echo date('F j, Y', strtotime($current_date)); ?></h2>
                    <div class="mechanic-grid">
                        <?php foreach ($mechanic_status as $mechanic): ?>
                            <div class="mechanic-card <?php echo strtolower(str_replace(' ', '-', $mechanic['status'])); ?>">
                                <h3><?php echo htmlspecialchars($mechanic['name']); ?> (ID: <?php echo $mechanic['mechanic_id']; ?>)</h3>
                                <p><strong>Status:</strong> <?php echo $mechanic['status']; ?></p>
                                <p><strong>Confirmed:</strong> <?php echo $mechanic['confirmed_count']; ?></p>
                                <p><strong>Completed:</strong> <?php echo $mechanic['completed_count']; ?></p>
                                <div style="margin-top:1rem;">
                                    <strong>Time Windows:</strong>
                                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;margin-top:0.5rem;">
                                    <?php
                                    $time_slots = [
                                        '8-10' => '8:00 AM - 10:00 AM',
                                        '11-13' => '11:00 AM - 1:00 PM',
                                        '14-16' => '2:00 PM - 4:00 PM',
                                        '16-18' => '4:00 PM - 6:00 PM'
                                    ];
                                    $pdo = getDBConnection();
                                    foreach ($time_slots as $slot => $label) {
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE mechanic_id = ? AND appointment_date = ? AND time_slot = ? AND status != 'cancelled'");
                                        $stmt->execute([$mechanic['mechanic_id'], $current_date, $slot]);
                                        $row = $stmt->fetch();
                                        $is_booked = $row['cnt'] > 0;
                                        echo '<div style="padding:0.5rem;color: #0f1a0f; border-radius:6px;background:'.($is_booked?'#f8d7da':'#d4edda').';border:1px solid '.($is_booked?'#dc3545':'#28a745').';font-size:0.95em;">';
                                        echo '<span style="font-weight:bold;">'.$label.'</span><br/>';
                                        echo $is_booked ? '<span style="color:#dc3545;">Booked</span>' : '<span style="color:#28a745;">Free</span>';
                                        echo '</div>';
                                    }
                                    ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-group">
                            <label for="mechanic_filter">Filter by Mechanic:</label>
                            <select id="mechanic_filter" onchange="window.location.href='?date=<?php echo $current_date; ?>&mechanic_id=' + this.value">
                                <option value="">All Mechanics</option>
                                <?php foreach ($mechanics as $mechanic): ?>
                                    <option value="<?php echo $mechanic['mechanic_id']; ?>" 
                                            <?php echo ($selected_mechanic == $mechanic['mechanic_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mechanic['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <h2>Appointments for <?php echo date('F j, Y', strtotime($current_date)); ?></h2>
                    
                    <?php if (empty($appointments)): ?>
                        <p>No appointments found for this date.</p>
                    <?php else: ?>
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Time Slot</th>
                                    <th>Mechanic</th>
                                    <th>Client Name</th>
                                    <th>Phone</th>
                                    <th>Car License</th>
                                    <th>Car Engine</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td class="time-slot">
                                            <?php 
                                            $time_display = '';
                                            switch($appointment['time_slot']) {
                                                case '8-10': $time_display = '8:00 AM - 10:00 AM'; break;
                                                case '11-13': $time_display = '11:00 AM - 1:00 PM'; break;
                                                case '14-16': $time_display = '2:00 PM - 4:00 PM'; break;
                                                case '16-18': $time_display = '4:00 PM - 6:00 PM'; break;
                                            }
                                            echo $time_display;
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($appointment['mechanic_name']); ?> (ID: <?php echo $appointment['mechanic_id']; ?>)</td>
                                        <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['client_phone']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['car_license_number']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['car_engine_number']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($appointment['status'] !== 'completed'): ?>
                                                <button class="btn btn-success" onclick="completeAppointment(<?php echo $appointment['id']; ?>)">
                                                    Mark Complete
                                                </button>
                                                <button class="btn btn-warning" onclick="showSwapModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['mechanic_id']; ?>', '<?php echo $appointment['time_slot']; ?>', '<?php echo $appointment['appointment_date']; ?>')">
                                                    Swap
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-danger" onclick="deleteAppointment(<?php echo $appointment['id']; ?>)">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div id="swapModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeSwapModal()">&times;</span>
                        <h2>Swap Appointment</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="swap_appointment">
                            <input type="hidden" name="appointment_id" id="swap_appointment_id">
                            <div class="form-group">
                                <label for="new_mechanic_id">New Mechanic:</label>
                                <select id="new_mechanic_id" name="new_mechanic_id" required>
                                    <option value="">Select mechanic</option>
                                    <?php foreach ($mechanics as $mechanic): ?>
                                        <option value="<?php echo $mechanic['mechanic_id']; ?>">
                                            <?php echo htmlspecialchars($mechanic['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="new_date">New Date:</label>
                                <input type="date" id="new_date" name="new_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="new_time_slot">New Time Slot:</label>
                                <select id="new_time_slot" name="new_time_slot" required>
                                    <option value="">Select time slot</option>
                                    <option value="8-10">8:00 AM - 10:00 AM</option>
                                    <option value="11-13">11:00 AM - 1:00 PM</option>
                                    <option value="14-16">2:00 PM - 4:00 PM</option>
                                    <option value="16-18">4:00 PM - 6:00 PM</option>
                                </select>
                            </div>
                            <button type="submit" class="btn">Swap Appointment</button>
                            <button type="button" class="btn btn-danger" onclick="closeSwapModal()">Cancel</button>
                        </form>
                    </div>
                </div>
                <script>
                    function completeAppointment(appointmentId) {
                        if (confirm('Are you sure you want to mark this appointment as completed?')) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="complete_appointment">
                                <input type="hidden" name="appointment_id" value="${appointmentId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }
                    function deleteAppointment(appointmentId) {
                        if (confirm('Are you sure you want to delete this appointment?')) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="delete_appointment">
                                <input type="hidden" name="appointment_id" value="${appointmentId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }
                    function showSwapModal(appointmentId, currentMechanicId, currentTimeSlot, currentDate) {
                        document.getElementById('swap_appointment_id').value = appointmentId;
                        document.getElementById('new_mechanic_id').value = currentMechanicId;
                        document.getElementById('new_date').value = currentDate;
                        document.getElementById('new_time_slot').value = currentTimeSlot;
                        document.getElementById('swapModal').style.display = 'block';
                    }
                    function closeSwapModal() {
                        document.getElementById('swapModal').style.display = 'none';
                    }
                    window.onclick = function(event) {
                        const modal = document.getElementById('swapModal');
                        if (event.target === modal) {
                            closeSwapModal();
                        }
                    }
                </script>
            <?php endif; ?>
        </main>
        
    </div>
</body>
</html>
