<?php
/**
 * Function to log system actions into the system_logs table.
 * Requires an active PDO connection ($dbh)
 * * @param PDO $dbh The database connection handler (from config.php).
 * @param int $user_id The ID of the user performing the action.
 * @param string $user_role The role of the user (e.g., 'Teacher', 'Student').
 * @param string $action The descriptive log message.
 */
function log_action($dbh, $user_id, $user_role, $action) {
    // Tiyakin na mayroon tayong valid ID at role para maiwasan ang error
    if (empty($user_id) || empty($user_role)) {
        return; 
    }
    
    try {
        // Tiyakin na malinis ang action message bago i-save
        $clean_action = trim($action); 
        
        $sql = "INSERT INTO system_logs (user_id, user_role, action) VALUES (:user_id, :user_role, :action)";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_role', $user_role, PDO::PARAM_STR);
        $stmt->bindParam(':action', $clean_action, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        // Option to log the error to a file if database logging fails
        // error_log("Log Action Failed: " . $e->getMessage());
        // We suppress the error so the main application continues to run
    }
}
?>