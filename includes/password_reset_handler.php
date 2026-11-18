<?php
// includes/password_reset_handler.php

class PasswordResetHandler {
    
    /**
     * Check if user requires password reset
     */
    public static function requiresPasswordReset($user_id, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT requires_password_reset FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $user && $user['requires_password_reset'] == 1;
        } catch (Exception $e) {
            error_log("Error checking password reset requirement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process password reset and migrate to bcrypt
     */
    public static function processPasswordReset($user_id, $new_password, $pdo) {
        try {
            // Hash the new password with bcrypt
            $hashed_password = PasswordUtils::hashPassword($new_password);
            
            // Update user record
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, requires_password_reset = 0, password_changed_at = NOW() WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error processing password reset: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has plaintext password (for migration)
     */
    public static function hasPlaintextPassword($password_hash) {
        // If it's not a bcrypt hash (starts with $2y$), consider it plaintext
        return !preg_match('/^\$2[ayb]\$[0-9]{2}\$/', $password_hash);
    }
    
    /**
     * Force password reset for all users (for migration)
     */
    public static function forceResetForAllUsers($pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET requires_password_reset = 1");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error forcing password reset for all users: " . $e->getMessage());
            return false;
        }
    }
}
?>