<?php
// pages/public_report.php
$logged_in = false;
$user_data = null;

// Fetch dropdown data
$assetModel = new \Ministry\Models\Asset();
$deptModel = new \Ministry\Models\Department();

$assets = $assetModel->getWithDetails();
$departments = $deptModel->findAll();

require_once __DIR__ . '/../components/layout/header.php';
?>

<body class="light-mode bg-bg flex flex-col items-center justify-start min-h-screen py-8 px-4">
    <div class="w-full max-w-2xl bg-card-bg border border-card-border rounded-xl shadow-card p-6 md:p-8">

        <div class="text-center mb-6">
            <img src="assets/img/logo3-h.jpeg" alt="logo" class="h-16 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-text">الإبلاغ عن مشكلة</h1>
            <p class="text-text-secondary text-sm mt-1">
                وزارة السياحة والآثار — وحدة نظم المعلومات والحاسوب
            </p>
        </div>

        <div id="form-message" class="mb-4"></div>

        <form id="public-ticket-form" enctype="multipart/form-data">

            <div class="form-group mb-4">
                <label for="reporter_name" class="form-label block text-text font-medium mb-2">الاسم الكامل *</label>
                <input type="text" id="reporter_name" name="reporter_name" required minlength="2" maxlength="100"
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text"
                    placeholder="أدخل اسمك الكامل">
            </div>

            <div class="form-group mb-4">
                <label for="reporter_phone" class="form-label block text-text font-medium mb-2">رقم الهاتف *</label>
                <input type="tel" id="reporter_phone" name="reporter_phone" required
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text"
                    placeholder="05XXXXXXXX" dir="ltr">
                <small class="text-text-secondary text-xs">سيتم التواصل معك على هذا الرقم لمتابعة الطلب</small>
            </div>

            <div class="form-group mb-4">
                <label for="title" class="form-label block text-text font-medium mb-2">عنوان المشكلة *</label>
                <input type="text" id="title" name="title" required minlength="3" maxlength="255"
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text"
                    placeholder="مثال: الطابعة لا تعمل">
            </div>

            <div class="form-group mb-4">
                <label for="description" class="form-label block text-text font-medium mb-2">وصف المشكلة *</label>
                <textarea id="description" name="description" rows="5" required minlength="10" maxlength="5000"
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text"
                    placeholder="صف المشكلة بالتفصيل..."></textarea>
            </div>

            <div class="form-group mb-4">
                <label for="asset_id" class="form-label block text-text font-medium mb-2">الجهاز (اختياري)</label>
                <select id="asset_id" name="asset_id"
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text">
                    <option value="">اختر الجهاز (إن وجد)</option>
                    <?php foreach ($assets as $a): ?>
                        <option value="<?php echo (int) $a['asset_id']; ?>">
                            <?php echo htmlspecialchars(($a['asset_tag'] ?: $a['serial_number']) . ' — ' . ($a['asset_type'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-4">
                <label for="department_id" class="form-label block text-text font-medium mb-2">القسم (اختياري)</label>
                <select id="department_id" name="department_id"
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text">
                    <option value="">اختر القسم</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int) $d['department_id']; ?>">
                            <?php echo htmlspecialchars($d['department_name_ar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-6">
                <label for="attachments" class="form-label block text-text font-medium mb-2">صور (اختياري)</label>
                <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf"
                    class="form-input w-full px-4 py-3 border border-input-border rounded-lg bg-input-bg text-text">
                <small class="text-text-secondary text-xs">حتى 3 ملفات، 2 ميجابايت لكل ملف (JPG / PNG / PDF)</small>
            </div>

            <button type="submit" id="submit-btn"
                class="btn-primary w-full bg-primary text-white py-3 px-6 rounded-lg font-medium hover:bg-primary-hover transition-colors">
                <i class="fas fa-paper-plane ml-2"></i> إرسال الطلب
            </button>
        </form>

        <div class="text-center mt-6">
            <a href="index.php?action=login" class="text-link text-link hover:text-link-hover">
                لديك حساب؟ تسجيل الدخول
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/layout/footer.php'; ?>

    <script>
        document.getElementById('public-ticket-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');
            const msg = document.getElementById('form-message');
            msg.innerHTML = '';
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الإرسال...';

            try {
                const fd = new FormData(this);
                const res = await fetch('index.php?action=api_public_create_ticket', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    msg.innerHTML = `
                    <div class="alert alert-success bg-alert-success-bg text-alert-success-text p-4 rounded-lg">
                        <i class="fas fa-check-circle ml-2"></i>
                        <strong>${data.message}</strong>
                        <br><small>رقم التذكرة: <strong>${data.ticket_number}</strong></small>
                    </div>`;
                    this.reset();
                    btn.innerHTML = '<i class="fas fa-check ml-2"></i> تم الإرسال';
                    btn.disabled = true;
                } else {
                    msg.innerHTML = `
                    <div class="alert alert-danger bg-alert-danger-bg text-alert-danger-text p-4 rounded-lg">
                        <i class="fas fa-exclamation-circle ml-2"></i> ${data.message}
                    </div>`;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane ml-2"></i> إرسال الطلب';
                }
            } catch (err) {
                msg.innerHTML = `
                <div class="alert alert-danger bg-alert-danger-bg text-alert-danger-text p-4 rounded-lg">
                    <i class="fas fa-exclamation-circle ml-2"></i> خطأ في الاتصال بالخادم
                </div>`;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane ml-2"></i> إرسال الطلب';
            }
        });
    </script>
</body>

</html>