<?php
/**
 * ==============================================================================
 * Database Configuration & Connection Handler (config/db.php)
 * ------------------------------------------------------------------------------
 * Establishes a secure PDO connection to the MySQL/MariaDB database.
 * Uses prepared statement emulation disabled to enforce native server-side prepared
 * statements, and sets exception error mode for defensive error handling.
 * ==============================================================================
 */

// ------------------------------------------------------------------------------
// DATABASE CONNECTION PARAMETERS
// Change these constants according to your local environment (e.g. XAMPP/WAMP)
// ------------------------------------------------------------------------------
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'scms');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns the singleton PDO database connection instance.
 *
 * @return PDO The active PDO connection instance.
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Enforce native prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Defensive error handling: Avoid exposing raw database credentials to end users
            error_log("Database Connection Failure: " . $e->getMessage());
            
            // Check if request is an AJAX/API call or direct browser view
            $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
            
            if ($is_ajax) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Database connection failed. Please ensure the database service is running.']);
                exit;
            }

            // Display a user-friendly error screen with setup instructions
            die('
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Database Connection Error - SCMS</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                    .error-card { max-width: 600px; margin: 80px auto; border-radius: 12px; border: 1px solid #fee2e2; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="card error-card shadow-sm">
                        <div class="card-body p-4 text-center">
                            <div class="text-danger mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                                </svg>
                            </div>
                            <h4 class="card-title text-danger fw-bold">Database Connection Failed</h4>
                            <p class="text-muted mt-2">The application could not establish a connection to the MySQL database (<code>' . htmlspecialchars(DB_NAME) . '</code> on <code>' . htmlspecialchars(DB_HOST) . '</code>).</p>
                            
                            <div class="alert alert-light text-start border mt-4">
                                <h6 class="fw-bold mb-2">Troubleshooting Steps:</h6>
                                <ol class="mb-0 small text-secondary ps-3">
                                    <li>Ensure MySQL / MariaDB service is started in your XAMPP or WAMP control panel.</li>
                                    <li>Confirm the database <strong>scms</strong> exists. (Import <code>sql/schema.sql</code> and <code>sql/seed.sql</code>).</li>
                                    <li>Check database credentials in <code>config/db.php</code>.</li>
                                </ol>
                            </div>
                            
                            <button onclick="window.location.reload();" class="btn btn-primary px-4 mt-2">Retry Connection</button>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ');
        }
    }

    return $pdo;
}
