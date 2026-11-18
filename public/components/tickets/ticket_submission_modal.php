<!-- ticket_submission_modal.php -->
<div id="ticketSubmissionModal" class="modal-overlay"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content"
        style="background: var(--clr-card-bg); border-radius: 12px; padding: 2rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: var(--clr-card-shadow);">
        <div class="flex justify-between items-center mb-4">
            <h3 style="color: var(--clr-text); font-size: 1.25rem; font-weight: 600;">تقديم تذكرة صيانة جديدة</h3>
            <button onclick="closeTicketModal()"
                style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--clr-text-secondary);">&times;</button>
        </div>
        <div id="ticket-error" style="color: var(--clr-danger); margin-bottom: 1rem; display: none;"></div>
        <form id="ticketForm" method="post">
            <div class="form-group">
                <label for="ticket_title" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">العنوان
                    *</label>
                <input type="text" id="ticket_title" name="title" class="form-input" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
            </div>
            <div class="form-group">
                <label for="ticket_description" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الوصف
                    *</label>
                <textarea id="ticket_description" name="description" class="form-input" rows="4" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);"></textarea>
            </div>
            <div class="form-group">
                <label for="ticket_attachment" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">
                    التذكرة الورقية / تقرير التقديم *
                </label>
                <input type="file" id="ticket_attachment" name="attachment" required
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width: 100%; padding: 0.6rem; border: 1px solid var(--clr-input-border);
               border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                <small style="color: var(--clr-text-secondary); display: block; margin-top: 0.35rem;">
                    مطلوب — لن يتم إنشاء التذكرة بدون المستند الورقي
                </small>
                <div id="attachment-preview"
                    style="margin-top: 0.35rem; font-size: 0.85rem; color: var(--clr-primary);"></div>
            </div>
            <div class="form-group">
                <label for="ticket_asset" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الجهاز
                    (اختياري)</label>
                <select id="ticket_asset" name="asset_id" class="form-input"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                    <option value="">اختر الجهاز</option>
                    <!-- Populated by JS -->
                </select>
            </div>
            <div class="form-group">
                <label for="ticket_priority" class="form-label"
                    style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الأولوية</label>
                <select id="ticket_priority" name="priority" class="form-input"
                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                    <option value="low">منخفض</option>
                    <option value="medium" selected>متوسط</option>
                    <option value="high">مرتفع</option>
                    <option value="critical">حرج</option>
                </select>
            </div>
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" onclick="closeTicketModal()" class="btn-secondary"
                    style="background: var(--clr-card-bg); color: var(--clr-primary); border: 1px solid var(--clr-primary); padding: 0.5rem 1.5rem; border-radius: 6px; cursor: pointer;">إلغاء</button>
                <button type="submit" id="ticket-submit-btn" class="btn-primary"
                    style="background: var(--clr-primary); color: white; border: none; padding: 0.5rem 1.5rem; border-radius: 6px; cursor: pointer;">تقديم</button>
            </div>
        </form>
    </div>
</div>

<script>
    let ticketAssets = [];

    function showTicketSubmissionModal() {
        document.getElementById('ticketSubmissionModal').style.display = 'flex';
        // Load assets for dropdown
        fetch('index.php?action=api_get_assets&filter=all')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    ticketAssets = data.assets;
                    const select = document.getElementById('ticket_asset');
                    select.innerHTML = '<option value="">اختر الجهاز</option>';
                    ticketAssets.forEach(asset => {
                        const opt = document.createElement('option');
                        opt.value = asset.asset_id;
                        opt.textContent = asset.asset_tag || asset.serial_number || 'غير معروف';
                        select.appendChild(opt);
                    });
                }
            });
    }

    function closeTicketModal() {
        document.getElementById('ticketSubmissionModal').style.display = 'none';
        document.getElementById('ticketForm').reset();
        document.getElementById('ticket-error').style.display = 'none';
    }

    document.getElementById('ticket_attachment').addEventListener('change', function () {
        const f = this.files[0];
        document.getElementById('attachment-preview').textContent = f ? '📎 ' + f.name : '';
    });

    document.getElementById('ticketForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('ticket-submit-btn');
        const errorDiv = document.getElementById('ticket-error');
        errorDiv.style.display = 'none';

        // Native `required` already guards the file, but double-check:
        if (!document.getElementById('ticket_attachment').files.length) {
            errorDiv.textContent = 'يجب إرفاق التذكرة الورقية أو تقرير التقديم';
            errorDiv.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'جاري الرفع...';

        const formData = new FormData(this); // includes the file automatically

        fetch('index.php?action=api_create_ticket', {
            method: 'POST',
            body: formData            // do NOT set Content-Type — browser sets the multipart boundary
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'تقديم';
                if (data.success) {
                    closeTicketModal();
                    if (typeof loadTickets === 'function') {
                        loadTickets(currentFilter || 'all');
                    }
                    showToast(
                        data.assigned_to
                            ? '✅ تم إنشاء التذكرة وإسنادها إليك — بانتظار الموافقة'
                            : '✅ تم إنشاء التذكرة — بانتظار الموافقة (بدون إسناد)',
                        'success'
                    );
                } else {
                    errorDiv.textContent = data.message || 'حدث خطأ';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'تقديم';
                errorDiv.textContent = 'خطأ في الاتصال بالخادم';
                errorDiv.style.display = 'block';
            });
    });

    // Close modal on outside click
    document.getElementById('ticketSubmissionModal').addEventListener('click', function (e) {
        if (e.target === this) closeTicketModal();
    });
</script>