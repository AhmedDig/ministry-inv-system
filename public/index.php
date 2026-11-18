<?php
// public/index.php – Main Router

// Enable error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load configuration (starts session, sets up autoloader and database)
require_once __DIR__ . '/../includes/config.php';

// ------------------------------------------------------------------
// Import model classes
// ------------------------------------------------------------------
use Ministry\Models\Ticket;
use Ministry\Models\TicketAssignment;
use Ministry\Models\User;
use Ministry\Models\Entity;
use Ministry\Models\Asset;
use Ministry\Models\Consumable;
use Ministry\Models\ActivityLog;

// ------------------------------------------------------------------
// Helper functions
// ------------------------------------------------------------------

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_data']);
}

function redirectTo(string $action): void
{
    header('Location: index.php?action=' . urlencode($action));
    exit;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirectTo('login');
    }
}

// ------------------------------------------------------------------
// Get the requested action
// ------------------------------------------------------------------
$action = $_GET['action'] ?? 'dashboard';

// Handle logout immediately (no login required)
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirectTo('login');
}

// ------------------------------------------------------------------
// API ROUTES (ALL API ENDPOINTS GO HERE, BEFORE PAGE ROUTES)
// ------------------------------------------------------------------

// ---- Helper for JSON responses ----
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if the action starts with 'api_'
if (strpos($action, 'api_') === 0) {

    // Public API endpoints (no auth required)
    $publicApiActions = ['api_public_create_ticket'];

    if (!in_array($action, $publicApiActions) && !isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'غير مصرح']);
    }

    // ------------------------------------------------------------
    // USER MANAGEMENT
    // ------------------------------------------------------------
    if ($action === 'api_get_users') {
        if ($_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $filter = $_GET['filter'] ?? 'all';
        $userModel = new User();
        $filters = [];
        if ($filter === 'pending')
            $filters['is_active'] = 0;
        elseif ($filter === 'active')
            $filters['is_active'] = 1;
        $users = $userModel->getAll($filters);
        jsonResponse(['success' => true, 'users' => $users]);
    }
    // ------------------------------------------------------------
    // PUBLIC TICKET SUBMISSION (no auth)
    // ------------------------------------------------------------
    if ($action === 'api_public_create_ticket') {
        try {
            $name = trim($_POST['reporter_name'] ?? '');
            $phoneRaw = trim($_POST['reporter_phone'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assetId = $_POST['asset_id'] ?? null;
            $deptId = $_POST['department_id'] ?? null;

            // ---- Required field validation ----
            if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                jsonResponse(['success' => false, 'message' => 'الاسم مطلوب (2 إلى 100 حرف).']);
            }
            if ($title === '' || mb_strlen($title) < 3) {
                jsonResponse(['success' => false, 'message' => 'العنوان مطلوب (3 أحرف على الأقل).']);
            }
            if (mb_strlen($description) < 10) {
                jsonResponse(['success' => false, 'message' => 'الوصف مطلوب (10 أحرف على الأقل).']);
            }

            // ---- Phone validation + normalization ----
            $phone = \Ministry\Utils\PhoneNormalizer::normalize($phoneRaw);
            if ($phone === null) {
                jsonResponse(['success' => false, 'message' => 'رقم الهاتف غير صحيح. أدخل رقماً فلسطينياً صحيحاً.']);
            }

            // ---- Rate limit ----
            $limiter = new \Ministry\Utils\TicketRateLimiter($pdo);
            $check = $limiter->check($phone);
            if (!$check['allowed']) {
                jsonResponse(['success' => false, 'message' => $check['reason']]);
            }

            // ---- Optional asset validation ----
            if ($assetId !== null && $assetId !== '') {
                $assetId = (int) $assetId;
                $stmt = $pdo->prepare("SELECT asset_id FROM assets WHERE asset_id = ? AND is_active = 1");
                $stmt->execute([$assetId]);
                if (!$stmt->fetch())
                    $assetId = null;
            } else {
                $assetId = null;
            }

            // ---- Optional department validation ----
            if ($deptId !== null && $deptId !== '') {
                $deptId = (int) $deptId;
                $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_id = ? AND is_active = 1");
                $stmt->execute([$deptId]);
                if (!$stmt->fetch())
                    $deptId = null;
            } else {
                $deptId = null;
            }

            // ---- Files ----
            $files = \Ministry\Utils\FileUploader::normalizeFilesArray($_FILES['attachments'] ?? null);
            $fileErrors = \Ministry\Utils\FileUploader::validate($files);
            if (!empty($fileErrors)) {
                jsonResponse(['success' => false, 'message' => implode(' ', $fileErrors)]);
            }

            // ---- Create ticket ----
            $ticketModel = new Ticket();
            $ticketId = $ticketModel->createTicket([
                'title' => $title,
                'description' => $description,
                'asset_id' => $assetId,
                'department_id' => $deptId,
                'priority' => 'medium',
                'status' => 'open',
                'ticket_type' => 'internal',
                'created_by' => null,
                'reporter_name' => $name,
                'reporter_phone' => $phone,
            ]);

            if (!$ticketId) {
                jsonResponse(['success' => false, 'message' => 'تعذر إنشاء التذكرة. يرجى المحاولة مرة أخرى.']);
            }

            // ---- Move files (if any) ----
            if (!empty($files)) {
                try {
                    $records = \Ministry\Utils\FileUploader::moveForTicket($ticketId, $files);
                    foreach ($records as $r) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO ticket_attachments
                         (ticket_id, file_path, original_name, mime_type, file_size)
                         VALUES (?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $ticketId,
                            $r['file_path'],
                            $r['original_name'],
                            $r['mime_type'],
                            $r['file_size'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Files failed → remove the ticket to avoid orphans
                    error_log('Ticket attachment failure: ' . $e->getMessage());
                    $pdo->prepare("DELETE FROM tickets WHERE ticket_id = ?")->execute([$ticketId]);
                    jsonResponse(['success' => false, 'message' => 'تعذر حفظ الصور. يرجى إعادة المحاولة بدون صور أو بصور أصغر.']);
                }
            }

            jsonResponse([
                'success' => true,
                'ticket_id' => $ticketId,
                'ticket_number' => '#' . $ticketId,
                'message' => 'تم تقديم طلبك بنجاح. سيتم التواصل معك على الرقم المسجل.',
            ]);

        } catch (\Throwable $e) {
            error_log('Public ticket creation error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً.']);
        }
        exit;
    }

    if ($action === 'api_approve_user') {
        if ($_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId)
            jsonResponse(['success' => false]);
        $userModel = new User();
        $result = $userModel->approve($userId);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_delete_user') {
        if ($_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId)
            jsonResponse(['success' => false]);
        $userModel = new User();
        $result = $userModel->delete($userId);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_force_reset') {
        if ($_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId)
            jsonResponse(['success' => false]);
        $userModel = new User();
        $result = $userModel->forcePasswordReset($userId);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_update_profile') {
        $userId = (int) $_SESSION['user_id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (empty($full_name) || empty($email)) {
            jsonResponse(['success' => false, 'message' => 'الاسم والبريد الإلكتروني مطلوبان']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
        }
        $userModel = new User();
        $result = $userModel->updateProfile($userId, ['full_name' => $full_name, 'email' => $email, 'phone' => $phone]);
        if ($result === true) {
            $updatedUser = $userModel->getWithEntity($userId);
            $_SESSION['user_data'] = $updatedUser;
            $_SESSION['user_display_name'] = $updatedUser['full_name'] ?? $updatedUser['username'] ?? 'مستخدم';
            jsonResponse(['success' => true, 'message' => 'تم التحديث']);
        } else {
            jsonResponse(['success' => false, 'message' => $result === false ? 'البريد الإلكتروني مستخدم' : 'حدث خطأ']);
        }
    }

    if ($action === 'api_change_password') {
        $userId = (int) $_SESSION['user_id'];
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (empty($current) || empty($new) || empty($confirm)) {
            jsonResponse(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
        }
        if ($new !== $confirm) {
            jsonResponse(['success' => false, 'message' => 'كلمتا المرور غير متطابقتين']);
        }
        $userModel = new User();
        $result = $userModel->changePassword($userId, $current, $new);
        if ($result === true) {
            jsonResponse(['success' => true, 'message' => 'تم تغيير كلمة المرور']);
        } else {
            jsonResponse(['success' => false, 'message' => $result]);
        }
    }

    // ------------------------------------------------------------
    // ENTITY MANAGEMENT
    // ------------------------------------------------------------
    if ($action === 'api_get_entities') {
        $filter = $_GET['filter'] ?? 'all';
        $entityModel = new Entity();
        $filters = [];
        if ($filter !== 'all')
            $filters['type'] = $filter;
        $entities = $entityModel->getWithUser($filters);
        jsonResponse(['success' => true, 'entities' => $entities]);
    }

    if ($action === 'api_delete_entity') {
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        if (!$entityId)
            jsonResponse(['success' => false]);
        $entityModel = new Entity();
        $result = $entityModel->delete($entityId);
        jsonResponse(['success' => (bool) $result]);
    }

    // ------------------------------------------------------------
    // ASSET MANAGEMENT
    // ------------------------------------------------------------
    if ($action === 'api_get_assets') {
        $filter = $_GET['filter'] ?? 'all';
        $assetModel = new Asset();
        if ($filter === 'all') {
            $assets = $assetModel->getWithDetails();
        } else {
            $assets = $assetModel->getByStatus($filter);
        }
        jsonResponse(['success' => true, 'assets' => $assets]);
    }

    if ($action === 'api_get_related_assets') {
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $entityId = (int) ($_GET['entity_id'] ?? 0);
        $excludeAssetId = (int) ($_GET['exclude_asset_id'] ?? 0);

        if (!$entityId) {
            jsonResponse(['success' => false, 'message' => 'معرف الكيان مطلوب']);
        }

        $assetModel = new Asset();
        $related = $assetModel->getRelated($entityId, $excludeAssetId);

        $entityModel = new Entity();
        $entityInfo = $entityModel->findById($entityId);

        if (!$entityInfo) {
            jsonResponse(['success' => false, 'message' => 'الكيان غير موجود']);
        }

        $total = count($related) + ($excludeAssetId > 0 ? 1 : 0);

        jsonResponse([
            'success' => true,
            'assets' => $related,
            'entity_name' => $entityInfo['full_name'] ?? 'غير معروف',
            'entity_type' => $entityInfo['type'] ?? 'employee',
            'total_assigned' => $total,
        ]);
    }

    // ------------------------------------------------------------
    // ADVANCED FILTER — structured conditions → SQL
    // ------------------------------------------------------------
    if ($action === 'api_filter_assets') {
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }

        $filter = $_GET['filter'] ?? 'all';
        $conditionsJson = $_GET['conditions'] ?? '[]';
        $logic = strtoupper($_GET['logic'] ?? 'AND');
        if (!in_array($logic, ['AND', 'OR']))
            $logic = 'AND';

        $conditions = json_decode($conditionsJson, true);
        if (!is_array($conditions))
            $conditions = [];

        $where = ['a.is_active = 1'];
        $params = [];

        // Quick filter (top tabs)
        if ($filter !== 'all') {
            $where[] = 'a.status = ?';
            $params[] = $filter;
        }

        // Advanced conditions
        $advancedClauses = [];
        $advancedParams = [];

        foreach ($conditions as $cond) {
            $type = $cond['type'] ?? '';
            $key = $cond['key'] ?? '';
            $operator = $cond['operator'] ?? '=';
            $value = $cond['value'] ?? '';
            if ($key === '')
                continue;

            if ($type === 'asset') {
                $allowedFields = ['asset_type', 'status', 'assigned_to', 'asset_tag', 'serial_number', 'manufacturer', 'model'];
                if (!in_array($key, $allowedFields))
                    continue;
                $col = "a.{$key}";

                if ($key === 'assigned_to') {
                    if ($value === '') {
                        $advancedClauses[] = "{$col} IS NULL";
                    } elseif ($value === '__not_null__') {
                        $advancedClauses[] = "{$col} IS NOT NULL";
                    } else {
                        $advancedClauses[] = "{$col} = ?";
                        $advancedParams[] = (int) $value;
                    }
                } else {
                    // Text or enum column
                    switch ($operator) {
                        case '=':
                            $advancedClauses[] = "{$col} = ?";
                            $advancedParams[] = $value;
                            break;
                        case '!=':
                            $advancedClauses[] = "{$col} != ?";
                            $advancedParams[] = $value;
                            break;
                        case 'contains':
                            $advancedClauses[] = "{$col} LIKE ?";
                            $advancedParams[] = '%' . $value . '%';
                            break;
                        case 'starts_with':
                            $advancedClauses[] = "{$col} LIKE ?";
                            $advancedParams[] = $value . '%';
                            break;
                        case 'is_not_null':
                            $advancedClauses[] = "{$col} IS NOT NULL";
                            break;
                    }
                }
            }
        }

        if (!empty($advancedClauses)) {
            $where[] = '(' . implode(" {$logic} ", $advancedClauses) . ')';
            $params = array_merge($params, $advancedParams);
        }

        $sql = "SELECT a.*,
                s.name AS supplier_name,
                l.name AS location_name,
                e.full_name AS assigned_to_name,
                e.type AS assigned_to_type
            FROM assets a
            LEFT JOIN suppliers s ON a.supplier_id = s.supplier_id
            LEFT JOIN locations l ON a.location_id = l.location_id
            LEFT JOIN entities e ON a.assigned_to = e.entity_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.created_at DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $assets = $stmt->fetchAll();
            jsonResponse(['success' => true, 'assets' => $assets, 'count' => count($assets)]);
        } catch (\Throwable $e) {
            error_log('Filter error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'خطأ في التصفية']);
        }
    }

    // ============================================================
// CSV IMPORT — reference data (once per page load)
// ============================================================
    if ($action === 'api_import_get_reference') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 4) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        try {
            $defs = (new \Ministry\Models\AttributeDefinition())->getAll();
            $defs = array_map(function ($d) {
                return [
                    'attr_key' => $d['attr_key'],
                    'display_label_ar' => $d['display_label_ar'],
                    'value_type' => $d['value_type'],
                    'unit' => $d['unit'],
                    'allowed_values' => $d['allowed_values_decoded'],
                    'applies_to_types' => $d['applies_to_types_decoded'],
                    'visible_when' => $d['visible_when_decoded'],
                ];
            }, $defs);

            $entities = $pdo->query("SELECT entity_id AS id, full_name AS name FROM entities WHERE is_active = 1")->fetchAll();
            $locations = $pdo->query("SELECT location_id AS id, name FROM locations WHERE is_active = 1")->fetchAll();
            $suppliers = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_active = 1")->fetchAll();

            jsonResponse([
                'success' => true,
                'attribute_defs' => $defs,
                'entities' => $entities,
                'locations' => $locations,
                'suppliers' => $suppliers,
            ]);
        } catch (\Throwable $e) {
            error_log('Reference data error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'خطأ في تحميل البيانات المرجعية']);
        }
    }

    // ============================================================
// CSV IMPORT — check which serials already exist
// ============================================================
    if ($action === 'api_import_check_serials') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 4) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $serials = json_decode($_POST['serials'] ?? '[]', true);
        if (!is_array($serials) || empty($serials)) {
            jsonResponse(['success' => true, 'existing' => []]);
        }
        $serials = array_values(array_filter(array_map('trim', $serials)));
        if (empty($serials)) {
            jsonResponse(['success' => true, 'existing' => []]);
        }
        try {
            $placeholders = implode(',', array_fill(0, count($serials), '?'));
            $stmt = $pdo->prepare("SELECT serial_number FROM assets WHERE serial_number IN ($placeholders) AND is_active = 1");
            $stmt->execute($serials);
            $existing = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            jsonResponse(['success' => true, 'existing' => $existing]);
        } catch (\Throwable $e) {
            error_log('Serial check error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'خطأ في التحقق من الأرقام التسلسلية']);
        }
    }

    // ============================================================
// CSV IMPORT — bulk insert (validated data only)
// ============================================================
    if ($action === 'api_import_check_duplicates') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 4) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];
        $serials = json_decode($_POST['serials'] ?? '[]', true) ?: [];

        $tags = array_values(array_filter(array_map('trim', $tags)));
        $serials = array_values(array_filter(array_map('trim', $serials)));

        $existingTags = [];
        $existingSerials = [];

        try {
            if (!empty($tags)) {
                $ph = implode(',', array_fill(0, count($tags), '?'));
                $stmt = $pdo->prepare("SELECT asset_tag FROM assets WHERE asset_tag IN ($ph) AND is_active = 1");
                $stmt->execute($tags);
                $existingTags = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }
            if (!empty($serials)) {
                $ph = implode(',', array_fill(0, count($serials), '?'));
                $stmt = $pdo->prepare("SELECT serial_number FROM assets WHERE serial_number IN ($ph) AND is_active = 1");
                $stmt->execute($serials);
                $existingSerials = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }
            jsonResponse(['success' => true, 'tags' => $existingTags, 'serials' => $existingSerials]);
        } catch (\Throwable $e) {
            error_log('Duplicate check error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'خطأ']);
        }
    }
    if ($action === 'api_import_bulk_insert') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 4) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        if (!is_array($rows) || empty($rows)) {
            jsonResponse(['success' => false, 'message' => 'لا توجد صفوف للاستيراد']);
        }

        $report = ['total' => count($rows), 'imported' => 0, 'skipped' => 0, 'errors' => []];

        $attrWriter = new \Ministry\Services\AssetAttributeWriter($pdo);
        $assetModel = new \Ministry\Models\Asset();

        $entityMap = [];
        $locMap = [];
        $supplierMap = [];
        $deptMap = [];

        $pdo->beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 1;
                try {
                    $assetData = $row['asset'] ?? [];
                    $attrs = $row['attributes'] ?? [];

                    // ---- Required validation ----
                    if (empty($assetData['asset_tag']))
                        throw new \RuntimeException('رقم الأصل مطلوب');
                    if (empty($assetData['asset_type']))
                        throw new \RuntimeException('نوع الأصل مطلوب');

                    // ---- Duplicate asset_tag ----
                    $stmt = $pdo->prepare("SELECT asset_id FROM assets WHERE asset_tag = ? AND is_active = 1");
                    $stmt->execute([$assetData['asset_tag']]);
                    if ($stmt->fetch())
                        throw new \RuntimeException('رقم الأصل مكرر: ' . $assetData['asset_tag']);

                    // ---- Serial (optional, unique when present) ----
                    $serial = !empty($assetData['serial_number']) ? trim($assetData['serial_number']) : null;
                    if ($serial !== null) {
                        $stmt = $pdo->prepare("SELECT asset_id FROM assets WHERE serial_number = ? AND is_active = 1");
                        $stmt->execute([$serial]);
                        if ($stmt->fetch())
                            throw new \RuntimeException('الرقم التسلسلي مكرر: ' . $serial);
                    }

                    // ---- Resolve/Create Department ----
                    $departmentId = null;
                    if (!empty($assetData['department_name'])) {
                        $dName = trim($assetData['department_name']);
                        $dKey = mb_strtolower($dName);
                        if (isset($deptMap[$dKey])) {
                            $departmentId = $deptMap[$dKey];
                        } else {
                            $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE LOWER(department_name_ar) = ? LIMIT 1");
                            $stmt->execute([$dKey]);
                            $id = $stmt->fetchColumn();
                            if (!$id) {
                                $stmt = $pdo->prepare("INSERT INTO departments (department_name_ar, is_active) VALUES (?, 1)");
                                $stmt->execute([$dName]);
                                $id = $pdo->lastInsertId();
                            }
                            $deptMap[$dKey] = (int) $id;
                            $departmentId = (int) $id;
                        }
                    }

                    // ---- Resolve/Create Entity ----
                    $assignedTo = null;
                    if (!empty($assetData['assigned_to_name'])) {
                        $name = trim($assetData['assigned_to_name']);
                        if (isset($entityMap[$name])) {
                            $assignedTo = $entityMap[$name];
                        } else {
                            $stmt = $pdo->prepare("SELECT entity_id FROM entities WHERE full_name = ? LIMIT 1");
                            $stmt->execute([$name]);
                            $id = $stmt->fetchColumn();
                            if (!$id) {
                                $stmt = $pdo->prepare("INSERT INTO entities (type, full_name, department_id, is_active) VALUES ('employee', ?, ?, 1)");
                                $stmt->execute([$name, $departmentId]);
                                $id = $pdo->lastInsertId();
                            }
                            $entityMap[$name] = (int) $id;
                            $assignedTo = (int) $id;
                        }
                    }

                    // ---- Resolve/Create Location ----
                    $locationId = null;
                    if (!empty($assetData['location_name'])) {
                        $name = trim($assetData['location_name']);
                        if (isset($locMap[$name])) {
                            $locationId = $locMap[$name];
                        } else {
                            $stmt = $pdo->prepare("SELECT location_id FROM locations WHERE name = ? LIMIT 1");
                            $stmt->execute([$name]);
                            $id = $stmt->fetchColumn();
                            if (!$id) {
                                $code = 'IMP-' . substr(md5($name), 0, 8);
                                $stmt = $pdo->prepare("INSERT INTO locations (code, name, is_active) VALUES (?, ?, 1)");
                                $stmt->execute([$code, $name]);
                                $id = $pdo->lastInsertId();
                            }
                            $locMap[$name] = (int) $id;
                            $locationId = (int) $id;
                        }
                    }

                    // ---- Resolve/Create Supplier ----
                    $supplierId = null;
                    if (!empty($assetData['supplier_name'])) {
                        $name = trim($assetData['supplier_name']);
                        if (isset($supplierMap[$name])) {
                            $supplierId = $supplierMap[$name];
                        } else {
                            $stmt = $pdo->prepare("SELECT supplier_id FROM suppliers WHERE name = ? LIMIT 1");
                            $stmt->execute([$name]);
                            $id = $stmt->fetchColumn();
                            if (!$id) {
                                $stmt = $pdo->prepare("INSERT INTO suppliers (name, type, is_active) VALUES (?, 'parts', 1)");
                                $stmt->execute([$name]);
                                $id = $pdo->lastInsertId();
                            }
                            $supplierMap[$name] = (int) $id;
                            $supplierId = (int) $id;
                        }
                    }

                    // ---- Status logic ----
                    $status = $assetData['status'] ?? 'available';
                    if ($assignedTo && $status === 'available')
                        $status = 'assigned';
                    if (!$assignedTo && $status === 'assigned')
                        $status = 'available';

                    // ---- Insert ----
                    $insert = [
                        'asset_tag' => $assetData['asset_tag'],
                        'serial_number' => $serial,
                        'asset_type' => $assetData['asset_type'],
                        'manufacturer' => !empty($assetData['manufacturer']) ? trim($assetData['manufacturer']) : null,
                        'model' => !empty($assetData['model']) ? trim($assetData['model']) : null,
                        'status' => $status,
                        'assigned_to' => $assignedTo,
                        'department_id' => $departmentId,
                        'location_id' => $locationId,
                        'supplier_id' => $supplierId,
                        'warranty_provider_id' => null,
                        'purchase_date' => $assetData['purchase_date'] ?? null,
                        'warranty_expiry' => $assetData['warranty_expiry'] ?? null,
                        'purchase_cost' => $assetData['cost'] ?? null,
                        'notes' => $assetData['notes'] ?? null,
                    ];

                    $assetId = $assetModel->create($insert);
                    if (!$assetId)
                        throw new \RuntimeException('فشل الإدخال');

                    // ---- Attributes ----
                    if (!empty($attrs)) {
                        $normalized = [];
                        foreach ($attrs as $k => $v) {
                            $normalized[$k] = is_array($v)
                                ? $v
                                : ['value' => (string) $v, 'num' => is_numeric($v) ? (float) $v : null];
                        }
                        $attrWriter->save((int) $assetId, $normalized);
                    }

                    $report['imported']++;
                } catch (\Throwable $e) {
                    $report['skipped']++;
                    $report['errors'][] = ['row' => $rowNum, 'message' => $e->getMessage()];
                    if ($report['skipped'] > 100) {
                        throw new \RuntimeException('عدد كبير من الأخطاء، تم إيقاف العملية');
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            error_log('Bulk insert error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => $e->getMessage(), 'report' => $report]);
        }

        jsonResponse(['success' => true, 'report' => $report]);
    }

    if ($action === 'api_delete_asset') {
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        if (!$assetId)
            jsonResponse(['success' => false]);
        $assetModel = new Asset();
        $result = $assetModel->delete($assetId);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_assign_asset') {
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        if (!$assetId || !$entityId) {
            jsonResponse(['success' => false, 'message' => 'معرف الأصل والكيان مطلوبان']);
        }
        $assetModel = new Asset();
        $result = $assetModel->update($assetId, ['assigned_to' => $entityId, 'status' => 'assigned']);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_unassign_asset') {
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        if (!$assetId)
            jsonResponse(['success' => false]);
        $assetModel = new Asset();
        $result = $assetModel->update($assetId, ['assigned_to' => null, 'status' => 'available']);
        jsonResponse(['success' => (bool) $result]);
    }

    // ------------------------------------------------------------
// ATTRIBUTE DEFINITIONS (for the form dropdown)
// ------------------------------------------------------------
    if ($action === 'api_get_attribute_definitions') {
        $assetType = $_GET['asset_type'] ?? '';
        $defModel = new \Ministry\Models\AttributeDefinition();
        $defs = $assetType
            ? $defModel->forAssetType($assetType)
            : $defModel->getAll();

        // Clean up internal `*_decoded` keys before sending
        $clean = array_map(function ($d) {
            return [
                'attr_key' => $d['attr_key'],
                'display_label_ar' => $d['display_label_ar'],
                'value_type' => $d['value_type'],
                'unit' => $d['unit'],
                'allowed_values' => $d['allowed_values_decoded'],
                'applies_to_types' => $d['applies_to_types_decoded'],
                'visible_when' => $d['visible_when_decoded'],
                'sort_order' => $d['sort_order'],
            ];
        }, $defs);

        jsonResponse(['success' => true, 'definitions' => $clean]);
    }

    // ------------------------------------------------------------
    // CONSUMABLE MANAGEMENT
    // ------------------------------------------------------------
    if ($action === 'api_get_consumables') {
        $search = $_GET['search'] ?? '';
        $consumableModel = new Consumable();
        $consumables = $consumableModel->getWithDetails();
        if ($search) {
            $consumables = array_filter($consumables, function ($item) use ($search) {
                return stripos($item['name'], $search) !== false || stripos($item['sku'], $search) !== false;
            });
            $consumables = array_values($consumables);
        }
        jsonResponse(['success' => true, 'consumables' => $consumables]);
    }

    if ($action === 'api_delete_consumable') {
        $consumableId = (int) ($_POST['consumable_id'] ?? 0);
        if (!$consumableId)
            jsonResponse(['success' => false]);
        $consumableModel = new Consumable();
        $result = $consumableModel->delete($consumableId);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_adjust_quantity') {
        $consumableId = (int) ($_POST['consumable_id'] ?? 0);
        $change = (int) ($_POST['change'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (!$consumableId || $change == 0) {
            jsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);
        }
        $consumableModel = new Consumable();
        $result = $consumableModel->updateQuantity($consumableId, $change, $reason);
        jsonResponse(['success' => (bool) $result]);
    }
    // ------------------------------------------------------------
    // TICKET MANAGEMENT (FULL)
    // ------------------------------------------------------------

    if ($action === 'api_get_tickets') {
        $user = $_SESSION['user_data'];
        $clearance = (int) $user['clearance_level'];
        $entityId = (int) $user['entity_id'];
        $filter = $_GET['filter'] ?? 'all';

        $ticketModel = new \Ministry\Models\Ticket();

        if ($clearance < 3) {
            $tickets = $ticketModel->getWithDetails(['created_by' => $entityId]);
        } else {
            $filters = [];
            if ($filter !== 'all')
                $filters['status'] = $filter;
            $tickets = $ticketModel->getWithDetails($filters);
        }

        $ticketIds = array_column($tickets, 'ticket_id');
        $assignments = $ticketModel->getAssignmentsForTickets($ticketIds);

        $formatted = array_map(function ($t) use ($assignments, $entityId) {
            $ticketAssignments = $assignments[$t['ticket_id']] ?? [];
            $pendingAssignmentId = null;
            $isAssignedPending = false;
            foreach ($ticketAssignments as $ass) {
                if ((int) $ass['technician_id'] === $entityId && $ass['status'] === 'pending') {
                    $pendingAssignmentId = $ass['assignment_id'];
                    $isAssignedPending = true;
                    break;
                }
            }
            return [
                'ticket_id' => $t['ticket_id'],
                'ticket_number' => '#' . $t['ticket_id'],
                'title' => $t['title'],
                'status' => $t['status'],
                'priority' => $t['priority'],
                'ticket_type' => $t['ticket_type'] ?? null,
                'category' => $t['category'] ?? null,
                'created_at' => $t['created_at'],
                'device_name' => $t['asset_tag'] ?? $t['serial_number'] ?? '---',
                'submitted_by_name' => $t['created_by_name'] ?? $t['reporter_name'] ?? 'غير معروف',
                'submitted_by_phone' => $t['reporter_phone'] ?? null,
                'pending_assignment_id' => $pendingAssignmentId,
                'is_assigned_pending' => $isAssignedPending,
                'assignments' => $ticketAssignments,
            ];
        }, $tickets);

        if ($clearance >= 3) {
            $stats = $ticketModel->getStats();
        } else {
            $userTickets = $ticketModel->getWithDetails(['created_by' => $entityId]);
            $stats = [
                'total' => count($userTickets),
                'pending_approval' => count(array_filter($userTickets, fn($t) => $t['status'] === 'pending_approval')),
                'open' => count(array_filter($userTickets, fn($t) => $t['status'] === 'open')),
                'in_progress' => count(array_filter($userTickets, fn($t) => $t['status'] === 'in_progress')),
                'awaiting_parts' => count(array_filter($userTickets, fn($t) => $t['status'] === 'awaiting_parts')),
                'frozen' => count(array_filter($userTickets, fn($t) => $t['status'] === 'frozen')),
                'closed' => count(array_filter($userTickets, fn($t) => $t['status'] === 'closed')),
                'resolved' => 0,
                'rejected' => 0,
                'pending' => count(array_filter($userTickets, fn($t) => $t['status'] === 'pending_approval')),
            ];
        }

        jsonResponse([
            'success' => true,
            'tickets' => $formatted,
            'stats' => $stats,
            'user_level' => $clearance,
            'user_entity_id' => $entityId,
        ]);
    }

    if ($action === 'api_get_ticket_details') {
        $ticketId = (int) ($_GET['id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false, 'message' => 'معرف التذكرة مطلوب']);

        $user = $_SESSION['user_data'];
        $clearance = (int) $user['clearance_level'];
        $entityId = (int) $user['entity_id'];

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->getFullDetails($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);

        if ($clearance < 3) {
            $isCreator = (int) $ticket['created_by'] === $entityId;
            $isAssigned = false;
            foreach ($ticket['assignments'] as $a) {
                if ((int) $a['technician_id'] === $entityId) {
                    $isAssigned = true;
                    break;
                }
            }
            if (!$isCreator && !$isAssigned) {
                jsonResponse(['success' => false, 'message' => 'غير مصرح لك بعرض هذه التذكرة']);
            }
        }

        if ($clearance >= 3) {
            $noteModel = new \Ministry\Models\TicketNote();
            $ticket['notes_count'] = $noteModel->countByTicket($ticketId);
        }

        jsonResponse(['success' => true, 'ticket' => $ticket, 'user_level' => $clearance]);
    }

    if ($action === 'api_create_ticket') {

        // 1. Paper gate
        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'يجب إرفاق التذكرة الورقية أو تقرير التقديم']);
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title === '' || $description === '') {
            jsonResponse(['success' => false, 'message' => 'العنوان والوصف مطلوبان']);
        }

        // 2. Identity — internal XOR public
        $creatorEntityId = isset($_SESSION['user_data']['entity_id'])
            ? (int) $_SESSION['user_data']['entity_id'] : null;
        $reporterName = trim($_POST['reporter_name'] ?? '');
        $reporterPhone = trim($_POST['reporter_phone'] ?? '');
        $isInternal = $creatorEntityId !== null;
        $isPublic = $reporterName !== '' && $reporterPhone !== '';

        if ($isInternal === $isPublic) {
            jsonResponse(['success' => false, 'message' => 'يجب تحديد هوية مقدّم واحد فقط']);
        }

        // 3. Validate + store the file
        $file = $_FILES['attachment'];
        if ($file['size'] > 10 * 1024 * 1024) {
            jsonResponse(['success' => false, 'message' => 'حجم الملف يتجاوز 10 ميجابايت']);
        }
        if (!function_exists('finfo_open')) {
            jsonResponse(['success' => false, 'message' => 'إعداد الخادم ناقص (fileinfo)']);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (!in_array($mime, $allowed, true)) {
            jsonResponse(['success' => false, 'message' => 'نوع الملف غير مسموح']);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $rel = 'storage/tickets/' . date('Y/m');
        $dir = __DIR__ . '/../' . $rel;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('api_create_ticket: cannot create dir ' . $dir);
            jsonResponse(['success' => false, 'message' => 'فشل تجهيز مجلد الرفع']);
        }
        if (!is_writable($dir)) {
            error_log('api_create_ticket: dir not writable ' . $dir);
            jsonResponse(['success' => false, 'message' => 'مجلد الرفع غير قابل للكتابة']);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $savedPath = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $savedPath)) {
            error_log('api_create_ticket: move_uploaded_file failed to ' . $savedPath);
            jsonResponse(['success' => false, 'message' => 'فشل حفظ الملف']);
        }

        // 4. Controller owns the transaction
        $pdo->beginTransaction();
        try {
            $ticketModel = new \Ministry\Models\Ticket();
            $ticketId = $ticketModel->createTicket([
                'title' => $title,
                'description' => $description,
                'asset_id' => !empty($_POST['asset_id']) ? (int) $_POST['asset_id'] : null,
                'priority' => $_POST['priority'] ?? 'medium',
                'ticket_type' => $_POST['ticket_type'] ?? 'internal',
                'category' => $_POST['category'] ?? null,
                'estimated_hours' => isset($_POST['estimated_hours']) ? (float) $_POST['estimated_hours'] : null,
                'created_by' => $isInternal ? $creatorEntityId : null,
                'reporter_name' => $isInternal ? null : $reporterName,
                'reporter_phone' => $isInternal ? null : $reporterPhone,
                'attachment_path' => $rel . '/' . $stored,
                'attachment_name' => $file['name'],
                'attachment_mime' => $mime,
                'attachment_size' => $file['size'],
            ]);

            // Your real NotificationService API
            $service = new \Ministry\Services\NotificationService($pdo);
            $service->notify([
                'category' => 'ticket',
                'severity' => 'warning',
                'title' => 'تذكرة جديدة #' . $ticketId . ' تحتاج موافقتك',
                'body' => $title,
                'related_type' => 'ticket',
                'related_id' => $ticketId,
                'action_url' => 'index.php?action=tickets&id=' . $ticketId,
                'requires_action' => true,
            ], ['roles' => [5]]);

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'ticket_id' => $ticketId,
                'status' => 'pending_approval',
                'assigned_to' => $isInternal ? $creatorEntityId : null,
            ]);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            if (file_exists($savedPath))
                @unlink($savedPath);
            error_log('api_create_ticket: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'فشل إنشاء التذكرة']);
        }
    }

    if ($action === 'api_update_ticket') {
        $ticketId = (int) ($_POST['id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false, 'message' => 'معرف التذكرة مطلوب']);

        $user = $_SESSION['user_data'];
        $clearance = (int) $user['clearance_level'];
        $entityId = (int) $user['entity_id'];

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);

        // Frozen tickets locked for everyone except L5
        if ($ticket['status'] === 'frozen' && $clearance < 5) {
            jsonResponse(['success' => false, 'message' => 'التذكرة مجمدة — لا يمكن التعديل']);
        }
        if ($clearance < 3 && (int) $ticket['created_by'] !== $entityId) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح لك بتعديل هذه التذكرة']);
        }

        $data = [
            'title' => trim($_POST['title'] ?? $ticket['title']),
            'description' => trim($_POST['description'] ?? $ticket['description']),
            'priority' => $_POST['priority'] ?? $ticket['priority'],
            'asset_id' => $_POST['asset_id'] ?? $ticket['asset_id'],
            'ticket_type' => $_POST['ticket_type'] ?? $ticket['ticket_type'],
            'category' => $_POST['category'] ?? $ticket['category'],
            'estimated_hours' => isset($_POST['estimated_hours']) ? (float) $_POST['estimated_hours'] : $ticket['estimated_hours'],
            'internal_notes' => trim($_POST['internal_notes'] ?? $ticket['internal_notes']),
        ];
        if ($data['asset_id'] === '' || $data['asset_id'] === null)
            $data['asset_id'] = null;

        $result = $ticketModel->updateTicket($ticketId, $data);
        jsonResponse(['success' => (bool) $result]);
    }

    if ($action === 'api_update_assignment_status') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);

        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!$assignmentId || !in_array($status, ['active', 'completed'])) {
            jsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);
        }

        $assignModel = new \Ministry\Models\TicketAssignment();
        $assign = $assignModel->findById($assignmentId);
        if (!$assign)
            jsonResponse(['success' => false, 'message' => 'التكليف غير موجود']);

        $myEntityId = (int) $_SESSION['user_data']['entity_id'];
        $myLevel = (int) $_SESSION['user_data']['clearance_level'];
        if ((int) $assign['technician_id'] !== $myEntityId && $myLevel < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }

        // Frozen check
        $ticket = (new \Ministry\Models\Ticket())->findById((int) $assign['ticket_id']);
        if ($ticket && $ticket['status'] === 'frozen' && $myLevel < 5) {
            jsonResponse(['success' => false, 'message' => 'التذكرة مجمدة — لا يمكن التعديل']);
        }

        $result = $assignModel->updateAssignmentStatus($assignmentId, $status);
        jsonResponse(['success' => (bool) $result]);
    }

    // ------------------------------------------------------------
    // ATTACHMENT DOWNLOAD (streams binary, not JSON)
    // ------------------------------------------------------------
    if ($action === 'api_download_attachment') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 3) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'غير مصرح';
            exit;
        }

        $attachmentId = (int) ($_GET['id'] ?? 0);
        if (!$attachmentId) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'معرف الملف مطلوب';
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM ticket_attachments WHERE attachment_id = ?");
        $stmt->execute([$attachmentId]);
        $att = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$att) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'الملف غير موجود';
            exit;
        }

        $path = __DIR__ . '/../storage/tickets/' . $att['file_path'];
        if (!file_exists($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'الملف غير موجود على الخادم';
            exit;
        }

        $disposition = isset($_GET['download']) ? 'attachment' : 'inline';
        header('Content-Type: ' . $att['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($att['original_name']) . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------
    // TICKET NOTES (group chat, technical staff only)
    // ------------------------------------------------------------
    if ($action === 'api_get_ticket_notes') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 3) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $ticketId = (int) ($_GET['ticket_id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false, 'message' => 'معرف التذكرة مطلوب']);

        $ticketModel = new \Ministry\Models\Ticket();
        if (!$ticketModel->findById($ticketId)) {
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);
        }

        $noteModel = new \Ministry\Models\TicketNote();
        $notes = $noteModel->getByTicket($ticketId);
        jsonResponse(['success' => true, 'notes' => $notes, 'count' => count($notes)]);
    }

    if ($action === 'api_add_ticket_note') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 3) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');

        if (!$ticketId)
            jsonResponse(['success' => false, 'message' => 'معرف التذكرة مطلوب']);
        if ($body === '')
            jsonResponse(['success' => false, 'message' => 'الرجاء إدخال نص الملاحظة']);
        if (mb_strlen($body) > 2000) {
            jsonResponse(['success' => false, 'message' => 'النص طويل جداً (2000 حرف كحد أقصى)']);
        }

        $ticketModel = new \Ministry\Models\Ticket();
        if (!$ticketModel->findById($ticketId)) {
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);
        }

        $noteModel = new \Ministry\Models\TicketNote();
        $noteId = $noteModel->addNote($ticketId, (int) $_SESSION['user_data']['entity_id'], $body);
        if (!$noteId)
            jsonResponse(['success' => false, 'message' => 'تعذر حفظ الملاحظة']);

        $notes = $noteModel->getByTicket($ticketId);
        $newNote = null;
        foreach ($notes as $n) {
            if ((int) $n['note_id'] === (int) $noteId) {
                $newNote = $n;
                break;
            }
        }

        jsonResponse(['success' => true, 'note_id' => $noteId, 'note' => $newNote, 'count' => count($notes)]);
    }

    // ============================================================
    // NOTIFICATIONS
    // ============================================================
    if ($action === 'api_get_notification_counts') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $service = new \Ministry\Services\NotificationService($pdo);
        $counts = $service->getUnreadCount((int) $_SESSION['user_data']['entity_id']);
        jsonResponse(['success' => true, 'counts' => $counts]);
    }

    if ($action === 'api_get_notifications') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $service = new \Ministry\Services\NotificationService($pdo);
        $entityId = (int) $_SESSION['user_data']['entity_id'];

        $filters = [];
        if (!empty($_GET['unread_only']))
            $filters['unread_only'] = true;
        if (!empty($_GET['category']))
            $filters['category'] = $_GET['category'];
        if (!empty($_GET['severity']))
            $filters['severity'] = $_GET['severity'];
        if (!empty($_GET['requires_action']))
            $filters['requires_action'] = true;

        $limit = (int) ($_GET['limit'] ?? 20);
        $offset = (int) ($_GET['offset'] ?? 0);

        $items = $service->getNotifications($entityId, $filters, $limit, $offset);
        $counts = $service->getUnreadCount($entityId);

        jsonResponse([
            'success' => true,
            'notifications' => $items,
            'counts' => $counts,
            'has_more' => count($items) === $limit,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    if ($action === 'api_mark_notification_read') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $deliveryId = (int) ($_POST['delivery_id'] ?? 0);
        if (!$deliveryId)
            jsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);
        $service = new \Ministry\Services\NotificationService($pdo);
        $ok = $service->markRead($deliveryId, (int) $_SESSION['user_data']['entity_id']);
        jsonResponse(['success' => $ok]);
    }

    if ($action === 'api_mark_all_notifications_read') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $service = new \Ministry\Services\NotificationService($pdo);
        $count = $service->markAllRead((int) $_SESSION['user_data']['entity_id']);
        jsonResponse(['success' => true, 'count' => $count]);
    }

    if ($action === 'api_send_broadcast') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $severity = $_POST['severity'] ?? 'info';
        $targetType = $_POST['target_type'] ?? 'all';
        $targetVal = $_POST['target_value'] ?? null;

        if ($title === '' || $body === '') {
            jsonResponse(['success' => false, 'message' => 'العنوان والنص مطلوبان']);
        }
        if (!in_array($severity, ['info', 'warning', 'danger'])) {
            jsonResponse(['success' => false, 'message' => 'مستوى غير صالح']);
        }
        if (!in_array($targetType, ['all', 'role', 'department', 'individual'])) {
            jsonResponse(['success' => false, 'message' => 'فئة مستهدفة غير صالحة']);
        }

        $service = new \Ministry\Services\NotificationService($pdo);
        $bid = $service->sendBroadcast(
            $title,
            $body,
            $severity,
            $targetType,
            $targetVal,
            (int) $_SESSION['user_data']['entity_id']
        );
        jsonResponse(['success' => true, 'broadcast_id' => $bid]);
    }

    // ============================================================
    // TICKET LIFECYCLE (L5 approval, freeze, assign, open, close, revert)
    // ============================================================
    if ($action === 'api_approve_ticket') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false, 'message' => 'معرف مطلوب']);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);
        if ($ticket['status'] !== 'pending_approval') {
            jsonResponse(['success' => false, 'message' => 'التذكرة ليست في انتظار الموافقة']);
        }

        $ok = $ticketModel->approveTicket($ticketId, (int) $_SESSION['user_data']['entity_id']);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل الاعتماد']);

        $service = new \Ministry\Services\NotificationService($pdo);
        $recipientIds = [(int) $ticket['created_by']];
        $assignments = (new \Ministry\Models\TicketAssignment())->getByTicket($ticketId);
        foreach ($assignments as $a)
            $recipientIds[] = (int) $a['technician_id'];

        $service->notify([
            'category' => 'ticket',
            'severity' => 'success',
            'title' => 'تمت الموافقة على التذكرة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => false,
        ], ['entity_ids' => array_unique($recipientIds)]);

        $service->resolveByRelated('ticket', $ticketId);

        jsonResponse(['success' => true]);
    }

    if ($action === 'api_freeze_ticket') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false]);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);

        $ok = $ticketModel->freezeTicket($ticketId, (int) $_SESSION['user_data']['entity_id']);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل التجميد']);

        $service = new \Ministry\Services\NotificationService($pdo);
        $recipientIds = [(int) $ticket['created_by']];
        foreach ((new \Ministry\Models\TicketAssignment())->getByTicket($ticketId) as $a) {
            $recipientIds[] = (int) $a['technician_id'];
        }
        $service->notify([
            'category' => 'ticket',
            'severity' => 'warning',
            'title' => 'تم تجميد التذكرة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => false,
        ], ['entity_ids' => array_unique($recipientIds)]);

        jsonResponse(['success' => true]);
    }

    if ($action === 'api_unfreeze_ticket') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $target = $_POST['target_status'] ?? 'open';
        if (!$ticketId)
            jsonResponse(['success' => false]);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);

        $ok = $ticketModel->unfreezeTicket($ticketId, (int) $_SESSION['user_data']['entity_id'], $target);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل إلغاء التجميد']);

        $service = new \Ministry\Services\NotificationService($pdo);
        $recipientIds = [(int) $ticket['created_by']];
        foreach ((new \Ministry\Models\TicketAssignment())->getByTicket($ticketId) as $a) {
            $recipientIds[] = (int) $a['technician_id'];
        }
        $service->notify([
            'category' => 'ticket',
            'severity' => 'info',
            'title' => 'تم إلغاء تجميد التذكرة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => false,
        ], ['entity_ids' => array_unique($recipientIds)]);

        jsonResponse(['success' => true]);
    }

    if ($action === 'api_reassign_ticket') {
        if (!isLoggedIn() || (int) $_SESSION['user_data']['clearance_level'] < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        }
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $techId = (int) ($_POST['technician_id'] ?? 0);
        if (!$ticketId || !$techId)
            jsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);
        if ($ticket['status'] === 'closed') {
            jsonResponse(['success' => false, 'message' => 'لا يمكن إعادة إسناد تذكرة مغلقة']);
        }

        $ok = $ticketModel->reassignTechnician($ticketId, $techId, (int) $_SESSION['user_data']['entity_id']);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل إعادة الإسناد']);

        $entityModel = new \Ministry\Models\Entity();
        $tech = $entityModel->findById($techId);

        $service = new \Ministry\Services\NotificationService($pdo);
        $service->notify([
            'category' => 'ticket',
            'severity' => 'info',
            'title' => 'تم إسنادك للتذكرة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => true,
        ], ['entity_ids' => [$techId]]);

        jsonResponse(['success' => true, 'technician_name' => $tech['full_name'] ?? '']);
    }

    if ($action === 'api_mark_ticket_opened') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false]);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);
        if ($ticket['status'] !== 'open')
            jsonResponse(['success' => true, 'skipped' => true]);

        $entityId = (int) $_SESSION['user_data']['entity_id'];
        $assignments = (new \Ministry\Models\TicketAssignment())->getByTicket($ticketId);
        $isAssigned = false;
        foreach ($assignments as $a) {
            if ((int) $a['technician_id'] === $entityId) {
                $isAssigned = true;
                break;
            }
        }
        if (!$isAssigned)
            jsonResponse(['success' => false, 'message' => 'أنت غير معين لهذه التذكرة']);

        $ok = $ticketModel->transitionStatus($ticketId, 'in_progress', $entityId);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل التحديث']);

        $service = new \Ministry\Services\NotificationService($pdo);
        $service->notify([
            'category' => 'ticket',
            'severity' => 'info',
            'title' => 'الفني بدأ العمل على التذكرة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => false,
        ], ['entity_ids' => [(int) $ticket['created_by']]]);

        jsonResponse(['success' => true]);
    }

    if ($action === 'api_revert_ticket_to_open') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false]);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);
        if (!in_array($ticket['status'], ['in_progress', 'awaiting_parts'])) {
            jsonResponse(['success' => false, 'message' => 'يمكن الإرجاع فقط من حالة "قيد المعالجة" أو "بانتظار قطع"']);
        }

        $entityId = (int) $_SESSION['user_data']['entity_id'];
        $level = (int) $_SESSION['user_data']['clearance_level'];
        $isCreator = (int) $ticket['created_by'] === $entityId;

        if (!$isCreator && $level < 5) {
            jsonResponse(['success' => false, 'message' => 'فقط منشئ التذكرة أو المدير العام يمكنه الإرجاع']);
        }

        $ok = $ticketModel->transitionStatus($ticketId, 'open', $entityId);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل الإرجاع']);

        jsonResponse(['success' => true]);
    }

    if ($action === 'api_close_ticket') {
        if (!isLoggedIn())
            jsonResponse(['success' => false, 'message' => 'غير مصرح']);
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        if (!$ticketId)
            jsonResponse(['success' => false]);

        $ticketModel = new \Ministry\Models\Ticket();
        $ticket = $ticketModel->findById($ticketId);
        if (!$ticket)
            jsonResponse(['success' => false, 'message' => 'التذكرة غير موجودة']);

        $entityId = (int) $_SESSION['user_data']['entity_id'];
        $level = (int) $_SESSION['user_data']['clearance_level'];

        if ($ticket['status'] === 'frozen' && $level < 5) {
            jsonResponse(['success' => false, 'message' => 'التذكرة مجمدة — لا يمكن الإغلاق']);
        }

        $assignments = (new \Ministry\Models\TicketAssignment())->getByTicket($ticketId);
        $isAssigned = false;
        foreach ($assignments as $a) {
            if ((int) $a['technician_id'] === $entityId) {
                $isAssigned = true;
                break;
            }
        }
        if (!$isAssigned && $level < 5) {
            jsonResponse(['success' => false, 'message' => 'غير مصرح بإغلاق هذه التذكرة']);
        }

        $ok = $ticketModel->transitionStatus($ticketId, 'closed', $entityId);
        if (!$ok)
            jsonResponse(['success' => false, 'message' => 'فشل الإغلاق']);

        $service = new \Ministry\Services\NotificationService($pdo);
        $recipientIds = [(int) $ticket['created_by']];
        foreach ($assignments as $a)
            $recipientIds[] = (int) $a['technician_id'];
        $service->notify([
            'category' => 'ticket',
            'severity' => 'success',
            'title' => 'تم إغلاق التذكرة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => false,
        ], ['entity_ids' => array_unique($recipientIds)]);

        $service->notify([
            'category' => 'ticket',
            'severity' => 'info',
            'title' => 'تذكرة مغلقة #' . $ticketId,
            'body' => $ticket['title'],
            'related_type' => 'ticket',
            'related_id' => $ticketId,
            'action_url' => 'index.php?action=tickets&id=' . $ticketId,
            'requires_action' => false,
        ], ['roles' => [5]]);

        jsonResponse(['success' => true]);
    }

    // ------------------------------------------------------------
    // ACTIVITY LOG
    // ------------------------------------------------------------
    if ($action === 'api_get_activity') {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int) ($_GET['entity_id'] ?? 0);
        if (empty($entityType) || !$entityId) {
            jsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);
        }
        $activityModel = new ActivityLog();
        $activities = $activityModel->getForEntity($entityType, $entityId, 50);
        jsonResponse(['success' => true, 'activities' => $activities]);
    }

    // If no API matched, return 404
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API غير موجود']);
    exit;
}

