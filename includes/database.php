<?php
/**
 * Database Connection using PDO
 */

require_once __DIR__ . '/../config/config.php';

function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // If connection fails, display a user-friendly error in dev/prod
            error_log('Database Connection Error: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#1e293b;">
                <h2 style="color:#e11d48;margin-top:0;">Database Connection Error</h2>
                <p>Could not connect to the database. Please ensure MySQL is running in XAMPP or configured correctly in <code>config/config.php</code>.</p>
                <p><small style="color:#64748b;">Error details: ' . htmlspecialchars($e->getMessage()) . '</small></p>
            </div>');
        }
    }
    
    return $pdo;
}
