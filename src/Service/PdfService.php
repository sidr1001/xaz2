<?php
declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Slim\Views\Twig;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PdfService
{
    public static function renderTwigToPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string)$dompdf->output();
    }

    public static function renderTemplateToFile(Request $request, string $template, array $data, string $filepath): void
    {
        $view = Twig::fromRequest($request);
        $html = $view->fetch($template, $data);
        $pdf = self::renderTwigToPdf($html);
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($filepath, $pdf);
    }
}

