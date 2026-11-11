<?php
$db = new PDO('sqlite:' . __DIR__ . '/school.sqlite');
$stats = $db->query('
  SELECT class_name,
         COUNT(*) AS student_count,
         ROUND(AVG(marks), 1) AS avg_marks,
         MAX(marks) AS top_marks,
         ROUND(AVG(attendance), 1) AS avg_attendance
  FROM students
  GROUP BY class_name
  ORDER BY class_name
')->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['format'] ?? '') === 'json') {
  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'classes' => $stats], JSON_PRETTY_PRINT);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Class Analytics Report</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="hero">
    <div class="nav">
      <a href="index.php" class="pill">&larr; Back to Dashboard</a>
      <a href="report.php?format=json" class="pill">View JSON API</a>
    </div>
  </header>
  <section class="card">
    <h3>Class Performance Summary (SQLite Aggregation)</h3>
    <table>
      <thead><tr><th>Class</th><th>Students</th><th>Avg Marks</th><th>Top Score</th><th>Attendance</th></tr></thead>
      <tbody>
        <?php foreach ($stats as $r): ?>
          <tr>
            <td><b><?= htmlspecialchars($r['class_name']) ?></b></td>
            <td><?= (int)$r['student_count'] ?></td>
            <td><?= htmlspecialchars((string)$r['avg_marks']) ?>%</td>
            <td><?= (int)$r['top_marks'] ?>%</td>
            <td><?= htmlspecialchars((string)$r['avg_attendance']) ?>%</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</body>
</html>
