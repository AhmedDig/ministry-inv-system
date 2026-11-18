<!-- components/tickets/ticket_details_modal.php -->
<div id="ticketDetailsModal" class="modal-overlay"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center;">
    <div class="modal-content"
        style="background: var(--clr-card-bg); border-radius: 12px; padding: 2rem; max-width: 820px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: var(--clr-card-shadow);">
        <div class="flex justify-between items-center mb-4">
            <h3 style="color: var(--clr-text); font-size: 1.25rem; font-weight: 600;">
                تفاصيل التذكرة <span id="ticketNumber" style="color: var(--clr-text-secondary);"></span>
            </h3>
            <button onclick="closeTicketDetails()"
                style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--clr-text-secondary);">&times;</button>
        </div>
        <div id="ticketDetailsContent">
            <div style="text-align: center; padding: 2rem; color: var(--clr-text-secondary);">جاري التحميل...</div>
        </div>
    </div>
</div>

<script>
    let currentTicketId = null;
    let currentTicketData = null;
    let userLevel = window.userLevel || 0;
    let currentUserEntityId = <?php echo $_SESSION['user_data']['entity_id'] ?? 0; ?>;
    let activityLoaded = false;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function openTicketDetails(ticketId) {
        currentTicketId = ticketId;
        activityLoaded = false;
        document.getElementById('ticketDetailsModal').style.display = 'flex';
        document.getElementById('ticketNumber').textContent = '#' + ticketId;
        document.getElementById('ticketDetailsContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--clr-text-secondary);">جاري التحميل...</div>';

        fetch(`index.php?action=api_get_ticket_details&id=${ticketId}&_=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.ticket) {
                    if (data.user_level !== undefined) userLevel = data.user_level;
                    currentTicketData = data.ticket;
                    renderTicketDetails(data.ticket);

                    // Auto-transition if this technician is opening an 'open' ticket
                    const isAssigned = (data.ticket.assignments || []).some(a => a.technician_id == currentUserEntityId);
                    if (data.ticket.status === 'open' && isAssigned && userLevel < 5) {
                        fetch('index.php?action=api_mark_ticket_opened', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `ticket_id=${ticketId}`
                        }).then(r => r.json()).then(d => {
                            if (d.success && !d.skipped) {
                                TicketHelpers.showToast('▶️ تم بدء العمل على التذكرة', 'success');
                                setTimeout(() => openTicketDetails(ticketId), 300);
                            }
                        }).catch(() => { });
                    }
                } else {
                    document.getElementById('ticketDetailsContent').innerHTML =
                        `<div style="text-align: center; padding: 2rem; color: var(--clr-danger);">${escapeHtml(data.message || 'فشل التحميل')}</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                document.getElementById('ticketDetailsContent').innerHTML =
                    '<div style="text-align: center; padding: 2rem; color: var(--clr-danger);">خطأ في الاتصال</div>';
            });
    }

    function closeTicketDetails() {
        document.getElementById('ticketDetailsModal').style.display = 'none';
        currentTicketId = null;
        currentTicketData = null;
        activityLoaded = false;
    }

    function renderTicketDetails(ticket) {
        const container = document.getElementById('ticketDetailsContent');
        const statusColor = TicketHelpers.statusColors[ticket.status] || 'var(--clr-text-secondary)';
        const statusLabel = TicketHelpers.statusLabels[ticket.status] || ticket.status;
        const priorityColor = TicketHelpers.priorityColors[ticket.priority] || 'var(--clr-text-secondary)';
        const priorityLabel = TicketHelpers.priorityLabels[ticket.priority] || ticket.priority;

        const isCreator = ticket.created_by == currentUserEntityId;
        const isAssigned = (ticket.assignments || []).some(a => a.technician_id == currentUserEntityId);
        const isFrozen = ticket.status === 'frozen';
        const isClosed = ticket.status === 'closed';
        const isL5 = userLevel >= 5;

        let html = '';

        // Frozen banner
        if (isFrozen) {
            html += `
            <div style="background: rgba(107,114,128,0.15); border: 1px solid #6b7280; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-snowflake" style="font-size: 1.5rem; color: #6b7280;"></i>
                <div>
                    <strong style="color: var(--clr-text);">التذكرة مجمّدة</strong>
                    <div style="color: var(--clr-text-secondary); font-size: 0.85rem;">لا يمكن تعديل هذه التذكرة حتى يتم إلغاء التجميد من قبل المدير العام</div>
                </div>
            </div>`;
        }

        // Basic info
        html += `
    <div id="ticketViewMode" style="${isFrozen && !isL5 ? 'opacity: 0.65; pointer-events: none;' : ''}">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p><strong style="color: var(--clr-text);">العنوان:</strong> <span style="color: var(--clr-text-secondary);">${escapeHtml(ticket.title)}</span></p>
                <p><strong style="color: var(--clr-text);">الوصف:</strong> <span style="color: var(--clr-text-secondary); white-space: pre-line;">${escapeHtml(ticket.description || 'لا يوجد')}</span></p>
                <p><strong style="color: var(--clr-text);">الحالة:</strong> <span style="color: ${statusColor}; font-weight: 600;">${statusLabel}</span></p>
                <p><strong style="color: var(--clr-text);">الأولوية:</strong> <span style="color: ${priorityColor}; font-weight: 600;">${priorityLabel}</span></p>
            </div>
            <div>
                <p><strong style="color: var(--clr-text);">الجهاز:</strong> <span style="color: var(--clr-text-secondary);">${escapeHtml(ticket.asset_tag || ticket.serial_number || 'غير محدد')}</span></p>
                <p><strong style="color: var(--clr-text);">المقدم:</strong> <span style="color: var(--clr-text-secondary);">${escapeHtml(ticket.created_by_name || ticket.reporter_name || 'غير معروف')}</span></p>
                ${ticket.reporter_phone && isL5 ? `
                    <p><strong style="color: var(--clr-text);">الهاتف:</strong>
                        <a href="tel:${escapeHtml(ticket.reporter_phone)}" dir="ltr" style="color: var(--clr-primary); font-weight: 600;">${escapeHtml(ticket.reporter_phone)}</a>
                    </p>` : ''}
                <p><strong style="color: var(--clr-text);">تاريخ الإنشاء:</strong> <span style="color: var(--clr-text-secondary);">${new Date(ticket.created_at).toLocaleString('ar-EG')}</span></p>
                ${ticket.approved_at ? `<p><strong style="color: var(--clr-text);">تاريخ الاعتماد:</strong> <span style="color: var(--clr-text-secondary);">${new Date(ticket.approved_at).toLocaleString('ar-EG')}</span></p>` : ''}
                ${ticket.frozen_at ? `<p><strong style="color: var(--clr-text);">تاريخ التجميد:</strong> <span style="color: var(--clr-text-secondary);">${new Date(ticket.frozen_at).toLocaleString('ar-EG')}</span></p>` : ''}
                ${ticket.closed_at ? `<p><strong style="color: var(--clr-text);">تاريخ الإغلاق:</strong> <span style="color: var(--clr-text-secondary);">${new Date(ticket.closed_at).toLocaleString('ar-EG')}</span></p>` : ''}
            </div>
        </div>`;

        // Attachments
        const attachments = ticket.attachments || [];
        if (attachments.length) {
            html += `
        <div class="mt-4">
            <strong style="color: var(--clr-text);">المرفقات (${attachments.length}):</strong>
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem;">
                ${attachments.map(att => {
                const isImg = att.mime_type.startsWith('image/');
                const kb = Math.round(att.file_size / 1024);
                const url = `index.php?action=api_download_attachment&id=${att.attachment_id}`;
                return `
                    <a href="${url}" target="_blank" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border: 1px solid var(--clr-card-border); border-radius: 8px; background: var(--clr-bg); color: var(--clr-text); text-decoration: none;">
                        ${isImg
                        ? `<img src="${url}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">`
                        : `<i class="fas fa-file-pdf" style="font-size: 1.5rem; color: var(--clr-danger);"></i>`}
                        <div style="display: flex; flex-direction: column; min-width: 0;">
                            <span style="font-size: 0.85rem; font-weight: 500; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(att.original_name)}</span>
                            <span style="font-size: 0.7rem; color: var(--clr-text-secondary);">${kb} KB</span>
                        </div>
                    </a>`;
            }).join('')}
            </div>
        </div>`;
        }

        // Assignments
        const assignments = ticket.assignments || [];
        html += `<div class="mt-4"><strong style="color: var(--clr-text);">الفنيون المعينون:</strong>`;
        if (assignments.length) {
            html += `<div style="margin-top: 0.5rem;">`;
            assignments.forEach(a => {
                const isMe = a.technician_id == currentUserEntityId;
                const canAccept = isMe && a.status === 'pending' && ticket.status === 'in_progress';
                const canComplete = isMe && a.status === 'active';
                html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid var(--clr-card-border);">
                <span style="color: var(--clr-text);">${escapeHtml(a.technician_name || 'غير معروف')} ${isMe ? ' (أنت)' : ''}</span>
                <span style="color: var(--clr-text-secondary); font-size: 0.8rem;">${a.status}</span>
            </div>`;
            });
            html += `</div>`;
        } else {
            html += `<div style="color: var(--clr-text-secondary); margin-top: 0.5rem;">لا توجد تعيينات</div>`;
        }
        html += `</div>`;

        // Status update — L5 only, restricted
        if (isL5 && !isClosed && !isFrozen && ['open', 'in_progress', 'awaiting_parts'].includes(ticket.status)) {
            const options = [];
            if (ticket.status === 'open') options.push('<option value="in_progress">قيد المعالجة</option>');
            if (ticket.status === 'in_progress') options.push('<option value="awaiting_parts">بانتظار قطع</option>');
            if (ticket.status === 'awaiting_parts') options.push('<option value="in_progress">قيد المعالجة</option>');
            if (options.length) {
                html += `
            <div class="mt-4 flex flex-wrap gap-2 items-center">
                <label style="color: var(--clr-text); font-weight: 500;">تحديث الحالة:</label>
                <select id="updateStatusSelect" class="form-input" style="width: auto; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                    ${options.join('')}
                </select>
                <button onclick="applyStatusChange(${ticket.ticket_id})" class="btn-secondary" style="padding: 0.3rem 1rem;">تطبيق</button>
            </div>`;
            }
        }

        // Reassign dropdown — L5 only, not closed
        if (isL5 && !isClosed) {
            html += `
        <div class="mt-4 flex flex-wrap gap-2 items-center">
            <label style="color: var(--clr-text); font-weight: 500;">إعادة إسناد:</label>
            <select id="reassignTechSelect" class="form-input" style="width: auto; padding: 0.4rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                <option value="">اختر فني...</option>
            </select>
            <button onclick="reassignTechnician(${ticket.ticket_id})" class="btn-secondary" style="padding: 0.3rem 1rem;">إعادة إسناد</button>
        </div>`;
        }

        // Action bar
        html += `<div class="mt-4 flex flex-wrap gap-2">
        ${isL5 && ticket.status === 'pending_approval' ? `<button onclick="approveTicket(${ticket.ticket_id})" class="btn-primary" style="padding: 0.4rem 1rem; background: var(--clr-success);"><i class="fas fa-check ml-1"></i> اعتماد</button>` : ''}
        ${isL5 && !isClosed && !isFrozen ? `<button onclick="freezeTicket(${ticket.ticket_id})" class="btn-warning" style="padding: 0.4rem 1rem;"><i class="fas fa-snowflake ml-1"></i> تجميد</button>` : ''}
        ${isL5 && isFrozen ? `<button onclick="unfreezeTicket(${ticket.ticket_id})" class="btn-primary" style="padding: 0.4rem 1rem;"><i class="fas fa-sun ml-1"></i> إلغاء التجميد</button>` : ''}
        ${isL5 && ['open', 'in_progress', 'awaiting_parts'].includes(ticket.status) ? `<button onclick="toggleEditMode()" class="btn-secondary" style="padding: 0.4rem 1rem;"><i class="fas fa-edit ml-1"></i> تعديل</button>` : ''}
        ${isL5 && ['in_progress', 'awaiting_parts'].includes(ticket.status) ? `<button onclick="closeTicket(${ticket.ticket_id})" class="btn-primary" style="padding: 0.4rem 1rem;"><i class="fas fa-check-circle ml-1"></i> إغلاق</button>` : ''}
        ${isAssigned && !isL5 && !isFrozen && !isClosed && ticket.status === 'in_progress' ? `<button onclick="closeTicket(${ticket.ticket_id})" class="btn-primary" style="padding: 0.4rem 1rem;"><i class="fas fa-check-circle ml-1"></i> إغلاق</button>` : ''}
        ${(isCreator || isL5) && !isFrozen && !isClosed && ['in_progress', 'awaiting_parts'].includes(ticket.status) ? `<button onclick="revertTicket(${ticket.ticket_id})" class="btn-warning" style="padding: 0.4rem 1rem;"><i class="fas fa-undo ml-1"></i> إرجاع للمفتوحة</button>` : ''}
    </div>`;

        // Edit form (hidden)
        html += `
    <div id="ticketEditMode" style="display: none; margin-top: 1rem;">
        <form id="editTicketForm" onsubmit="saveTicketEdits(event)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group md:col-span-2">
                    <label style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">العنوان *</label>
                    <input type="text" id="editTitle" required minlength="3" maxlength="255" value="${escapeHtml(ticket.title || '')}"
                           style="width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                </div>
                <div class="form-group md:col-span-2">
                    <label style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الوصف *</label>
                    <textarea id="editDescription" required minlength="10" rows="4"
                              style="width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">${escapeHtml(ticket.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">الأولوية</label>
                    <select id="editPriority" style="width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                        <option value="low"      ${ticket.priority === 'low' ? 'selected' : ''}>منخفض</option>
                        <option value="medium"   ${ticket.priority === 'medium' ? 'selected' : ''}>متوسط</option>
                        <option value="high"     ${ticket.priority === 'high' ? 'selected' : ''}>مرتفع</option>
                        <option value="critical" ${ticket.priority === 'critical' ? 'selected' : ''}>حرج</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">النوع</label>
                    <select id="editTicketType" style="width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">
                        <option value="internal" ${ticket.ticket_type === 'internal' ? 'selected' : ''}>داخلي</option>
                        <option value="external" ${ticket.ticket_type === 'external' ? 'selected' : ''}>خارجي</option>
                        <option value="software" ${ticket.ticket_type === 'software' ? 'selected' : ''}>برمجي</option>
                        <option value="hardware" ${ticket.ticket_type === 'hardware' ? 'selected' : ''}>عتادي</option>
                        <option value="request"  ${ticket.ticket_type === 'request' ? 'selected' : ''}>طلب</option>
                    </select>
                </div>
                <div class="form-group md:col-span-2">
                    <label style="color: var(--clr-text); display: block; margin-bottom: 0.5rem; font-weight: 500;">ملاحظات داخلية</label>
                    <textarea id="editInternalNotes" rows="2"
                              style="width: 100%; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text);">${escapeHtml(ticket.internal_notes || '')}</textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="toggleEditMode()" class="btn-secondary" style="padding: 0.3rem 1rem;">إلغاء</button>
                <button type="submit" class="btn-primary" style="padding: 0.3rem 1rem;">حفظ</button>
            </div>
        </form>
    </div>`;

        // Notes + activity toggles
        html += `
    <div class="mt-4 flex flex-wrap gap-2">
        ${userLevel >= 3 ? `
            <button onclick="toggleNotes()" class="btn-secondary text-sm" style="padding: 0.25rem 0.75rem; border: 1px solid var(--clr-success); background: transparent; color: var(--clr-success);">
                <i class="fas fa-comments ml-1"></i> ملاحظات الفريق
                <span id="notes-count-badge" style="background: var(--clr-success); color: white; padding: 0.05rem 0.4rem; border-radius: 8px; margin-right: 0.25rem; font-size: 0.7rem;">${ticket.notes_count || 0}</span>
            </button>` : ''}
        <button onclick="toggleActivity()" class="btn-secondary text-sm" style="padding: 0.25rem 0.75rem; border: 1px solid var(--clr-primary); background: transparent; color: var(--clr-primary);">
            <i class="fas fa-history ml-1"></i> سجل النشاطات
        </button>
    </div>
    <div id="ticket-notes-container" style="display: none; margin-top: 0.75rem;"></div>
    <div id="ticket-activity-container" style="display: none; margin-top: 0.5rem;"></div>
    </div>`;

        container.innerHTML = html;

        // Load technicians for reassign (L5)
        if (isL5 && !isClosed) {
            fetch('index.php?action=api_get_entities&filter=employee')
                .then(r => r.json())
                .then(d => {
                    if (!d.success) return;
                    const sel = document.getElementById('reassignTechSelect');
                    if (!sel) return;
                    d.entities.forEach(t => {
                        const o = document.createElement('option');
                        o.value = t.entity_id;
                        o.textContent = t.full_name;
                        sel.appendChild(o);
                    });
                });
        }

        window._currentTicketId = ticket.ticket_id;
    }

    // ---------- API actions ----------
    function approveTicket(id) {
        if (!confirm('اعتماد هذه التذكرة؟ سيصبح بإمكان الفني بدء العمل.')) return;
        fetch('index.php?action=api_approve_ticket', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('✅ تم الاعتماد', 'success'); openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function freezeTicket(id) {
        if (!confirm('تجميد هذه التذكرة؟')) return;
        fetch('index.php?action=api_freeze_ticket', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('❄️ تم التجميد', 'success'); openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function unfreezeTicket(id) {
        if (!confirm('إلغاء تجميد هذه التذكرة؟')) return;
        fetch('index.php?action=api_unfreeze_ticket', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}&target_status=open`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('☀️ تم إلغاء التجميد', 'success'); openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function reassignTechnician(id) {
        const sel = document.getElementById('reassignTechSelect');
        if (!sel || !sel.value) { TicketHelpers.showToast('اختر فني أولاً', 'error'); return; }
        if (!confirm('إعادة إسناد هذه التذكرة؟ سيتم إزالة الإسناد الحالي.')) return;
        fetch('index.php?action=api_reassign_ticket', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}&technician_id=${sel.value}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('✅ تم إعادة الإسناد', 'success'); openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function applyStatusChange(id) {
        const sel = document.getElementById('updateStatusSelect');
        if (!sel) return;
        fetch('index.php?action=api_mark_ticket_opened', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            // for awaiting_parts → in_progress, we'll need a dedicated endpoint later
            if (d.success) { openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
        });
    }

    function closeTicket(id) {
        if (!confirm('إغلاق هذه التذكرة نهائياً؟ لا يمكن التراجع.')) return;
        fetch('index.php?action=api_close_ticket', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('✅ تم الإغلاق', 'success'); openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    function revertTicket(id) {
        if (!confirm('إرجاع التذكرة إلى حالة "مفتوحة"؟')) return;
        fetch('index.php?action=api_revert_ticket_to_open', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('↩️ تم الإرجاع', 'success'); openTicketDetails(id); if (typeof loadTickets === 'function') loadTickets(currentFilter); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    // ---------- Edit ----------
    function toggleEditMode() {
        const v = document.getElementById('ticketViewMode');
        const e = document.getElementById('ticketEditMode');
        if (!v || !e) return;
        const editing = e.style.display !== 'none';
        v.style.display = editing ? 'block' : 'none';
        e.style.display = editing ? 'none' : 'block';
    }

    function saveTicketEdits(ev) {
        ev.preventDefault();
        if (!currentTicketId) return;
        const fd = new URLSearchParams();
        fd.append('id', currentTicketId);
        fd.append('title', document.getElementById('editTitle').value.trim());
        fd.append('description', document.getElementById('editDescription').value.trim());
        fd.append('priority', document.getElementById('editPriority').value);
        fd.append('ticket_type', document.getElementById('editTicketType').value);
        fd.append('internal_notes', document.getElementById('editInternalNotes').value.trim());

        fetch('index.php?action=api_update_ticket', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: fd.toString()
        }).then(r => r.json()).then(d => {
            if (d.success) { TicketHelpers.showToast('✅ تم الحفظ', 'success'); openTicketDetails(currentTicketId); }
            else TicketHelpers.showToast('❌ ' + (d.message || 'فشل'), 'error');
        });
    }

    // ---------- Notes (unchanged) ----------
    function toggleNotes() {
        const c = document.getElementById('ticket-notes-container');
        if (!c) return;
        if (c.style.display === 'none') { c.style.display = 'block'; loadNotes(currentTicketId); }
        else c.style.display = 'none';
    }

    function loadNotes(ticketId) {
        const c = document.getElementById('ticket-notes-container');
        c.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--clr-text-secondary);">جاري التحميل...</div>';
        fetch(`index.php?action=api_get_ticket_notes&ticket_id=${ticketId}&_=${Date.now()}`)
            .then(r => r.json())
            .then(d => {
                if (d.success) { renderNotesThread(c, d.notes); updateNotesBadge(d.count); }
                else c.innerHTML = `<div style="text-align: center; padding: 1rem; color: var(--clr-danger);">${escapeHtml(d.message || 'فشل')}</div>`;
            });
    }

    function renderNotesThread(container, notes) {
        const thread = notes.length
            ? notes.map(renderNoteBubble).join('')
            : '<div style="text-align: center; padding: 1.5rem; color: var(--clr-text-secondary);">لا توجد ملاحظات بعد. كن أول من يكتب.</div>';

        container.innerHTML = `
        <div style="border: 1px solid var(--clr-card-border); border-radius: 10px; background: var(--clr-card-bg); overflow: hidden;">
            <div style="padding: 0.5rem 1rem; background: var(--clr-bg); border-bottom: 1px solid var(--clr-card-border); display: flex; justify-content: space-between; align-items: center;">
                <strong style="color: var(--clr-text); font-size: 0.9rem;"><i class="fas fa-comments ml-1" style="color: var(--clr-success);"></i> محادثة الفريق الفني</strong>
                <button onclick="loadNotes(${currentTicketId})" style="background: none; border: none; cursor: pointer; color: var(--clr-text-secondary);"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div id="notes-scroll-area" style="max-height: 320px; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">${thread}</div>
            <div style="padding: 0.75rem; border-top: 1px solid var(--clr-card-border); background: var(--clr-bg);">
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <textarea id="note-input" placeholder="اكتب ملاحظة..." rows="2" maxlength="2000" onkeydown="handleNoteKeydown(event)"
                              style="flex: 1; padding: 0.5rem; border: 1px solid var(--clr-input-border); border-radius: 6px; background: var(--clr-input-bg); color: var(--clr-text); resize: vertical; font-family: inherit;"></textarea>
                    <button onclick="submitNote()" id="note-submit-btn" style="background: var(--clr-success); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>`;

        const area = document.getElementById('notes-scroll-area');
        if (area) area.scrollTop = area.scrollHeight;
        const input = document.getElementById('note-input');
        if (input) input.focus();
    }

    function renderNoteBubble(note) {
        const color = TicketHelpers.getAvatarColor(parseInt(note.author_entity_id, 10) || 0);
        const initial = (note.author_name || '?').charAt(0);
        const dateStr = new Date(note.created_at).toLocaleString('ar-EG', {
            month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
        const safeBody = escapeHtml(note.body).replace(/\n/g, '<br>');
        return `
        <div style="display: flex; gap: 0.6rem; align-items: flex-start;">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: ${color}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">${escapeHtml(initial)}</div>
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;">
                    <strong style="color: var(--clr-text); font-size: 0.85rem;">${escapeHtml(note.author_name || 'مستخدم')}</strong>
                    <span style="color: var(--clr-text-secondary); font-size: 0.7rem;">${dateStr}</span>
                </div>
                <div style="background: var(--clr-bg); border: 1px solid var(--clr-card-border); border-radius: 8px; padding: 0.5rem 0.75rem; margin-top: 0.2rem; color: var(--clr-text); font-size: 0.9rem; line-height: 1.5; word-wrap: break-word;">${safeBody}</div>
            </div>
        </div>`;
    }

    function handleNoteKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitNote(); }
    }

    function submitNote() {
        const input = document.getElementById('note-input');
        const btn = document.getElementById('note-submit-btn');
        if (!input || !btn) return;
        const body = input.value.trim();
        if (!body) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetch('index.php?action=api_add_ticket_note', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ticket_id=${currentTicketId}&body=${encodeURIComponent(body)}`
        }).then(r => r.json()).then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            if (d.success && d.note) {
                input.value = '';
                const area = document.getElementById('notes-scroll-area');
                if (area) {
                    const empty = area.querySelector('div[style*="text-align: center"]');
                    if (empty && empty.textContent.includes('لا توجد ملاحظات')) empty.remove();
                    area.insertAdjacentHTML('beforeend', renderNoteBubble(d.note));
                    area.scrollTop = area.scrollHeight;
                }
                updateNotesBadge(d.count);
            } else {
                TicketHelpers.showToast(d.message || 'فشل', 'error');
            }
        }).catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            TicketHelpers.showToast('خطأ في الاتصال', 'error');
        });
    }

    function updateNotesBadge(count) {
        const b = document.getElementById('notes-count-badge');
        if (b) b.textContent = count;
    }

    // ---------- Activity (unchanged) ----------
    function toggleActivity() {
        const c = document.getElementById('ticket-activity-container');
        if (!c) return;
        if (c.style.display === 'none') {
            c.style.display = 'block';
            if (!activityLoaded) {
                const ticketId = window._currentTicketId;
                if (!ticketId) return;
                c.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--clr-text-secondary);">جاري التحميل...</div>';
                fetch(`index.php?action=api_get_activity&entity_type=ticket&entity_id=${ticketId}&_=${Date.now()}`)
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) { renderActivity(c, d.activities); activityLoaded = true; }
                        else c.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--clr-danger);">فشل</div>';
                    });
            }
        } else {
            c.style.display = 'none';
        }
    }

    function renderActivity(container, activities) {
        if (!activities.length) {
            container.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--clr-text-secondary);">لا توجد نشاطات</div>';
            return;
        }
        const labels = {
            'created': 'إنشاء', 'updated': 'تحديث', 'deleted': 'حذف',
            'assigned': 'إسناد', 'unassigned': 'فك إسناد',
            'status_changed': 'تغيير الحالة', 'assignment_status_changed': 'تغيير حالة الفني'
        };
        container.innerHTML = activities.map(act => {
            let d = '';
            if (act.details) {
                try {
                    const p = JSON.parse(act.details);
                    if (p.changes) {
                        d = Object.entries(p.changes).map(([f, c]) =>
                            `<span style="background: var(--clr-bg); padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.75rem;">${f}: ${c.old ?? '-'} → ${c.new ?? '-'}</span>`
                        ).join(' ');
                    } else if (p.old_status) {
                        d = `<span style="font-size: 0.75rem;">${TicketHelpers.statusLabels[p.old_status] || p.old_status} → ${TicketHelpers.statusLabels[p.new_status] || p.new_status}</span>`;
                    }
                } catch (e) { }
            }
            return `
        <div style="display: flex; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid var(--clr-card-border);">
            <div style="width: 2rem; color: var(--clr-primary);"><i class="fas fa-history"></i></div>
            <div style="flex: 1;">
                <div style="color: var(--clr-text); font-weight: 500;">${labels[act.event_type] || act.event_type}</div>
                ${d ? `<div>${d}</div>` : ''}
                <div style="font-size: 0.7rem; color: var(--clr-text-secondary);">${act.actor_name || 'النظام'} · ${new Date(act.occurred_at).toLocaleString('ar-EG')}</div>
            </div>
        </div>`;
        }).join('');
    }

    document.getElementById('ticketDetailsModal').addEventListener('click', function (e) {
        if (e.target === this) closeTicketDetails();
    });
</script>