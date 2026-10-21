<?php

declare(strict_types=1);

use Lubie\Container;
use Lubie\Db\Database;
use Lubie\Http\Kernel;
use Lubie\Http\Router;
use Lubie\Repositories\SettingsRepository;
use Lubie\Security\DbSessionHandler;
use Lubie\Security\Session;
use Lubie\Sms\HttpSmsGateway;
use Lubie\Sms\SandboxSmsGateway;
use Lubie\Sms\SmsGateway;
use Lubie\Support\Clock;
use Lubie\Support\Env;

/*
 * Application bootstrap shared by public/index.php, bin/console.php and tests.
 * Configuration comes from environment variables; .env is read only when present (XAMPP).
 */
$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    // Fallback PSR-4 autoloader so the app runs before `composer install` (PDF export then uses print view).
    spl_autoload_register(static function (string $class) use ($root): void {
        if (str_starts_with($class, 'Lubie\\')) {
            $file = $root . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

Env::load($root . '/.env');
date_default_timezone_set('Africa/Accra');
ini_set('display_errors', Env::get('APP_ENV') === 'local' && Env::bool('APP_DEBUG', false) ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/** Build the container for one request (or one test). */
function lubie_container(?Database $db = null, ?Clock $clock = null): Container
{
    $c = new Container($db ?? Database::fromEnv(), $clock ?? new Clock(Env::get('APP_FIXED_NOW')));
    $c->factory(SmsGateway::class, static fn () => Env::get('SMS_PROVIDER', 'sandbox') === 'arkesel' ? new HttpSmsGateway() : new SandboxSmsGateway());
    $c->factory(Session::class, static function (Container $c) {
        $settings = $c->get(SettingsRepository::class);
        return new Session($c->get(DbSessionHandler::class), $c->get(Clock::class), $settings->int('session_idle_minutes', 20), 12);
    });
    return $c;
}

function lubie_kernel(Container $c): Kernel
{
    $router = new Router();
    (require dirname(__DIR__) . '/config/routes.php')($router);
    return new Kernel($c, $router, rtrim((string) Env::get('APP_BASE_PATH', ''), '/'));
}
