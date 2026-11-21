<?php
/**
 * CampusFix - Database Connection Layer (PDO)
 * Provides resilient, prepared statement database connections and error handling.
 */

require_once __DIR__ . '/app.php';

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'campusfix');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get or initialize PDO database connection singleton
 *
 * @return PDO
 * @throws PDOException
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log real exception internally
            error_log("CampusFix DB Connection Failed: " . $e->getMessage());

            // Check if running in browser to render polite diagnostic screen
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                http_response_code(500);
                echo '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Database Connection Notice - CampusFix</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
                    <style>
                        body { background-color: #f8fafc; font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                        .error-card { max-width: 620px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); padding: 2rem; }
                        .code-box { background: #0f172a; color: #38bdf8; padding: 1rem; border-radius: 6px; font-size: 0.85rem; font-family: monospace; overflow-x: auto; }
                    </style>
                </head>
                <body>
                    <div class="error-card text-center">
                        <div class="text-warning mb-3" style="font-size: 3rem;">⚠️</div>
                        <h4 class="fw-bold text-dark mb-2">CampusFix Database Not Connected</h4>
                        <p class="text-secondary small mb-4">The application cannot communicate with MySQL on <code>localhost:3306</code>. Please verify that MySQL is started in your XAMPP Control Panel and that the database schema is imported.</p>
                        
                        <div class="text-start mb-4">
                            <h6 class="fw-semibold text-dark">Quick Setup Steps:</h6>
                            <ol class="small text-secondary ps-3 mb-3">
                                <li>Open <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong>.</li>
                                <li>Open <a href="http://localhost/phpmyadmin" target="_blank" class="fw-bold text-primary">phpMyAdmin</a> in your browser.</li>
                                <li>Import the database file located at:</li>
                            </ol>
                            <div class="code-box">database/campusfix.sql</div>
                        </div>

                        <a href="" class="btn btn-primary px-4 py-2 fw-semibold">🔄 Retry Connection</a>
                    </div>
                </body>
                </html>';
                exit;
            } else {
                throw new PDOException("Database connection error: Ensure MySQL service is running and database 'campusfix' exists. (" . $e->getMessage() . ")");
            }
        }
    }

    return $pdo;
}

