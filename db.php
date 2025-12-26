<?php
// This file handles the entire database setup. It connects to the database,
// creates the necessary tables if they don't exist, repairs common schema issues,
// and seeds the database with initial data if it's empty.

// --- DATABASE CONNECTION SETTINGS ---
$servername = "localhost"; // The address of the database server (usually localhost)
$username = "root";        // The database username (default for XAMPP is "root")
$password = "";            // The database password (default for XAMPP is empty)
$dbname = "attendance_system"; // The name of the database for this application

// --- ESTABLISH DATABASE CONNECTION ---
// Creates a new MySQLi object, which represents the connection to the database.
$conn = new mysqli($servername, $username, $password);

// --- VERIFY CONNECTION ---
// Checks if the connection attempt resulted in an error.
if ($conn->connect_error) {
  // If there was an error, stop the script immediately and show the error message.
  die("Connection failed: " . $conn->connect_error);
}

// --- SELECT OR CREATE DATABASE ---
// This command creates the database if it doesn't already exist. This makes setup easier.
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
// Selects the database to use for all subsequent queries.
$conn->select_db($dbname);

// --- TABLE CREATION QUERIES ---
// These queries create the tables needed for the application.
// "IF NOT EXISTS" ensures that the script doesn't fail if the tables are already there.

// Users table: Stores login information for both admins and students.
$conn->query("CREATE TABLE IF NOT EXISTS users (
  user_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Unique ID for each user
  username VARCHAR(50) NOT NULL UNIQUE, -- User's login name (must be unique)
  password VARCHAR(255) NOT NULL, -- Hashed password for security
  role ENUM('admin', 'student') NOT NULL, -- Defines if the user is an admin or a student
  student_id INT(6) UNSIGNED, -- Links to the students table if the user is a student
  reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- The date the user was registered
)");

// Courses table: Stores the names of the courses offered.
$conn->query("CREATE TABLE IF NOT EXISTS courses (
  course_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Unique ID for each course
  course_name VARCHAR(100) NOT NULL UNIQUE -- The name of the course (must be unique)
)");

// Batches table: Stores the names of student batches (e.g., "2024 Batch").
$conn->query("CREATE TABLE IF NOT EXISTS batches (
  batch_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Unique ID for each batch
  batch_name VARCHAR(50) NOT NULL UNIQUE -- The name of the batch (must be unique)
)");

// Batch-Courses linking table: A many-to-many relationship table.
// It connects batches to the courses they are assigned.
$conn->query("CREATE TABLE IF NOT EXISTS batch_courses (
  batch_course_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT(6) UNSIGNED NOT NULL, -- Foreign key linking to the 'batches' table
  course_id INT(6) UNSIGNED NOT NULL, -- Foreign key linking to the 'courses' table
  UNIQUE KEY (batch_id, course_id), -- Ensures a batch can't be linked to the same course twice
  FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE, -- If a batch is deleted, these links are also deleted
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE -- If a course is deleted, these links are also deleted
)");

// Students table: Stores information about each student.
$conn->query("CREATE TABLE IF NOT EXISTS students (
  student_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Unique ID for each student
  name VARCHAR(100) NOT NULL, -- Student's full name
  email VARCHAR(100) UNIQUE, -- Student's email (must be unique)
  batch_id INT(6) UNSIGNED, -- Links the student to a specific batch
  reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- The date the student was registered
  FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE -- If a batch is deleted, all students in it are also deleted
)");

// Attendance table: Stores each attendance record.
$conn->query("CREATE TABLE IF NOT EXISTS attendance (
  id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Unique ID for each attendance record
  student_id INT(6) UNSIGNED NOT NULL, -- Links to the student
  course_id INT(6) UNSIGNED NOT NULL, -- Links to the course
  date DATE NOT NULL, -- The date of the attendance
  status ENUM('Present', 'Absent') NOT NULL, -- The attendance status
  time_marked DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- When the record was created
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE, -- If a student is deleted, their attendance records are also deleted
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE, -- If a course is deleted, its attendance records are also deleted
  UNIQUE KEY unique_attendance (student_id, course_id, date) -- Prevents duplicate attendance records for the same student, course, and day
)");

// --- DATABASE SCHEMA MIGRATION AND REPAIR ---
// This section fixes common problems with older or inconsistent database schemas.

