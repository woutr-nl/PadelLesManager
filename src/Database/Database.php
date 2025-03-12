<?php

namespace App\Database;

use PDO;
use PDOException;
use App\Core\Application;

class Database {
    private static ?PDO $instance = null;
    
    private function __construct() {}
    
    /**
     * Checks database configuration and returns diagnostic information
     * @return array Diagnostic information about the database configuration
     */
    public static function checkConfiguration(): array {
        $diagnostics = [
            'config_status' => [],
            'connection_info' => [],
            'potential_issues' => [],
            'recommendations' => []
        ];

        // Check configuration using Application class
        $host = Application::config('db.host');
        $name = Application::config('db.name');
        $user = Application::config('db.user');
        $pass = Application::config('db.pass');

        // Check basic configuration
        $diagnostics['config_status'] = [
            'host_configured' => !empty($host),
            'database_configured' => !empty($name),
            'username_configured' => !empty($user),
            'password_configured' => !empty($pass)
        ];

        if ($host) {
            // Parse host information
            $port = 3306;
            if (strpos($host, ':') !== false) {
                list($host, $port) = explode(':', $host);
            }

            $diagnostics['connection_info'] = [
                'host' => $host,
                'port' => $port,
                'database' => $name,
                'username' => $user,
                'is_local' => in_array($host, ['localhost', '127.0.0.1', '::1']),
                'is_ip' => filter_var($host, FILTER_VALIDATE_IP) !== false
            ];

            // Check for potential issues
            if ($diagnostics['connection_info']['is_local'] && !in_array($host, ['127.0.0.1', '::1'])) {
                $diagnostics['potential_issues'][] = "'localhost' might resolve to a Unix socket instead of TCP/IP";
                $diagnostics['recommendations'][] = "Use '127.0.0.1' instead of 'localhost' for TCP/IP connections";
            }

            if (!$diagnostics['connection_info']['is_local']) {
                $diagnostics['potential_issues'][] = "External database detected - additional security measures may be needed";
                $diagnostics['recommendations'][] = "Ensure your IP is whitelisted in the database firewall";
                $diagnostics['recommendations'][] = "Check if the database user has permission to connect from your IP";
                $diagnostics['recommendations'][] = "Verify that port {$port} is open on the database server";
            }

            if ($port != 3306) {
                $diagnostics['potential_issues'][] = "Non-standard MySQL port detected";
                $diagnostics['recommendations'][] = "Verify that this port is intentional and open";
            }
        } else {
            $diagnostics['potential_issues'][] = "Database host not configured";
            $diagnostics['recommendations'][] = "Check DB_HOST in your .env file";
        }

        // Check for missing configuration
        foreach ($diagnostics['config_status'] as $key => $status) {
            if (!$status) {
                $envKey = match($key) {
                    'host_configured' => 'DB_HOST',
                    'database_configured' => 'DB_NAME',
                    'username_configured' => 'DB_USER',
                    'password_configured' => 'DB_PASS'
                };
                $diagnostics['potential_issues'][] = "{$envKey} is not configured";
                $diagnostics['recommendations'][] = "Check {$envKey} in your .env file";
            }
        }

        return $diagnostics;
    }

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                // Get configuration from Application class
                $host = Application::config('db.host');
                $name = Application::config('db.name');
                $user = Application::config('db.user');
                $pass = Application::config('db.pass');

                if (!$host || !$name || !$user) {
                    $diagnostics = self::checkConfiguration();
                    throw new PDOException(
                        "Database configuration is incomplete. Issues found:\n" .
                        implode("\n", $diagnostics['potential_issues']) . "\n\n" .
                        "Recommendations:\n" .
                        implode("\n", $diagnostics['recommendations'])
                    );
                }

                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=utf8mb4",
                    $host,
                    $name
                );
                
                // Add port if not default
                if (strpos($host, ':') === false) {
                    $dsn .= ';port=3306';  // Default MySQL port
                }
                
                self::$instance = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 5  // Add connection timeout
                    ]
                );
            } catch (PDOException $e) {
                // Add more context to the error message
                $message = 'Database connection failed: ' . $e->getMessage();
                $diagnostics = self::checkConfiguration();
                
                if (!empty($diagnostics['potential_issues'])) {
                    $message .= "\n\nPotential issues found:\n" . implode("\n", $diagnostics['potential_issues']);
                }
                if (!empty($diagnostics['recommendations'])) {
                    $message .= "\n\nRecommendations:\n" . implode("\n", $diagnostics['recommendations']);
                }
                
                throw new PDOException($message, (int)$e->getCode());
            }
        }
        
        return self::$instance;
    }
    
    public static function query(string $query, array $params = []): \PDOStatement {
        try {
            error_log("Executing query: " . $query);
            error_log("Query parameters: " . json_encode($params));
            
            $stmt = self::getInstance()->prepare($query);
            $stmt->execute($params);
            
            error_log("Query execution complete. Affected rows: " . $stmt->rowCount());
            
            return $stmt;
        } catch (\PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " - Query: " . $query);
            throw $e;
        }
    }
} 