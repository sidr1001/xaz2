<?php
declare(strict_types=1);

use App\Middleware\AdminAuthMiddleware;
use App\Middleware\AgentAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controller\HomeController;
use App\Controller\TourController;
use App\Controller\BookingController;
use App\Controller\Admin\AuthController as AdminAuthController;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\ToursController;
use App\Controller\Admin\AgentsController;
use App\Controller\Admin\TouristsController as AdminTouristsController;
use App\Controller\Admin\BookingsController as AdminBookingsController;
use App\Controller\Admin\PaymentsController as AdminPaymentsController;
use App\Controller\Admin\SettingsController as AdminSettingsController;
use App\Controller\Agent\AuthController as AgentAuthController;
use App\Controller\Agent\DashboardController as AgentDashboardController;
use App\Controller\Agent\BookingsController as AgentBookingsController;
use App\Controller\Agent\ProfileController as AgentProfileController;
use App\Controller\Agent\PaymentsController as AgentPaymentsController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Controller\PaymentWebhookController;

/** @var App $app */
$app = $app ?? null;

// Public routes
$app->get('/', [HomeController::class, 'index']);
$app->get('/tour/{id}', [TourController::class, 'view']);
$app->get('/tour/{id}/booking', [BookingController::class, 'create']);
$app->post('/booking', [BookingController::class, 'store'])->add(new CsrfMiddleware());

// Payment callbacks
$app->post('/payment/tbank/callback', [PaymentWebhookController::class, 'tbank']);

// Admin auth
$app->map(['GET'], '/admin/login', [AdminAuthController::class, 'loginForm']);
$app->map(['POST'], '/admin/login', [AdminAuthController::class, 'login']);
$app->get('/admin/logout', [AdminAuthController::class, 'logout']);

// Protected admin routes
$app->group('/admin', function (RouteCollectorProxy $group) {
    $group->get('', [DashboardController::class, 'index']);

    // Tours CRUD
    $group->get('/tours', [ToursController::class, 'index']);
    $group->get('/tours/create', [ToursController::class, 'create']);
    $group->post('/tours/store', [ToursController::class, 'store']);
    $group->get('/tours/{id}/edit', [ToursController::class, 'edit']);
    $group->post('/tours/{id}/update', [ToursController::class, 'update']);
    $group->post('/tours/{id}/delete', [ToursController::class, 'delete']);

    // Agents management
    $group->get('/agents', [AgentsController::class, 'index']);
    $group->get('/agents/create', [AgentsController::class, 'create']);
    $group->post('/agents/store', [AgentsController::class, 'store']);
    $group->get('/agents/{id}/edit', [AgentsController::class, 'edit']);
    $group->post('/agents/{id}/update', [AgentsController::class, 'update']);
    $group->post('/agents/{id}/delete', [AgentsController::class, 'delete']);
    $group->post('/agents/{id}/contract/upload', [AgentsController::class, 'contractUpload']);
    $group->post('/agents/{id}/contract/mode', [AgentsController::class, 'contractMode']);

    // Tourists list
    $group->get('/tourists', [AdminTouristsController::class, 'index']);
    $group->get('/tourists/{id}/edit', [AdminTouristsController::class, 'edit']);
    $group->post('/tourists/{id}/update', [AdminTouristsController::class, 'update']);
    $group->post('/tourists/{id}/upload', [AdminTouristsController::class, 'upload']);
    $group->post('/tourists/{id}/file/delete', [AdminTouristsController::class, 'deleteFile']);
    // Bookings
    $group->get('/bookings', [AdminBookingsController::class, 'index']);
    $group->get('/bookings/{id}', [AdminBookingsController::class, 'view']);
    $group->post('/bookings/{id}/payment-status', [AdminBookingsController::class, 'paymentStatus']);
    $group->post('/bookings/{id}/order-status', [AdminBookingsController::class, 'orderStatus']);
    $group->post('/bookings/{id}/documents', [AdminBookingsController::class, 'generateDocuments']);

    // Payments
    $group->get('/bookings/{booking_id}/payment/invoice', [AdminPaymentsController::class, 'invoice']);
    $group->get('/bookings/{booking_id}/payment/online', [AdminPaymentsController::class, 'online']);

    // Settings
    $group->get('/settings', [AdminSettingsController::class, 'index']);
    $group->post('/settings', [AdminSettingsController::class, 'save']);
})->add(new AdminAuthMiddleware());

// Agent auth
$app->map(['GET'], '/agent/login', [AgentAuthController::class, 'loginForm']);
$app->map(['POST'], '/agent/login', [AgentAuthController::class, 'login']);
$app->get('/agent/logout', [AgentAuthController::class, 'logout']);

// Agent area
$app->group('/agent', function (RouteCollectorProxy $group) {
    $group->get('', [AgentDashboardController::class, 'index']);
    $group->get('/bookings', [AgentBookingsController::class, 'index']);
    $group->get('/bookings/{id}', [AgentBookingsController::class, 'view']);
    $group->post('/bookings/{id}/documents', [AgentBookingsController::class, 'generateDocuments']);
    $group->get('/tourists', [\App\Controller\Agent\TouristsController::class, 'index']);
    $group->get('/tourists/{id}/edit', [\App\Controller\Agent\TouristsController::class, 'edit']);
    $group->post('/tourists/{id}/update', [\App\Controller\Agent\TouristsController::class, 'update']);
    $group->post('/tourists/{id}/upload', [\App\Controller\Agent\TouristsController::class, 'upload']);
    $group->post('/tourists/{id}/file/delete', [\App\Controller\Agent\TouristsController::class, 'deleteFile']);
    $group->get('/tours/{tour_id}/book', [AgentBookingsController::class, 'create']);
    $group->post('/bookings/store', [AgentBookingsController::class, 'store']);
    $group->get('/tours/{tour_id}/pricing', [\App\Controller\Agent\PricingController::class, 'form']);
    $group->post('/tours/{tour_id}/pricing', [\App\Controller\Agent\PricingController::class, 'save']);
    $group->get('/bookings/{booking_id}/payment/invoice', [AgentPaymentsController::class, 'invoice']);
    $group->get('/bookings/{booking_id}/payment/online', [AgentPaymentsController::class, 'online']);
    $group->get('/api/tourists/search', [\App\Controller\Agent\TouristsController::class, 'search']);
    $group->get('/profile', [AgentProfileController::class, 'form']);
    $group->post('/profile', [AgentProfileController::class, 'save']);
    $group->get('/profile/contract', [AgentProfileController::class, 'contract']);
    $group->post('/profile/upload/signature', [AgentProfileController::class, 'uploadSignature']);
    $group->post('/profile/upload/stamp', [AgentProfileController::class, 'uploadStamp']);
})->add(new AgentAuthMiddleware());

// 404 handler
$app->any('/{routes:.+}', function ($request, $response) {
    $view = \Slim\Views\Twig::fromRequest($request);
    return $view->render($response->withStatus(404), '404.twig');
});