// Temporarily disable foreign key checks. This allows making changes to table structures
// without causing errors related to key constraints.
$conn->query("SET FOREIGN_KEY_CHECKS=0;");

// --- PATCH FOR OLD 'attendance' TABLE ---
// This block checks if the 'attendance' table is from an old version that is missing the 'course_id' column.
$check_att_col = $conn->query("SHOW COLUMNS FROM `attendance` LIKE 'course_id'");
if ($check_att_col->num_rows == 0) { // If the column doesn't exist...
    // Add the 'course_id' column to the table.
    $conn->query("ALTER TABLE attendance ADD COLUMN course_id INT(6) UNSIGNED NOT NULL AFTER student_id");
    // Delete any old records that would now be invalid.
    $conn->query("DELETE FROM attendance WHERE course_id = 0"); 
}

// --- DATA INTEGRITY CLEANUP ---
// These queries clean up "orphaned" records. For example, an attendance record
// for a student who no longer exists in the 'students' table.
$conn->query("DELETE a FROM attendance a LEFT JOIN students s ON a.student_id = s.student_id WHERE s.student_id IS NULL");
$conn->query("DELETE a FROM attendance a LEFT JOIN courses c ON a.course_id = c.course_id WHERE c.course_id IS NULL");

// --- FIX FAULTY FOREIGN KEYS ---
// This section robustly fixes issues where the foreign keys don't have 'ON DELETE CASCADE'.
// It specifically looks for auto-named keys (like 'attendance_ibfk_...') and replaces them.
$fk_exists_query = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$dbname}' AND TABLE_NAME = 'attendance' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE 'attendance_ibfk_%'";
$fk_result = $conn->query($fk_exists_query);
if ($fk_result) {
    while ($row = $fk_result->fetch_assoc()) {
        $fk_name = $row['CONSTRAINT_NAME'];
        // Check if this foreign key is for the 'student_id' column.
        $col_check = $conn->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE CONSTRAINT_NAME = '{$fk_name}' AND TABLE_SCHEMA = '{$dbname}' AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'student_id'");
        if ($col_check && $col_check->num_rows > 0) {
            // If it is, drop the old, incorrect foreign key.
            $conn->query("ALTER TABLE attendance DROP FOREIGN KEY `{$fk_name}`");
        }
    }
}

// After dropping any old keys, add the correct foreign key with 'ON DELETE CASCADE'.
$conn->query("ALTER TABLE attendance ADD FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE");

// Do the same for the 'course_id' foreign key to ensure it's correct.
$fk_check_query_course = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbname}' AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'course_id' AND REFERENCED_TABLE_NAME = 'courses'";
$fk_result_course = $conn->query($fk_check_query_course);
if ($fk_result_course->num_rows > 0) {
    $fk_name = $fk_result_course->fetch_assoc()['CONSTRAINT_NAME'];
    if ($fk_name != 'PRIMARY') { // Don't drop primary keys
        $conn->query("ALTER TABLE attendance DROP FOREIGN KEY `{$fk_name}`");
    }
}
$conn->query("ALTER TABLE attendance ADD FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE");

// --- RECREATE UNIQUE KEY ---
// This ensures that the unique key for attendance records is correctly defined.
// First, check if an old index exists.
$index_check_query = "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = '{$dbname}' AND table_name = 'attendance' AND index_name = 'unique_attendance'";
$index_result = $conn->query($index_check_query);
$index_exists = $index_result->fetch_assoc()['COUNT(1)'] > 0;

if ($index_exists) {
    // If it exists, drop it before creating the new one.
    $conn->query("ALTER TABLE attendance DROP INDEX unique_attendance");
}
// Add the correct unique key.
$conn->query("ALTER TABLE attendance ADD UNIQUE KEY unique_attendance (student_id, course_id, date)");

// Re-enable foreign key checks now that all schema modifications are complete.
$conn->query("SET FOREIGN_KEY_CHECKS=1;");


// --- SEED DATABASE WITH SAMPLE DATA (ONLY IF EMPTY) ---
// This section fills the database with some sample data the first time the application is run.

// 1. Add a Default Admin User if no admin exists.
$admin_check = $conn->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1");
if ($admin_check->num_rows == 0) {
    $admin_user = 'admin';
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT); // Hash the password for security
    $conn->query("INSERT INTO users (username, password, role) VALUES ('$admin_user', '$admin_pass', 'admin')");
}