// ------------------------------------------------------------------
// PAGE ROUTES (non-API)
// ------------------------------------------------------------------
$publicActions = ['login', 'register', 'report'];

if (!in_array($action, $publicActions) && !isLoggedIn()) {
    redirectTo('login');
}

switch ($action) {
    case 'register':
        require_once __DIR__ . '/pages/register.php';
        break;
    case 'login':
        if (isLoggedIn())
            redirectTo('dashboard');
        require_once __DIR__ . '/pages/login.php';
        break;
    case 'report':
        require_once __DIR__ . '/pages/public_report.php';
        break;
    case 'dashboard':
        require_once __DIR__ . '/pages/dashboard.php';
        break;
    case 'assets':
        require_once __DIR__ . '/pages/assets_list.php';
        break;
    case 'asset_add':
    case 'asset_edit':
        require_once __DIR__ . '/pages/asset_edit.php';
        break;
    case 'import_assets':
        require_once __DIR__ . '/pages/import_assets.php';
        break;
    case 'entities':
        require_once __DIR__ . '/pages/entities_list.php';
        break;
    case 'entity_add':
    case 'entity_edit':
        require_once __DIR__ . '/pages/entity_edit.php';
        break;
    case 'tickets':
        require_once __DIR__ . '/pages/tickets_list.php';
        break;
    case 'users':
        require_once __DIR__ . '/pages/users_list.php';
        break;
    case 'user_add':
    case 'user_edit':
        require_once __DIR__ . '/pages/user_edit.php';
        break;
    case 'consumables':
        require_once __DIR__ . '/pages/consumables_list.php';
        break;
    case 'consumable_add':
    case 'consumable_edit':
        require_once __DIR__ . '/pages/consumable_edit.php';
        break;
    case 'profile':
        require_once __DIR__ . '/pages/profile.php';
        break;
    case 'notifications':
        require_once __DIR__ . '/pages/notifications.php';
        break;
    default:
        http_response_code(404);
        require_once __DIR__ . '/pages/404.php';
        break;
}