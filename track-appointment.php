<?php
require_once 'db.php';

$appointments = [];
$error = '';
$phone_number = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = $_POST['phone_number'] ?? '';
    
    if (empty($phone_number)) {
        $error = "Please enter your phone number.";
    } else {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    a.id,
                    a.client_name,
                    a.client_address,
                    a.car_license_number,
                    a.car_engine_number,
                    m.name as mechanic_name,
                    a.appointment_date,
                    a.time_slot,
                    a.status,
                    a.created_at
                FROM appointments a
                JOIN mechanics m ON a.mechanic_id = m.mechanic_id
                WHERE a.client_phone = ?
                ORDER BY a.appointment_date DESC, a.created_at DESC
            ");
            
            $stmt->execute([$phone_number]);
            $appointments = $stmt->fetchAll();
            
            if (empty($appointments)) {
                $error = "No appointments found for this phone number.";
            }
            
        } catch (Exception $e) {
            $error = "Error retrieving appointments: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Appointment - Car Mechanic</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="track.css">
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
            <a href="index.php" class="back-link">← Back to Home</a>
            
            <div class="track-form">
                <h1>Track Your Appointments</h1>
                <p>Enter your phone number to view all your appointments.</p>
                
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="phone_number">Phone Number *</label>
                        <input type="tel" id="phone_number" name="phone_number" required 
                               placeholder="Enter your phone number" 
                               value="<?php echo htmlspecialchars($phone_number); ?>">
                    </div>
                    
                    <button type="submit" class="btn">Track Appointments</button>
                </form>
                
                <?php if (!empty($appointments)): ?>
                    <div class="appointments-list">
                        <h2>Your Appointments (<?php echo count($appointments); ?>)</h2>
                        
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <span class="appointment-id">Appointment #<?php echo $appointment['id']; ?></span>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="appointment-details">
                                    <div class="detail-group">
                                        <div class="detail-label">Client Name</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($appointment['client_name']); ?></div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Address</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($appointment['client_address']); ?></div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Car License</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($appointment['car_license_number']); ?></div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Car Engine</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($appointment['car_engine_number']); ?></div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Mechanic</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($appointment['mechanic_name']); ?></div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Date</div>
                                        <div class="detail-value"><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Time Slot</div>
                                        <div class="detail-value time-slot-display">
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
                                        </div>
                                    </div>
                                    
                                    <div class="detail-group">
                                        <div class="detail-label">Booked On</div>
                                        <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($appointment['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)): ?>
                    <div class="no-appointments">
                        <p>No appointments found for this phone number.</p>
                        <p><a href="book-appointment.php">Book your first appointment</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        
    </div>
</body>
</html>
