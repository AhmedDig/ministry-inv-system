<!-- components/activity/activity_timeline.php -->
<?php
/**
 * Reusable Activity Timeline Component.
 * Requires: $entityType, $entityId, $title (optional)
 */
$entityType = $entityType ?? '';
$entityId = $entityId ?? 0;
$title = $title ?? 'سجل النشاطات';
?>
<div class="activity-timeline" data-entity-type="<?php echo $entityType; ?>" data-entity-id="<?php echo $entityId; ?>">
    <h4 style="color: var(--clr-text); font-weight: 600; margin-bottom: 1rem;">
        <?php echo $title; ?>
    </h4>
    <div id="activity-list-<?php echo $entityType . '-' . $entityId; ?>" class="activity-feed">
        <div style="text-align: center; padding: 1rem; color: var(--clr-text-secondary);">جاري التحميل...</div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('.activity-timeline[data-entity-type="<?php echo $entityType; ?>"][data-entity-id="<?php echo $entityId; ?>"]');
        if (!container) return;

        const entityType = container.dataset.entityType;
        const entityId = container.dataset.entityId;
        const listId = `activity-list-${entityType}-${entityId}`;
        const list = document.getElementById(listId);
        if (!list) return;

        fetch(`index.php?action=api_get_activity&entity_type=${entityType}&entity_id=${entityId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.activities.length) {
                    list.innerHTML = data.activities.map(act => {
                        const iconMap = {
                            'asset': 'fa-box',
                            'consumable': 'fa-cubes',
                            'ticket': 'fa-ticket-alt',
                            'user': 'fa-user',
                            'entity': 'fa-users',
                            'login': 'fa-sign-in-alt'
                        };
                        const icon = iconMap[act.entity_type] || 'fa-circle';
                        const actionLabels = {
                            'created': 'إنشاء',
                            'updated': 'تحديث',
                            'deleted': 'حذف',
                            'assigned': 'إسناد',
                            'unassigned': 'فك إسناد',
                            'status_changed': 'تغيير الحالة',
                            'quantity_changed': 'تغيير الكمية',
                            'approved': 'موافقة',
                            'forced_reset': 'إعادة تعيين',
                            'login_success': 'تسجيل دخول',
                            'login_failed': 'فشل تسجيل دخول'
                        };
                        const actionLabel = actionLabels[act.event_type] || act.event_type;
                        let detailsHtml = '';
                        if (act.details) {
                            const details = JSON.parse(act.details);
                            if (details.changes) {
                                detailsHtml = Object.entries(details.changes).map(([field, change]) =>
                                    `<span style="background: var(--clr-bg); padding: 0.1rem 0.4rem; border-radius: 4px; margin: 0.1rem; display: inline-block; font-size: 0.75rem;">
                                    ${field}: ${change.old ?? '-'} → ${change.new ?? '-'}
                                </span>`
                                ).join(' ');
                            } else if (details.reason) {
                                detailsHtml = `<span style="font-size: 0.75rem; color: var(--clr-text-secondary);">السبب: ${details.reason}</span>`;
                            } else if (details.name) {
                                detailsHtml = `<span style="font-size: 0.75rem; color: var(--clr-text-secondary);">${details.name}</span>`;
                            }
                        }
                        return `
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid var(--clr-card-border);">
                            <div style="flex-shrink: 0; width: 2rem; text-align: center; color: var(--clr-primary);">
                                <i class="fas ${icon}"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="color: var(--clr-text); font-weight: 500;">${actionLabel}</div>
                                ${detailsHtml ? `<div style="margin-top: 0.1rem;">${detailsHtml}</div>` : ''}
                                <div style="font-size: 0.7rem; color: var(--clr-text-secondary); margin-top: 0.1rem;">
                                    ${act.actor_name || 'النظام'} · ${new Date(act.occurred_at).toLocaleString('ar-EG')}
                                </div>
                            </div>
                        </div>
                    `;
                    }).join('');
                } else {
                    list.innerHTML = `<div style="text-align: center; padding: 1rem; color: var(--clr-text-secondary);">لا توجد نشاطات مسجلة</div>`;
                }
            })
            .catch(err => {
                console.error(err);
                list.innerHTML = `<div style="text-align: center; padding: 1rem; color: var(--clr-danger);">حدث خطأ في تحميل النشاطات</div>`;
            });
    });
</script>