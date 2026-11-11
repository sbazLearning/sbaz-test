<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Bill - Shop Billing System</title>
<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --accent: #f97316;
    --success: #16a34a;
    --danger: #dc2626;
    --bg: #f4f6fb;
    --card: #ffffff;
    --text: #1e2432;
    --text-light: #64748b;
    --border: #e2e8f0;
    --radius: 12px;
    --shadow: 0 2px 10px rgba(30,41,59,0.06);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, Verdana, Arial, sans-serif; background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 32px; width: 100%; max-width: 520px; }
h1 { text-align: center; margin-bottom: 6px; font-size: 22px; }
p.sub { text-align: center; color: var(--text-light); font-size: 13px; margin-bottom: 24px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-light); margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
.form-group input:focus, .form-group textarea:focus { border-color: var(--primary); }
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 16px; border-radius: 9px; border: none; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all .15s; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-outline { background: #fff; border: 1.5px solid var(--border); color: var(--text); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-danger { background: #fef2f2; color: var(--danger); }
.actions { display: flex; gap: 10px; margin-top: 20px; }
.actions a { flex: 1; text-align: center; }
.toast { position: fixed; bottom: 24px; right: 24px; background: #101a2e; color: #fff; padding: 13px 18px; border-radius: 10px; font-size: 13.5px; box-shadow: 0 8px 30px rgba(0,0,0,.15); z-index: 999; opacity: 0; transform: translateY(10px); transition: all .25s; pointer-events: none; }
.toast.show { opacity: 1; transform: translateY(0); }
.toast.error { background: #b91c1c; }
.toast.success { background: #15803d; }
</style>
</head>
<body>
<div class="card">
    <h1>✏️ Edit Bill</h1>
    <p class="sub">Modify an existing bill. All previous versions are kept in Edit History.</p>
    <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13.5px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom:10px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        <br>Use the <b>Bill History</b> page to view or edit a saved bill.
    </div>
    <div class="actions">
        <a href="bills.php" class="btn btn-outline">&larr; Back to Bills</a>
        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
    </div>
</div>
<div class="toast" id="toast"></div>
<script>
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (type ? ' ' + type : '');
    setTimeout(() => { t.className = 'toast'; }, 2800);
}
</script>
</body>
</html>