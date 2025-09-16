<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Service\SettingsService;
use App\Service\Csrf;

require __DIR__ . '/../vendor/autoload.php';

// Start session for auth
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Load environment
\Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/config', '.env')->safeLoad();

// Create App
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Twig view
$twig = Twig::create(dirname(__DIR__) . '/templates', ['cache' => false]);
$twig->getEnvironment()->addGlobal('settings', SettingsService::getAll());
$twig->getEnvironment()->addGlobal('csrf', Csrf::token());
$app->add(TwigMiddleware::create($app, $twig));

// Error middleware (show detailed in dev)
$displayErrorDetails = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

// Register routes
require dirname(__DIR__) . '/src/routes.php';

// Run app
$app->run();

