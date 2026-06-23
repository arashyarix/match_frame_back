<?php

namespace App\Support\Ai;

/**
 * Shrinks photos before they're sent to a vision model. Full-size phone photos
 * are several MB each; base64-encoding a dozen of them blows past Anthropic's
 * request-size limit (413) and times out OpenAI uploads. Downscaling to a
 * ~1024px long edge as JPEG keeps quality high for judging a photo while cutting
 * each image to a few hundred KB.
 *
 * Uses the GD extension. If GD is unavailable it returns the original bytes.
 */
class ImagePrep
{
    public static function downscale(string $bytes, string $fallbackMime = 'image/jpeg', int $maxEdge = 1024, int $quality = 80): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['mime' => $fallbackMime, 'data' => base64_encode($bytes)];
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return ['mime' => $fallbackMime, 'data' => base64_encode($bytes)];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $maxEdge / max(1, max($w, $h)));

        if ($scale < 1.0) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            // Flatten any transparency onto white since we output JPEG.
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        imagejpeg($src, null, $quality);
        $out = ob_get_clean();
        imagedestroy($src);

        if ($out === false || $out === '') {
            return ['mime' => $fallbackMime, 'data' => base64_encode($bytes)];
        }

        return ['mime' => 'image/jpeg', 'data' => base64_encode($out)];
    }
}
