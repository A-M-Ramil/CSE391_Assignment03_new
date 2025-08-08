<?php
date_default_timezone_set('Asia/Dhaka');

require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        $client_phone = $_POST['client_phone'] ?? '';
        $client_name = $_POST['client_name'] ?? '';
        $client_address = $_POST['client_address'] ?? '';
        $car_license_number = $_POST['car_license_number'] ?? '';
        $car_engine_number = $_POST['car_engine_number'] ?? '';
        $mechanic_id = $_POST['mechanic_id'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';
        $time_slot = $_POST['time_slot'] ?? '';
        
        if (empty($client_phone) || empty($client_name) || empty($client_address) || 
            empty($car_license_number) || empty($car_engine_number) || empty($mechanic_id) || 
            empty($appointment_date) || empty($time_slot)) {
            throw new Exception("All required fields must be filled.");
        }
        
        $today = date('Y-m-d');
        $current_time = date('H:i');
        
        if ($appointment_date === $today) {
            $slot_start_time = '';
            switch($time_slot) {
                case '8-10': $slot_start_time = '08:00'; break;
                case '11-13': $slot_start_time = '11:00'; break;
                case '14-16': $slot_start_time = '14:00'; break;
                case '16-18': $slot_start_time = '16:00'; break;
            }
            
            if ($current_time >= $slot_start_time) {
                throw new Exception("This time slot has already passed for today. Please select a future time slot or date.");
            }
        }
        
        $stmt = $pdo->prepare("SELECT check_mechanic_availability(?, ?, ?) as is_available");
        $stmt->execute([$mechanic_id, $appointment_date, $time_slot]);
        $result = $stmt->fetch();
        
        if (!$result['is_available']) {
            throw new Exception("This time slot is already booked. Please select a different time slot.");
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as car_booked_count 
            FROM appointments 
            WHERE car_engine_number = ? 
            AND appointment_date = ? 
            AND time_slot = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$car_engine_number, $appointment_date, $time_slot]);
        $car_result = $stmt->fetch();
        
        if ($car_result['car_booked_count'] > 0) {
            throw new Exception("This car is already booked with another mechanic for the same time slot. Please select a different time slot or date.");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO appointments (
                client_phone, client_name, client_address, car_license_number, 
                car_engine_number, mechanic_id, appointment_date, time_slot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $client_phone, $client_name, $client_address, $car_license_number,
            $car_engine_number, $mechanic_id, $appointment_date, $time_slot
        ]);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO mechanic_schedule (mechanic_id, schedule_date, current_appointments)
                VALUES (?, ?, 1)
                ON CONFLICT (mechanic_id, schedule_date) 
                DO UPDATE SET current_appointments = mechanic_schedule.current_appointments + 1
            ");
            $stmt->execute([$mechanic_id, $appointment_date]);
        } catch (Exception $e) {
        }
        
        $message = "Appointment booked successfully! Your appointment ID is: " . $pdo->lastInsertId();
        
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

$available_slots = [];
$selected_mechanic = $_GET['mechanic_id'] ?? '';
$selected_date = $_GET['appointment_date'] ?? '';

