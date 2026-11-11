<?php
/**
 * Database connection settings.
 * If your MySQL username/password is different, change it below.
 * Default XAMPP settings are usually: user = root, password = "" (blank)
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'hardware_shop');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("
        <div style='font-family:Arial;max-width:600px;margin:60px auto;padding:24px;
             border:1px solid #f3c2c2;background:#fff5f5;border-radius:10px;color:#7a1f1f'>
            <h2>Database Connection Failed</h2>
            <p>The application could not connect to MySQL. Please check:</p>
            <ul>
                <li>Is XAMPP / MySQL running?</li>
                <li>Is the database <b>hardware_shop</b> imported? (see README.md)</li>
                <li>Are the DB_USER / DB_PASS correct in <code>config/db.php</code>?</li>
            </ul>
            <p style='color:#999'>Technical detail: " . htmlspecialchars($e->getMessage()) . "</p>
        </div>
    ");
}
