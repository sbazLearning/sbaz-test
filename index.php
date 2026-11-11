<?php
$dbFile = __DIR__ . '/school.sqlite';
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE IF NOT EXISTS students (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  class_name TEXT NOT NULL,
  marks INTEGER NOT NULL DEFAULT 0,
  attendance INTEGER NOT NULL DEFAULT 100
)');
$db->exec('CREATE TABLE IF NOT EXISTS notices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  posted_at TEXT NOT NULL
)');

if ((int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn() === 0) {
  $seedStudents = [
    ['Aarav Sharma', 'Grade 10-A', 91, 96],
    ['Sara Khan', 'Grade 10-A', 87, 94],
    ['Rohan Verma', 'Grade 10-B', 76, 89],
    ['Meera Nair', 'Grade 10-B', 95, 98]
  ];
  $st = $db->prepare('INSERT INTO students (name, class_name, marks, attendance) VALUES (?, ?, ?, ?)');
  foreach ($seedStudents as $row) $st->execute($row);
}
if ((int)$db->query('SELECT COUNT(*) FROM notices')->fetchColumn() === 0) {
  $db->prepare('INSERT INTO notices (title, posted_at) VALUES (?, ?)')->execute(['Science Fair registration closes Friday', date('Y-m-d')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['action'] ?? '';
  if ($act === 'add_student' && !empty($_POST['name'])) {
    $st = $db->prepare('INSERT INTO students (name, class_name, marks, attendance) VALUES (?, ?, ?, ?)');
    $st->execute([
      trim($_POST['name']),
      trim($_POST['class_name'] ?? 'Grade 10-A'),
      max(0, min(100, (int)($_POST['marks'] ?? 0))),
      max(0, min(100, (int)($_POST['attendance'] ?? 100)))
    ]);
  } elseif ($act === 'add_notice' && !empty($_POST['title'])) {
    $st = $db->prepare('INSERT INTO notices (title, posted_at) VALUES (?, ?)');
    $st->execute([trim($_POST['title']), date('Y-m-d H:i')]);
  } elseif ($act === 'delete_student' && !empty($_POST['id'])) {
    $st = $db->prepare('DELETE FROM students WHERE id = ?');
    $st->execute([(int)$_POST['id']]);
  }
}

$filter = trim($_GET['class'] ?? '');
if ($filter !== '') {
  $st = $db->prepare('SELECT * FROM students WHERE class_name = ? ORDER BY marks DESC');
  $st->execute([$filter]);
  $students = $st->fetchAll(PDO::FETCH_ASSOC);
} else {
  $students = $db->query('SELECT * FROM students ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}
$notices = $db->query('SELECT * FROM notices ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$avgMarks = $students ? round(array_sum(array_column($students, 'marks')) / count($students), 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Sunrise High School Portal</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="hero">
    <img src="assets/campus-banner.png" alt="Sunrise High School Banner" class="banner">
    <div class="nav">
      <a href="index.php" class="pill active">Dashboard</a>
      <a href="index.php?class=Grade+10-A" class="pill">Grade 10-A</a>
      <a href="index.php?class=Grade+10-B" class="pill">Grade 10-B</a>
      <a href="report.php" class="pill">Analytics Report</a>
    </div>
  </header>

  <section class="stats">
    <div class="stat"><b><?= count($students) ?></b><span>Students Shown</span></div>
    <div class="stat"><b><?= htmlspecialchars((string)$avgMarks) ?>%</b><span>Avg Score</span></div>
    <div class="stat"><b>PHP <?= htmlspecialchars(phpversion()) ?></b><span>SQLite Live</span></div>
  </section>

  <section class="card">
    <h3>Add Student Record</h3>
    <form method="POST" action="index.php" class="form-grid">
      <input type="hidden" name="action" value="add_student">
      <input name="name" placeholder="Student full name" required>
      <select name="class_name">
        <option value="Grade 10-A">Grade 10-A</option>
        <option value="Grade 10-B">Grade 10-B</option>
      </select>
      <input type="number" name="marks" min="0" max="100" value="85" placeholder="Marks %">
      <input type="number" name="attendance" min="0" max="100" value="95" placeholder="Attendance %">
      <button type="submit">＋ Save Student</button>
    </form>
  </section>

  <section class="card">
    <h3>Student Roster</h3>
    <table>
      <thead><tr><th>Name</th><th>Class</th><th>Marks</th><th>Attend.</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><b><?= htmlspecialchars($s['name']) ?></b></td>
            <td><?= htmlspecialchars($s['class_name']) ?></td>
            <td><?= (int)$s['marks'] ?>%</td>
            <td><?= (int)$s['attendance'] ?>%</td>
            <td>
              <form method="POST" action="index.php" style="margin:0">
                <input type="hidden" name="action" value="delete_student">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="del">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h3>School Notice Board</h3>
    <form method="POST" action="index.php" class="notice-form">
      <input type="hidden" name="action" value="add_notice">
      <input name="title" placeholder="Post new notice..." required>
      <button type="submit">Post</button>
    </form>
    <ul class="notices">
      <?php foreach ($notices as $n): ?>
        <li><b><?= htmlspecialchars($n['title']) ?></b> <small>(<?= htmlspecialchars($n['posted_at']) ?>)</small></li>
      <?php endforeach; ?>
    </ul>
  </section>
</body>
</html>
