<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SettingsController
{
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/settings.twig', [
            'settings' => SettingsService::getAll(),
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $keys = ['site_name','site_desc','email','phone','currency','payment_methods','card_image_width','card_image_height'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                SettingsService::set($key, (string)$data[$key]);
            }
        }
        // toggles
        SettingsService::set('bus_seat_selection_enabled', isset($data['bus_seat_selection_enabled']) ? '1' : '0');
        return $response->withHeader('Location', '/admin/settings')->withStatus(302);
    }
}
