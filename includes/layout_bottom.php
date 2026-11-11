        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('show');
    });
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('show');
    });
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (type ? ' ' + type : '');
    setTimeout(() => { t.className = 'toast'; }, 2800);
}

// Number fields (Qty, Price, Discount, etc.) across the whole app should
// only ever change from what the person actually types. Browsers silently
// nudge the value up/down when you scroll the mouse wheel over a focused
// number field, or press the Up/Down arrow keys - neither is obvious to
// the person typing, so both are blocked everywhere.
document.addEventListener('wheel', function(e) {
    if (e.target.tagName === 'INPUT' && e.target.type === 'number' && document.activeElement === e.target) {
        e.preventDefault();
    }
}, { passive: false });

document.addEventListener('keydown', function(e) {
    if ((e.key === 'ArrowUp' || e.key === 'ArrowDown') &&
        e.target.tagName === 'INPUT' && e.target.type === 'number') {
        e.preventDefault();
    }
});
</script>
</body>
</html>
