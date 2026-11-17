// School LMS - Client-side helpers

document.addEventListener('DOMContentLoaded', function () {
    // Mobile nav toggle
    const toggle = document.getElementById('navToggle');
    const menu = document.getElementById('navMenu');
    if (toggle && menu) {
        toggle.addEventListener('click', () => menu.classList.toggle('open'));
    }

    // Auto-hide flash messages
    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(() => flash.remove(), 5000);
    }

    // Age auto-calculation from date of birth
    const dobInputs = document.querySelectorAll('input[data-age-target]');
    dobInputs.forEach(dob => {
        const targetId = dob.getAttribute('data-age-target');
        const ageField = document.getElementById(targetId);
        if (!ageField) return;

        function calcAge() {
            const val = dob.value;
            if (!val) { ageField.value = ''; return; }
            const birth = new Date(val);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageField.value = age >= 0 ? age : '';
        }
        dob.addEventListener('change', calcAge);
        dob.addEventListener('input', calcAge);
        if (dob.value) calcAge();
    });

    // Photo preview
    const photoInputs = document.querySelectorAll('input[type="file"][data-preview]');
    photoInputs.forEach(input => {
        const previewId = input.getAttribute('data-preview');
        const preview = document.getElementById(previewId);
        if (!preview) return;
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // Confirm delete
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
});

// ===== DRAG-AND-DROP: make dashboard items movable =====
document.addEventListener('DOMContentLoaded', function () {
    // Grid containers in which items can be reordered
    const grids = document.querySelectorAll('.stats-grid, .quick-actions-grid, .form-grid');
    if (!grids.length) return;

    // Items that should be draggable (exclude form-heavy cards on enroll/settings pages)
    const draggableItems = document.querySelectorAll(
        '.stats-grid .stat-card, .quick-actions-grid .quick-action-card, .form-grid > .card:not(.enroll-card):not(.settings-card)'
    );

    function getDragAfterElement(container, y) {
        const items = [...container.querySelectorAll('.draggable-item:not(.dragging)')];
        return items.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    grids.forEach(grid => {
        // Assign a stable key to each child for localStorage persistence
        Array.from(grid.children).forEach((child, idx) => {
            if (!child.dataset.orderKey) {
                child.dataset.orderKey = (child.className.replace(/\s+/g, '_')) + '__' + idx;
            }
        });

        // Restore previously saved order
        const storageKey = 'dragOrder_' + (grid.className.replace(/\s+/g, '_'));
        try {
            const savedOrder = JSON.parse(localStorage.getItem(storageKey));
            if (Array.isArray(savedOrder)) {
                const children = Array.from(grid.children);
                savedOrder.forEach(key => {
                    const child = children.find(c => c.dataset.orderKey === key);
                    if (child) grid.appendChild(child);
                });
            }
        } catch (e) { /* ignore malformed storage */ }

        grid.addEventListener('dragover', (e) => {
            const dragging = document.querySelector('.dragging');
            if (!dragging) return;
            e.preventDefault();
            const afterEl = getDragAfterElement(grid, e.clientY) || grid.lastElementChild;
            grid.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            if (afterEl && afterEl !== dragging) afterEl.classList.add('drag-over');
        });

        grid.addEventListener('dragleave', (e) => {
            if (!grid.contains(e.relatedTarget)) {
                grid.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            }
        });

        grid.addEventListener('drop', (e) => {
            e.preventDefault();
            const dragging = document.querySelector('.dragging');
            if (!dragging || dragging.parentElement !== grid) return;
            const afterEl = getDragAfterElement(grid, e.clientY);
            if (afterEl == null) {
                grid.appendChild(dragging);
            } else {
                grid.insertBefore(dragging, afterEl);
            }
            dragging.classList.remove('dragging');
            grid.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));

            // Persist new order
            const order = Array.from(grid.children).map(c => c.dataset.orderKey);
            try { localStorage.setItem(storageKey, JSON.stringify(order)); } catch (err) { }
        });
    });

    // Enable dragging on each item
    draggableItems.forEach(item => {
        item.classList.add('draggable-item');
        item.setAttribute('draggable', 'true');
        item.addEventListener('dragstart', (e) => {
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', item.dataset.orderKey || ''); } catch (err) { }
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        });
    });
});

// Staff attendance: enable/disable arrival & departure time inputs for a day when the Present checkbox toggles
function toggleDay(checkbox, rowIdx, dayName) {
    const row = checkbox.closest('tr');
    if (!row) return;
    // Arrival & departure inputs for this day share the day name in their `name` attribute, e.g. arrival[SID][Monday]
    const suffix = '][' + dayName + ']';
    row.querySelectorAll('input[type="time"]').forEach(t => {
        if (t.name && t.name.indexOf(suffix) !== -1) {
            t.disabled = !checkbox.checked;
        }
    });
    // Update the P(1)/A(0) status span text WITHOUT removing the checkbox element
    const statusSpan = checkbox.closest('label') ? checkbox.closest('label').querySelector('.att-status') : null;
    if (statusSpan) {
        statusSpan.textContent = checkbox.checked ? 'P(1)' : 'A(0)';
    }
    updateRowTotal(row);
}

// Recalculate the Total column for a staff row based on checked Present boxes
function updateRowTotal(row) {
    if (!row) return;
    const totalCell = row.querySelector('td:last-child strong');
    if (!totalCell) return;
    const checked = row.querySelectorAll('input[type="checkbox"][name^="present["]:checked');
    totalCell.textContent = checked.length;
}

// Report card score calculation helpers (used on teacher report page)
function getGradeRemark(total) {
    const score = parseInt(total) || 0;
    if (score >= 90) return { grade: 'A1', remark: 'EXCELLENT', aggregate: 1 };
    if (score >= 80) return { grade: 'B2', remark: 'VERY GOOD', aggregate: 2 };
    if (score >= 75) return { grade: 'B3', remark: 'GOOD', aggregate: 3 };
    if (score >= 70) return { grade: 'C4', remark: 'CREDIT', aggregate: 4 };
    if (score >= 65) return { grade: 'C5', remark: 'CREDIT', aggregate: 5 };
    if (score >= 60) return { grade: 'C6', remark: 'CREDIT', aggregate: 6 };
    if (score >= 55) return { grade: 'D7', remark: 'PASS', aggregate: 7 };
    if (score >= 50) return { grade: 'E8', remark: 'PASS', aggregate: 8 };
    return { grade: 'F9', remark: 'FAIL', aggregate: 9 };
}
