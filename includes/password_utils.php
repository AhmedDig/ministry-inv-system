<?php
// includes/password_utils.php

class PasswordUtils
{

    /**
     * Check if password is already hashed with bcrypt
     */
    public static function isPasswordHashed($hash)
    {
        if (empty($hash)) {
            return false;
        }

        // Bcrypt hashes start with $2y$ and are 60 characters
        if (substr($hash, 0, 4) === '$2y$' && strlen($hash) === 60) {
            return true;
        }

        // Also check for other bcrypt variants
        if (substr($hash, 0, 4) === '$2a$' || substr($hash, 0, 4) === '$2b$') {
            return true;
        }

        return false;
    }

    /**
     * Hash a password using bcrypt with cost factor 12
     */
    public static function hashPassword($password)
    {
        if (empty($password)) {
            throw new InvalidArgumentException("Password cannot be empty");
        }

        // Validate password strength
        /*
        if (!self::isStrongPassword($password)) {
            throw new InvalidArgumentException("Password does not meet security requirements");
        }
            */

        $options = [
            'cost' => 12, // Good balance between security and performance
        ];

        return password_hash($password, PASSWORD_DEFAULT, $options);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash)
    {
        if (empty($password) || empty($hash)) {
            return false;
        }

        // Use constant-time comparison to prevent timing attacks
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing (if algorithm/cost changes)
     */
    public static function needsRehash($hash)
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * Validate password strength
     */
    public static function isStrongPassword($password)
    {
        if (strlen($password) < 8) {
            return false;
        }

        // Check for common patterns
        $commonPasswords = ['123456', 'password', '12345678', 'qwerty', '123456789'];
        if (in_array(strtolower($password), $commonPasswords)) {
            return false;
        }

        // Check complexity (at least one uppercase, one lowercase, one number)
        /*
        if (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password)
        ) {
            return false;
        }

        */
        return true;
    }

    /**
     * Generate secure random password
     */
    public static function generateSecurePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
        $password = '';
        $charsLength = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength)];
        }

        return $password;
    }
}