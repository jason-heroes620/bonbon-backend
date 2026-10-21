<?php

namespace App\Services;

use RuntimeException;

class ImageOptimizer
{
    /**
     * Produce an optimized (optionally scaled) binary copy of an image.
     *
     * @return array{binary: string, extension: string, width: int, height: int, file_size_bytes: int}
     */
    public function optimize(string $realPath, ?int $maxWidth = null, int $maxBytes = 409600, ?int $maxHeight = null): array
    {
        if (!is_file($realPath) || !is_readable($realPath)) {
            throw new RuntimeException('Unable to access uploaded image.');
        }

        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        [$srcWidth, $srcHeight] = $imageInfo;
        $mime = (string) ($imageInfo['mime'] ?? '');
        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($realPath),
            'image/png' => imagecreatefrompng($realPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($realPath) : false,
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException('Unsupported image format.');
        }

        $startScale = 1.0;
        if ($maxWidth !== null && $srcWidth > $maxWidth) {
            $startScale = min($startScale, $maxWidth / $srcWidth);
        }
        if ($maxHeight !== null && $srcHeight > $maxHeight) {
            $startScale = min($startScale, $maxHeight / $srcHeight);
        }

        $bestBinary = null;
        $bestExtension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $bestWidth = $srcWidth;
        $bestHeight = $srcHeight;
        $scaleSteps = [1, 0.9, 0.8, 0.7, 0.6, 0.5, 0.4];
        $qualitySteps = [82, 76, 70, 64, 58, 52, 46];

        foreach ($scaleSteps as $scaleStep) {
            $scale = min(1.0, $startScale * $scaleStep);
            $targetWidth = max(1, (int) round($srcWidth * $scale));
            $targetHeight = max(1, (int) round($srcHeight * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $srcWidth,
                $srcHeight,
            );

            foreach ($qualitySteps as $quality) {
                ob_start();
                if (function_exists('imagewebp')) {
                    imagewebp($canvas, null, $quality);
                    $extension = 'webp';
                } else {
                    $white = imagecreatetruecolor($targetWidth, $targetHeight);
                    $background = imagecolorallocate($white, 255, 255, 255);
                    imagefilledrectangle($white, 0, 0, $targetWidth, $targetHeight, $background);
                    imagecopy($white, $canvas, 0, 0, 0, 0, $targetWidth, $targetHeight);
                    imagejpeg($white, null, $quality);
                    imagedestroy($white);
                    $extension = 'jpg';
                }
                $binary = (string) ob_get_clean();

                if ($bestBinary === null || strlen($binary) < strlen($bestBinary)) {
                    $bestBinary = $binary;
                    $bestExtension = $extension;
                    $bestWidth = $targetWidth;
                    $bestHeight = $targetHeight;
                }

                if (strlen($binary) <= $maxBytes) {
                    imagedestroy($canvas);
                    imagedestroy($source);

                    return [
                        'binary' => $binary,
                        'extension' => $extension,
                        'width' => $targetWidth,
                        'height' => $targetHeight,
                        'file_size_bytes' => strlen($binary),
                    ];
                }
            }

            imagedestroy($canvas);
        }

        imagedestroy($source);

        return [
            'binary' => (string) $bestBinary,
            'extension' => $bestExtension,
            'width' => $bestWidth,
            'height' => $bestHeight,
            'file_size_bytes' => strlen((string) $bestBinary),
        ];
    }
}
