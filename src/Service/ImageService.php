<?php
declare(strict_types=1);

namespace App\Service;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

final class ImageService
{
    public static function processUpload(\Psr\Http\Message\UploadedFileInterface $file, int $maxWidth = 1920, int $thumbWidth = 480, bool $toWebp = false): array
    {
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/tours';
        $thumbDir = $uploadDir . '/thumbs';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION) ?: 'jpg';
        $basename = bin2hex(random_bytes(10));
        $filename = $basename . '.' . strtolower($extension);
        $path = $uploadDir . '/' . $filename;
        $file->moveTo($path);

        $manager = new ImageManager(new Driver());
        $image = $manager->read($path);
        if ($image->width() > $maxWidth) {
            $image = $image->scale($maxWidth / $image->width());
        }
        $image->save($path);

        $thumb = $manager->read($path)->cover($thumbWidth, (int)round($thumbWidth * 0.66));
        $thumbPath = $thumbDir . '/' . $filename;
        $thumb->save($thumbPath);

        if ($toWebp) {
            $webp = $uploadDir . '/' . $basename . '.webp';
            $image->toWebp(85)->save($webp);
        }

        return [
            'path' => '/uploads/tours/' . $filename,
            'thumb' => '/uploads/tours/thumbs/' . $filename,
        ];
    }
}