// 2. Add sample data only if there are no students in the database.
$student_check = $conn->query("SELECT 1 FROM students LIMIT 1");
if ($student_check->num_rows == 0) {
    // A transaction ensures that all these queries succeed or none of them do. This prevents partial data insertion.
    $conn->begin_transaction();
    try {
        // Seed Courses
        $courses = ['Web Development', 'Database Management', 'Software Engineering', 'Data Structures', 'Computer Networks', 'Operating Systems'];
        $course_ids = [];
        $stmt_course = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
        foreach ($courses as $course) {
            $stmt_course->bind_param("s", $course);
            $stmt_course->execute();
            $course_ids[] = $stmt_course->insert_id;
        }
        $stmt_course->close();

        // Seed a Batch
        $batch_name = '2024 Batch';
        $stmt_batch = $conn->prepare("INSERT INTO batches (batch_name) VALUES (?)");
        $stmt_batch->bind_param("s", $batch_name);
        $stmt_batch->execute();
        $batch_id = $stmt_batch->insert_id;
        $stmt_batch->close();

        // Link the courses to the batch
        $stmt_batch_course = $conn->prepare("INSERT INTO batch_courses (batch_id, course_id) VALUES (?, ?)");
        foreach ($course_ids as $course_id) {
            $stmt_batch_course->bind_param("ii", $batch_id, $course_id);
            $stmt_batch_course->execute();
        }
        $stmt_batch_course->close();

        // Seed Students and create their user accounts
        $students = [
            ['name' => 'John Doe', 'email' => 'john.doe@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@example.com'],
            ['name' => 'Peter Jones', 'email' => 'peter.jones@example.com']
        ];
        $student_ids = [];
        $stmt_student = $conn->prepare("INSERT INTO students (name, email, batch_id) VALUES (?, ?, ?)");
        $stmt_user = $conn->prepare("INSERT INTO users (username, password, role, student_id) VALUES (?, ?, 'student', ?)");
        $student_pass = password_hash('student123', PASSWORD_DEFAULT);

        foreach ($students as $student) {
            // Insert into students table
            $stmt_student->bind_param("ssi", $student['name'], $student['email'], $batch_id);
            $stmt_student->execute();
            $new_student_id = $stmt_student->insert_id;
            $student_ids[] = $new_student_id;

            // Create a corresponding user account for the student
            $stmt_user->bind_param("ssi", $student['email'], $student_pass, $new_student_id);
            $stmt_user->execute();
        }
        $stmt_student->close();
        $stmt_user->close();

        // Seed some sample attendance data for the first course
        $first_course_id = $course_ids[0];
        $stmt_att = $conn->prepare("INSERT INTO attendance (student_id, course_id, date, status) VALUES (?, ?, ?, ?)");
        $date1 = date('Y-m-d', strtotime('-2 days'));
        $date2 = date('Y-m-d', strtotime('-1 days'));
        
        // Day 1 Attendance
        $status = 'Present';
        $stmt_att->bind_param("iiss", $student_ids[0], $first_course_id, $date1, $status);
        $stmt_att->execute();
        $stmt_att->bind_param("iiss", $student_ids[1], $first_course_id, $date1, $status);
        $stmt_att->execute();
        $status = 'Absent';
        $stmt_att->bind_param("iiss", $student_ids[2], $first_course_id, $date1, $status);
        $stmt_att->execute();
        
        // Day 2 Attendance
        $status = 'Present';
        $stmt_att->bind_param("iiss", $student_ids[0], $first_course_id, $date2, $status);
        $stmt_att->execute();
        $status = 'Absent';
        $stmt_att->bind_param("iiss", $student_ids[1], $first_course_id, $date2, $status);
        $stmt_att->execute();
        $status = 'Present';
        $stmt_att->bind_param("iiss", $student_ids[2], $first_course_id, $date2, $status);
        $stmt_att->execute();
        
        $stmt_att->close();

        // If all queries were successful, commit the transaction to save the changes.
        $conn->commit();
    } catch (Exception $e) {
        // If any query failed, roll back the transaction to undo all changes.
        $conn->rollback();
        // Optional: log the error message, e.g., error_log($e->getMessage());
    }
}
?>
