(() => {
    let dialog, employeeId, requestId = 0;
    const labels = { none: 'No new action', nte: 'NTE issued', warning: 'Warning issued', violation: 'Violation recorded', suspension: 'Suspension' };
    function node(tag, text, className) {
        const n = document.createElement(tag); if (text != null) n.textContent = text; if (className) n.className = className; return n;
    }
    function date(value) { return value ? new Date(value.slice(0,10) + 'T12:00:00').toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' }) : '—'; }
    function init() {
        dialog = document.createElement('dialog'); dialog.className = 'employee-status-dialog'; dialog.setAttribute('aria-labelledby', 'employee-status-title');
        dialog.innerHTML = '<div class="status-heading"><h2 id="employee-status-title">Employee status</h2><button type="button" class="btn btn-outline" aria-label="Close employee status">Close</button></div><form class="status-filters"><label>From<input class="form-control" type="date" name="from" required></label><label>To<input class="form-control" type="date" name="to" required></label><button class="btn btn-outline">Apply</button><button type="button" class="btn btn-ghost" data-all>All history</button></form><div class="status-content" aria-live="polite"></div>';
        document.body.append(dialog);
        dialog.querySelector('.status-heading button').onclick = () => { requestId++; dialog.close(); };
        dialog.addEventListener('cancel', () => requestId++);
        dialog.querySelector('form').onsubmit = event => { event.preventDefault(); load(new URLSearchParams(new FormData(event.target))); };
        dialog.querySelector('[data-all]').onclick = () => load(new URLSearchParams({ all: '1' }));
    }
    async function load(params = new URLSearchParams()) {
        const seq = ++requestId, content = dialog.querySelector('.status-content'); content.replaceChildren(node('p','Loading employee status…'));
        try {
            const response = await fetch(`/employees/${employeeId}/status?${params}`);
            if (!response.ok) throw new Error(response.status === 403 ? 'You cannot view this employee.' : 'Unable to load status. Please try again.');
            const data = await response.json(); if (seq !== requestId) return;
            dialog.querySelector('h2').textContent = data.employee.full_name + ' · Employee status';
            const form = dialog.querySelector('form'); form.elements.from.value = data.from; form.elements.to.value = data.to;
            content.replaceChildren(node('p',`${data.employee.dept_name || 'Unassigned'} · ${data.employee.status}`, 'text-muted'));
            content.append(node('h3',`${data.absences.length} absent day${data.absences.length === 1 ? '' : 's'} · ${data.all ? 'All history' : date(data.from) + ' – ' + date(data.to)}`));
            if (!data.absences.length) content.append(node('p','No confirmed absences in this period.'));
            const list = node('ul'); data.absences.forEach(a => list.append(node('li',`${date(a.work_date)}${a.remarks ? ' — ' + a.remarks : ''}`))); content.append(list);
            content.append(node('h3','BTW decisions & HR history'));
            if (!data.cases.length) content.append(node('p', data.absences.length ? 'Needs BTW — no case recorded yet.' : 'No BTW cases recorded.'));
            data.cases.forEach(c => {
                const section = node('section', null, 'status-case');
                section.append(node('h4', `${c.case_ref} · ${data.labels[c.status] || c.status}`));
                let dates = []; try { dates = JSON.parse(c.absence_dates); } catch {}
                section.append(node('p','Related absences: ' + (Array.isArray(dates) ? dates.map(date).join(', ') : '—')));
                section.append(node('p',c.hr_remarks || 'Awaiting HR summary.'));
                if (c.decision_at) section.append(node('p','Decision date: ' + date(c.decision_at)));
                if (c.authorized_return_date) section.append(node('p',`${c.status === 'approved' ? 'Authorized' : 'Planned'} return: ${date(c.authorized_return_date)}${c.status !== 'approved' ? ' · BTW approval still required' : ''}`));
                const actions = data.actions.filter(a => a.case_id === c.id);
                actions.forEach(a => {
                    const row = node('div',null,'status-history');
                    row.append(node('strong',`${labels[a.action_type] || a.action_type} · ${date(a.issued_date || a.created_at)}`));
                    row.append(node('p',`${data.labels[a.decision] || a.decision} — ${a.summary}`));
                    if (a.action_type === 'suspension') {
                        const today = new Intl.DateTimeFormat('en-CA', { timeZone:'Asia/Manila', year:'numeric', month:'2-digit', day:'2-digit' }).format(new Date());
                        const state = today < a.suspension_start ? 'Scheduled' : today > a.suspension_end ? 'Period completed' : 'Ongoing';
                        row.append(node('p',`Suspension (${state}): ${date(a.suspension_start)} – ${date(a.suspension_end)} · Return: ${date(a.return_date)}`));
                    }
                    row.append(node('small',`Recorded by ${a.author_name}`)); section.append(row);
                });
                const link = node('a','Open BTW case','btn btn-sm btn-outline'); link.href = `/btw/${c.id}`; section.append(link); content.append(section);
            });
        } catch (error) { if (seq === requestId) content.replaceChildren(node('p',error.message)); }
    }
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-employee-status]'); if (!trigger) return;
        if (!dialog) init(); employeeId = trigger.dataset.employeeStatus; dialog.showModal();
        const params = new URLSearchParams(); if (trigger.dataset.from) params.set('from', trigger.dataset.from); if (trigger.dataset.to) params.set('to',trigger.dataset.to); load(params);
    });
})();
