(() => {
    const department = document.getElementById('hr-department'), attendance = document.getElementById('hr-attendance'), search = document.getElementById('hr-search');
    const details = document.getElementById('hr-employee-details');
    function filter() {
        let count = 0;
        document.querySelectorAll('#hr-employees tr').forEach(row => {
            const status = row.dataset.attendance;
            const match = (!department.value || row.dataset.department === department.value) && (!attendance.value || (attendance.value === 'present' ? ['present','late'].includes(status) : attendance.value === 'on_leave' ? ['on_leave','leave'].includes(status) : status === attendance.value)) && row.dataset.name.includes(search.value.toLowerCase());
            row.hidden = !match; if (match) count++;
        });
        document.getElementById('hr-empty').hidden = count > 0;
    }
    [department,attendance,search].forEach(input => input.addEventListener('input',filter));
    document.querySelectorAll('button[data-department]').forEach(button => button.onclick = () => { department.value = button.dataset.department; attendance.value = ''; search.value = ''; details.open = true; filter(); details.scrollIntoView({block:'nearest'}); });
    document.querySelectorAll('[data-att-filter]').forEach(button => button.onclick = () => { attendance.value = button.dataset.attFilter; department.value = ''; search.value = ''; details.open = true; filter(); details.scrollIntoView({block:'nearest'}); });
})();
