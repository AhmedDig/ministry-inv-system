<?php
namespace Ministry\Models;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $logEntityType = 'user';
    /**
     * Find a user by username, joining entities to get full_name.
     */
    public function findByUsername($username)
    {
        $sql = "SELECT u.*, e.full_name, e.type as entity_type, e.department_id,
                       e.phone, e.email
                FROM users u
                JOIN entities e ON u.entity_id = e.entity_id
                WHERE u.username = ? AND u.is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    /**
     * Get all users with their entity data, optionally filtered.
     */
    public function getAll($filters = [])
    {
        $sql = "SELECT u.*, e.full_name, e.department_id, e.phone, e.email,
                       d.department_name_ar
                FROM users u
                JOIN entities e ON u.entity_id = e.entity_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                WHERE 1=1";

        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = ?";
            $params[] = (int) $filters['is_active'];
        }

        if (!empty($filters['clearance_level'])) {
            $sql .= " AND u.clearance_level = ?";
            $params[] = (int) $filters['clearance_level'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.full_name LIKE ? OR u.username LIKE ? OR e.email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get a user with entity data by ID.
     */
    public function getWithEntity($userId)
    {
        $sql = "SELECT u.*, e.full_name, e.department_id, e.phone, e.email,
                       d.department_name_ar
                FROM users u
                JOIN entities e ON u.entity_id = e.entity_id
                LEFT JOIN departments d ON e.department_id = d.department_id
                WHERE u.user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * Create a new user along with an entity.
     * Expects data: full_name, username, password, email, department_id, clearance_level, phone (optional)
     * Returns user_id on success, false on failure.
     */
    public function createWithEntity($data)
    {
        // Validate required fields
        $required = ['full_name', 'username', 'password', 'email', 'department_id', 'clearance_level'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        // ---- FIX: Check duplicate username in users table ----
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            return false; // duplicate username
        }

        // ---- FIX: Check duplicate email in entities table ----
        $stmt = $this->db->prepare("SELECT entity_id FROM entities WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return false; // duplicate email
        }

        // Begin transaction
        $this->db->getConnection()->beginTransaction();

        try {
            // 1. Create entity
            $entityModel = new Entity();
            $entityId = $entityModel->create([
                'type' => 'employee',
                'full_name' => $data['full_name'],
                'department_id' => $data['department_id'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'is_active' => 1
            ]);

            if (!$entityId) {
                throw new \Exception('Failed to create entity');
            }

            // 2. Create user
            $hashedPassword = $this->hashPassword($data['password']);
            $userId = $this->create([
                'entity_id' => $entityId,
                'username' => $data['username'],
                'password_hash' => $hashedPassword,
                'clearance_level' => $data['clearance_level'],
                'is_active' => $data['is_active'] ?? 0,
                'requires_password_reset' => $data['requires_password_reset'] ?? 0
            ]);

            if (!$userId) {
                throw new \Exception('Failed to create user');
            }

            $this->db->getConnection()->commit();
            return $userId;

        } catch (\Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log('User creation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user and entity together.
     */
    public function updateWithEntity($userId, $data)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }

        // Check duplicate email if email is being changed
        if (!empty($data['email'])) {
            $stmt = $this->db->prepare("SELECT entity_id FROM entities WHERE email = ? AND entity_id != ?");
            $stmt->execute([$data['email'], $user['entity_id']]);
            if ($stmt->fetch()) {
                return false; // email already used by another entity
            }
        }

        // Check duplicate username if username is being changed
        if (!empty($data['username'])) {
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$data['username'], $userId]);
            if ($stmt->fetch()) {
                return false; // username already taken
            }
        }

        $this->db->getConnection()->beginTransaction();

        try {
            // Update entity
            $entityModel = new Entity();
            $entityData = [];
            if (isset($data['full_name'])) {
                $entityData['full_name'] = $data['full_name'];
            }
            if (isset($data['department_id'])) {
                $entityData['department_id'] = $data['department_id'];
            }
            if (isset($data['phone'])) {
                $entityData['phone'] = $data['phone'];
            }
            if (isset($data['email'])) {
                $entityData['email'] = $data['email'];
            }
            if (!empty($entityData)) {
                $entityModel->update($user['entity_id'], $entityData);
            }

            // Update user
            $userData = [];
            if (isset($data['username'])) {
                $userData['username'] = $data['username'];
            }
            if (isset($data['clearance_level'])) {
                $userData['clearance_level'] = $data['clearance_level'];
            }
            if (isset($data['is_active'])) {
                $userData['is_active'] = $data['is_active'];
            }
            if (isset($data['requires_password_reset'])) {
                $userData['requires_password_reset'] = $data['requires_password_reset'];
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $userData['password_hash'] = $this->hashPassword($data['password']);
            }
            if (!empty($userData)) {
                $this->update($userId, $userData);
            }

            $this->db->getConnection()->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log('User update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Approve a user (set is_active = 1).
     */
    public function approve($userId)
    {
        $result = $this->update($userId, ['is_active' => 1]);
        if ($result) {
            $logger = \Ministry\Services\ActivityLogger::getInstance();
            $logger->log('user', $userId, 'approved', []);
        }
        return $result;
    }

    /**
     * Reject a user (soft delete – set is_active = 0).
     */
    public function reject($userId)
    {
        return $this->update($userId, ['is_active' => 0]);
    }

    /**
     * Force password reset on next login.
     */
    public function forcePasswordReset($userId)
    {
        $result = $this->update($userId, ['requires_password_reset' => 1]);
        if ($result) {
            $logger = \Ministry\Services\ActivityLogger::getInstance();
            $logger->log('user', $userId, 'forced_reset', []);
        }
        return $result;
    }

    /**
     * Hash a password.
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }

    /**
     * Verify password against stored hash.
     */
    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin($userId)
    {
        $sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Record a failed login attempt.
     */
    public function recordFailedAttempt($userId)
    {
        $sql = "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedAttempts($userId)
    {
        $sql = "UPDATE users SET failed_login_attempts = 0 WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    /**
     * Update user's profile (name, email, phone) – without changing password.
     * @param int $userId
     * @param array $data ['full_name', 'email', 'phone']
     * @return bool
     */
    public function updateProfile($userId, $data)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return false;
        }

        // Check email uniqueness (if changed)
        if (!empty($data['email'])) {
            $stmt = $this->db->prepare("SELECT entity_id FROM entities WHERE email = ? AND entity_id != ?");
            $stmt->execute([$data['email'], $user['entity_id']]);
            if ($stmt->fetch()) {
                return false; // email already used
            }
        }

        // Update entity
        $entityModel = new Entity();
        $entityData = [];
        if (isset($data['full_name'])) {
            $entityData['full_name'] = $data['full_name'];
        }
        if (isset($data['email'])) {
            $entityData['email'] = $data['email'];
        }
        if (isset($data['phone'])) {
            $entityData['phone'] = $data['phone'];
        }
        if (!empty($entityData)) {
            return $entityModel->update($user['entity_id'], $entityData);
        }
        return true; // nothing to update
    }

    /**
     * Change user password – requires current password verification.
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool|string true on success, string error message on failure
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        $user = $this->findById($userId);
        if (!$user) {
            return 'المستخدم غير موجود';
        }

        // Verify current password
        if (!$this->verifyPassword($currentPassword, $user['password_hash'])) {
            return 'كلمة المرور الحالية غير صحيحة';
        }

        // Validate new password strength
        if (strlen($newPassword) < 6) {
            return 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل';
        }

        // Update password
        $hashed = $this->hashPassword($newPassword);
        $updated = $this->update($userId, [
            'password_hash' => $hashed,
            'requires_password_reset' => 0
        ]);

        return $updated ? true : 'حدث خطأ أثناء تحديث كلمة المرور';
    }
}