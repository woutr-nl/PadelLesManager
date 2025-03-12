<?php

namespace App\Core;

use Dotenv\Dotenv;

class Application {
    private static bool $initialized = false;
    private static array $config = [];
    
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        
        // Load environment variables
        $rootPath = dirname(__DIR__, 2); // Go up two levels from src/Core to project root
        $dotenv = Dotenv::createImmutable($rootPath);
        $dotenv->load();
        
        // Store configuration
        self::$config = [
            'db' => [
                'host' => $_ENV['DB_HOST'] ?? null,
                'name' => $_ENV['DB_NAME'] ?? null,
                'user' => $_ENV['DB_USER'] ?? null,
                'pass' => $_ENV['DB_PASS'] ?? null,
            ],
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'PadelLesManager',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => $_ENV['APP_DEBUG'] ?? false,
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
            ],
            'google' => [
                'credentials_path' => $_ENV['GOOGLE_CREDENTIALS_PATH'] ?? null,
                'calendar_id' => $_ENV['GOOGLE_CALENDAR_ID'] ?? null,
            ]
        ];
        
        // Set error reporting based on environment
        if (self::$config['app']['env'] === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
        
        self::$initialized = true;
    }
    
    public static function config(string $key, mixed $default = null): mixed {
        if (!self::$initialized) {
            self::init();
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public static function isInitialized(): bool {
        return self::$initialized;
    }
    
    public static function isDevelopment(): bool {
        return self::config('app.env') === 'development';
    }
} 