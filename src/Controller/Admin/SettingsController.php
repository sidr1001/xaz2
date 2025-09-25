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
        $settings = SettingsService::getAll();
        return $view->render($response, 'admin/settings.twig', [
            'settings' => $settings,
            'query_log' => $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [],
            'breadcrumbs' => [
                ['title' => 'Админка', 'url' => '/admin'],
                ['title' => 'Настройки'],
            ],
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
        SettingsService::set('sql_debug_enabled', isset($data['sql_debug_enabled']) ? '1' : '0');

        // handle uploads
        $files = $request->getUploadedFiles();
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/operator';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        if (isset($files['operator_signature']) && $files['operator_signature']->getError() === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($files['operator_signature']->getClientFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg'], true)) {
                $path = $uploadDir . '/signature.png';
                $files['operator_signature']->moveTo($path);
                SettingsService::set('operator_signature_path', str_replace(dirname(__DIR__,3).'/public', '', $path));
            }
        }
        if (isset($files['operator_stamp']) && $files['operator_stamp']->getError() === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($files['operator_stamp']->getClientFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg'], true)) {
                $path = $uploadDir . '/stamp.png';
                $files['operator_stamp']->moveTo($path);
                SettingsService::set('operator_stamp_path', str_replace(dirname(__DIR__,3).'/public', '', $path));
            }
        }
        return $response->withHeader('Location', '/admin/settings')->withStatus(302);
    }
}
