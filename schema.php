<?php
/**
 * Database Schema for Car Mechanic Booking System
 * Creates tables for clients, mechanics, and appointments
 */

require_once 'db.php';

try {
    $pdo = getDBConnection();
    
    // Create clients table
    $createClientsTable = "
    CREATE TABLE IF NOT EXISTS clients (
        id SERIAL PRIMARY KEY,
        phone_number VARCHAR(15) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    // Create mechanics table with predefined 5 mechanics
    $createMechanicsTable = "
    CREATE TABLE IF NOT EXISTS mechanics (
        id SERIAL PRIMARY KEY,
        mechanic_id INTEGER UNIQUE NOT NULL CHECK (mechanic_id BETWEEN 1 AND 5),
        name VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    // Create appointments table
    $createAppointmentsTable = "
    CREATE TABLE IF NOT EXISTS appointments (
        id SERIAL PRIMARY KEY,
        client_phone VARCHAR(15) NOT NULL,
        client_name VARCHAR(100) NOT NULL,
        client_address TEXT NOT NULL,
        car_license_number VARCHAR(50) NOT NULL,
        car_engine_number VARCHAR(50) NOT NULL,
        mechanic_id INTEGER NOT NULL,
        appointment_date DATE NOT NULL,
        time_slot VARCHAR(10) NOT NULL CHECK (time_slot IN ('8-10', '11-13', '14-16', '16-18')),
        status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mechanic_id) REFERENCES mechanics(mechanic_id),
        UNIQUE(mechanic_id, appointment_date, time_slot)
        -- Removed foreign key constraint for client_phone to allow non-registered users
    );
    ";
    
    // Create mechanic_schedule table to track daily capacity
    $createMechanicScheduleTable = "
    CREATE TABLE IF NOT EXISTS mechanic_schedule (
        id SERIAL PRIMARY KEY,
        mechanic_id INTEGER NOT NULL,
        schedule_date DATE NOT NULL,
        max_appointments INTEGER DEFAULT 4,
        current_appointments INTEGER DEFAULT 0,
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mechanic_id) REFERENCES mechanics(mechanic_id),
        UNIQUE(mechanic_id, schedule_date)
    );
    ";
    
    // Execute table creation
    $pdo->exec($createClientsTable);
    $pdo->exec($createMechanicsTable);
    $pdo->exec($createAppointmentsTable);
    $pdo->exec($createMechanicScheduleTable);
    
    // Insert predefined 5 mechanics
    $insertMechanics = "
    INSERT INTO mechanics (mechanic_id, name) VALUES
    (1, 'Mech1'),
    (2, 'Mech2'),
    (3, 'Mech3'),
    (4, 'Mech4'),
    (5, 'Mech5')
    ON CONFLICT (mechanic_id) DO NOTHING;
    ";
    
    $pdo->exec($insertMechanics);
    
    // Create indexes for better performance
    $createIndexes = "
    CREATE INDEX IF NOT EXISTS idx_appointments_client_phone ON appointments(client_phone);
    CREATE INDEX IF NOT EXISTS idx_appointments_mechanic_date ON appointments(mechanic_id, appointment_date);
    CREATE INDEX IF NOT EXISTS idx_mechanic_schedule_date ON mechanic_schedule(mechanic_id, schedule_date);
    CREATE INDEX IF NOT EXISTS idx_clients_phone ON clients(phone_number);
    ";
    
    $pdo->exec($createIndexes);
    
    // Create function to check mechanic availability for specific time slot
    $createAvailabilityFunction = "
    CREATE OR REPLACE FUNCTION check_mechanic_availability(
        p_mechanic_id INTEGER,
        p_appointment_date DATE,
        p_time_slot VARCHAR(10)
    ) RETURNS BOOLEAN AS $$
    DECLARE
        slot_count INTEGER;
    BEGIN
        -- Check if the specific time slot is already booked
        SELECT COUNT(*) INTO slot_count
        FROM appointments 
        WHERE mechanic_id = p_mechanic_id 
        AND appointment_date = p_appointment_date 
        AND time_slot = p_time_slot
        AND status != 'cancelled';
        
        -- Return true if slot is available (count = 0)
        RETURN slot_count = 0;
    END;
    $$ LANGUAGE plpgsql;
    ";
    
    $pdo->exec($createAvailabilityFunction);
    
    // Create function to find next available slot for a mechanic
    $createNextAvailableFunction = "
    CREATE OR REPLACE FUNCTION find_next_available_slot(
        p_mechanic_id INTEGER,
        p_start_date DATE DEFAULT CURRENT_DATE
    ) RETURNS DATE AS $$
    DECLARE
        check_date DATE := p_start_date;
        max_days INTEGER := 30; -- Look ahead 30 days
        day_count INTEGER := 0;
    BEGIN
        WHILE day_count < max_days LOOP
            IF check_mechanic_availability(p_mechanic_id, check_date) THEN
                RETURN check_date;
            END IF;
            check_date := check_date + INTERVAL '1 day';
            day_count := day_count + 1;
        END LOOP;
        
        RETURN NULL; -- No availability found
    END;
    $$ LANGUAGE plpgsql;
    ";
    
    $pdo->exec($createNextAvailableFunction);
    
    echo "Database schema created successfully!\n";
    echo "Tables created:\n";
    echo "- clients (with phone_number as unique identifier)\n";
    echo "- mechanics (5 predefined mechanics)\n";
    echo "- appointments (with all required fields)\n";
    echo "- mechanic_schedule (for tracking daily capacity)\n";
    echo "\nFunctions created:\n";
    echo "- check_mechanic_availability()\n";
    echo "- find_next_available_slot()\n";
    
} catch (PDOException $e) {
    die("Schema creation failed: " . $e->getMessage());
}
?>
