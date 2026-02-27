'use strict';

(function () {

    const MAX_ROWS = 10;

    const table  = document.getElementById('activitiesTable');
    const addBtn = document.getElementById('addRow');

    function getRowCount() {
        return table?.querySelectorAll('.activity-row').length ?? 0;
    }

    function updateRowNumbers() {
        table?.querySelectorAll('.activity-row').forEach((row, i) => {
            row.querySelector('.row-num').textContent = i + 1;
            row.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
            });
        });
    }

    function buildRow(index) {
        const statuses = window.KSG_STATUSES ?? {};
        const opts = Object.entries(statuses)
            .map(([k, v]) => {
                const escaped = v
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
                return `<option value="${k.replace(/"/g, '&quot;')}">${escaped}</option>`;
            })
            .join('');

        const tr = document.createElement('tr');
        tr.className = 'activity-row';
        tr.innerHTML = `
            <td class="row-num">${index + 1}</td>
            <td><input type="text" name="activities[${index}][activity]" placeholder="Describe the activity or issue" maxlength="500"></td>
            <td>
                <select name="activities[${index}][status]">
                    <option value="">-- Status --</option>
                    ${opts}
                </select>
            </td>
            <td><input type="text" name="activities[${index}][action_needed]" placeholder="Action required" maxlength="500"></td>
            <td><input type="text" name="activities[${index}][notes]" placeholder="Additional notes" maxlength="500"></td>
            <td><button type="button" class="btn-remove-row" title="Remove row">&#x2715;</button></td>
        `;
        return tr;
    }

    if (addBtn) {
        addBtn.addEventListener('click', () => {
            if (getRowCount() >= MAX_ROWS) {
                alert(`Maximum of ${MAX_ROWS} activities allowed.`);
                return;
            }
            const idx = getRowCount();
            table.querySelector('tbody').appendChild(buildRow(idx));
        });
    }

    table?.addEventListener('click', e => {
        if (e.target.closest('.btn-remove-row')) {
            const rows = table.querySelectorAll('.activity-row');
            if (rows.length <= 1) {
                alert('At least one activity row is required.');
                return;
            }
            e.target.closest('.activity-row').remove();
            updateRowNumbers();
        }
    });

    const form = document.getElementById('reportForm');

    form?.addEventListener('submit', e => {
        const errors = [];

        ['campus', 'department', 'hod_name', 'week_start', 'week_end', 'report_date',
         'prepared_by_name', 'prepared_by_designation'].forEach(name => {
            const el = form.querySelector(`[name="${name}"]`);
            if (!el || !el.value.trim()) {
                errors.push(`"${el?.labels?.[0]?.textContent?.replace('*', '').trim() ?? name}" is required.`);
                el?.classList.add('input-error');
            } else {
                el?.classList.remove('input-error');
            }
        });

        const ws = form.querySelector('[name="week_start"]')?.value;
        const we = form.querySelector('[name="week_end"]')?.value;
        if (ws && we) {
            const wsDate = new Date(ws);
            const weDate = new Date(we);
            if (wsDate > weDate) {
                errors.push('"Reporting Week End" must be on or after the start date.');
            }
        }

        const activityInputs = table?.querySelectorAll('[name*="[activity]"]');
        const hasActivity = Array.from(activityInputs ?? []).some(i => i.value.trim());
        if (!hasActivity) {
            errors.push('Please enter at least one activity / issue.');
        }

        if (errors.length) {
            e.preventDefault();
            showErrors(errors);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    function showErrors(errors) {
        let box = document.getElementById('clientErrors');
        if (!box) {
            box = document.createElement('div');
            box.id = 'clientErrors';
            box.className = 'alert alert-error';
            form.prepend(box);
        }
        box.innerHTML = '';
        const strong = document.createElement('strong');
        strong.textContent = 'Please fix the following:';
        box.appendChild(strong);
        const ul = document.createElement('ul');
        errors.forEach(msg => {
            const li = document.createElement('li');
            li.textContent = msg;
            ul.appendChild(li);
        });
        box.appendChild(ul);
    }

    form?.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('input', () => el.classList.remove('input-error'));
    });

    const weekStart = document.querySelector('[name="week_start"]');
    const weekEnd   = document.querySelector('[name="week_end"]');

    weekStart?.addEventListener('change', () => {
        if (!weekEnd?.value && weekStart.value) {
            const d = new Date(weekStart.value);
            d.setDate(d.getDate() + 4);
            weekEnd.value = d.toISOString().split('T')[0];
        }
    });

    document.querySelectorAll('.filter-control').forEach(el => {
        el.addEventListener('change', () => {
            const pageInput = document.querySelector('[name="page"]');
            if (pageInput) pageInput.value = 1;
        });
    });

})();