if (!empty($selected_mechanic) && !empty($selected_date)) {
    try {
        $pdo = getDBConnection();
        
        $today = date('Y-m-d');
        $current_time = date('H:i');
        $is_today = ($selected_date === $today);
        
        $all_slots = ['8-10', '11-13', '14-16', '16-18'];
        
        foreach ($all_slots as $slot) {
            $status = 'Available';
            
            if ($is_today) {
                $slot_start_time = '';
                switch($slot) {
                    case '8-10': $slot_start_time = '08:00'; break;
                    case '11-13': $slot_start_time = '11:00'; break;
                    case '14-16': $slot_start_time = '14:00'; break;
                    case '16-18': $slot_start_time = '16:00'; break;
                }
                
                if ($current_time >= $slot_start_time) {
                    $status = 'Passed';
                }
            }
            
            if ($status !== 'Passed') {
                $stmt = $pdo->prepare("SELECT check_mechanic_availability(?, ?, ?) as is_available");
                $stmt->execute([$selected_mechanic, $selected_date, $slot]);
                $result = $stmt->fetch();
                
                if (!$result['is_available']) {
                    $status = 'Booked';
                } else {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as car_booked_count 
                        FROM appointments 
                        WHERE appointment_date = ? 
                        AND time_slot = ? 
                        AND status != 'cancelled'
                    ");
                    $stmt->execute([$selected_date, $slot]);
                    $car_result = $stmt->fetch();
                    
                    if ($car_result['car_booked_count'] > 0) {
                        $status = 'Limited';
                    }
                }
            }
            
            $available_slots[] = [
                'time_slot' => $slot,
                'status' => $status
            ];
        }
    } catch (Exception $e) {
        $available_slots = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Car Mechanic</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="bookapp.css">
</head>
<body>
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
    <div class="page-container">
        
        
        <main>
                        
            <div class="booking-form">
                <h1>Book an Appointment</h1>
                <p>Book your car service appointment with our expert mechanics.</p>
                
                <?php if ($message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" onsubmit="return validateForm();">
                    <div class="form-group">
                        <label for="mechanic_id">Select Mechanic *</label>
                        <select id="mechanic_id" name="mechanic_id" required onchange="checkAvailability()">
                            <option value="">Choose a mechanic</option>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <option value="<?php echo $mechanic['mechanic_id']; ?>" 
                                        <?php echo ($selected_mechanic == $mechanic['mechanic_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mechanic['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_date">Appointment Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" required 
                               min="<?php echo date('Y-m-d'); ?>" onchange="checkAvailability()"
                               value="<?php echo htmlspecialchars($selected_date); ?>">
                    </div>
                    
                    <?php if (!empty($available_slots)): ?>
                    <div class="form-group">
                        <label>Available Time Slots for <?php echo htmlspecialchars($selected_date); ?>:</label>
                        <div class="availability-grid">
                            <?php foreach ($available_slots as $slot): ?>
                                <div class="slot-item <?php echo strtolower($slot['status']); ?>">
                                    <span class="slot-time">
                                        <?php 
                                        $time_display = '';
                                        switch($slot['time_slot']) {
                                            case '8-10': $time_display = '8:00 AM - 10:00 AM'; break;
                                            case '11-13': $time_display = '11:00 AM - 1:00 PM'; break;
                                            case '14-16': $time_display = '2:00 PM - 4:00 PM'; break;
                                            case '16-18': $time_display = '4:00 PM - 6:00 PM'; break;
                                        }
                                        echo $time_display;
                                        ?>
                                    </span>
                                    <span class="slot-status"><?php echo $slot['status']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="time_slot">Time Slot *</label>
                        <select id="time_slot" name="time_slot" required>
                            <option value="">Choose time slot</option>
                            <?php
                            $today = date('Y-m-d');
                            $current_time = date('H:i');
                            $is_today = ($selected_date === $today);
                            
                            $time_slots = [
                                '8-10' => '8:00 AM - 10:00 AM',
                                '11-13' => '11:00 AM - 1:00 PM',
                                '14-16' => '2:00 PM - 4:00 PM',
                                '16-18' => '4:00 PM - 6:00 PM'
                            ];
                            
                            foreach ($time_slots as $slot => $display) {
                                $disabled = '';
                                $text = $display;
                                
                                if ($is_today) {
                                    switch($slot) {
                                        case '8-10': 
                                            if ($current_time >= '08:00') {
                                                $disabled = 'disabled';
                                                $text .= ' (Passed)';
                                            }
                                            break;
                                        case '11-13': 
                                            if ($current_time >= '11:00') {
                                                $disabled = 'disabled';
                                                $text .= ' (Passed)';
                                            }
                                            break;
                                        case '14-16': 
                                            if ($current_time >= '14:00') {
                                                $disabled = 'disabled';
                                                $text .= ' (Passed)';
                                            }
                                            break;
                                        case '16-18': 
                                            if ($current_time >= '16:00') {
                                                $disabled = 'disabled';
                                                $text .= ' (Passed)';
                                            }
                                            break;
                                    }
                                }
                                
                                echo "<option value=\"$slot\" $disabled>$text</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client_phone">Phone Number *</label>
                        <input type="tel" id="client_phone" name="client_phone" required 
                               placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_name">Full Name *</label>
                        <input type="text" id="client_name" name="client_name" required 
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_address">Address *</label>
                        <textarea id="client_address" name="client_address" required 
                                  placeholder="Enter your complete address"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_license_number">Car License Number *</label>
                        <input type="text" id="car_license_number" name="car_license_number" required 
                               placeholder="Enter car license number">
                    </div>
                    
                    <div class="form-group">
                        <label for="car_engine_number">Car Engine Number *</label>
                        <input type="text" id="car_engine_number" name="car_engine_number" required 
                               placeholder="Enter car engine number">
                    </div>
                    
                    
                    
                    <button type="submit" class="btn">Book Appointment</button>
                </form>
            </div>
        </main>
        
        
    </div>
    
    <script>
        function checkAvailability() {
            const mechanicId = document.getElementById('mechanic_id').value;
            const appointmentDate = document.getElementById('appointment_date').value;
            
            if (mechanicId && appointmentDate) {
                window.location.href = `book-appointment.php?mechanic_id=${mechanicId}&appointment_date=${appointmentDate}`;
            }
        }
    </script>
</body>
</html>
