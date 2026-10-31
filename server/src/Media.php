<?php
declare(strict_types=1);

namespace Stillroom;

/**
 * Uploaded audio and images. Files go to <public>/uploads/YYYY/MM/<random>.<ext> and are served directly by the web server
 * (fast, supports Range requests for audio seeking). The uploads folder has its own .htaccess that disables script execution.
 * Checks: upload error, size limit, extension whitelist, MIME via finfo AND magic bytes. Images are re-encoded with GD
 * (strips metadata and anything hidden in the file) and resized.
 */
final class Media
{
    public const AUDIO = [
        // ext => [allowed finfo mimes, served mime]
        'mp3' => [['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'application/octet-stream'], 'audio/mpeg'],
        'm4a' => [['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'audio/aac'], 'audio/mp4'],
        'aac' => [['audio/aac', 'audio/x-aac', 'audio/aacp', 'application/octet-stream'], 'audio/aac'],
        'ogg' => [['audio/ogg', 'application/ogg', 'audio/opus'], 'audio/ogg'],
        'opus' => [['audio/ogg', 'application/ogg', 'audio/opus'], 'audio/ogg'],
        'wav' => [['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'], 'audio/wav'],
        'flac' => [['audio/flac', 'audio/x-flac'], 'audio/flac'],
        'webm' => [['audio/webm', 'video/webm'], 'audio/webm'],
    ];
    public const IMAGE = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function publicDir(): string
    {
        $env = Env::get('PUBLIC_DIR');
        if ($env) {
            return $env[0] === '/' ? rtrim($env, '/') : APP_ROOT . '/' . rtrim($env, '/');
        }
        return is_dir(APP_ROOT . '/public_html') ? APP_ROOT . '/public_html' : APP_ROOT . '/public';
    }

    public static function uploadDir(): string
    {
        return self::publicDir() . '/uploads';
    }

    public static function maxAudioBytes(): int
    {
        return Env::int('MAX_AUDIO_MB', 60) * 1024 * 1024;
    }

    public static function maxImageBytes(): int
    {
        return Env::int('MAX_IMAGE_MB', 8) * 1024 * 1024;
    }

    public static function url(?string $path): ?string
    {
        return $path ? Env::appUrl() . '/uploads/' . $path : null;
    }

    public static function find(?int $id): ?array
    {
        return $id ? Db::one('SELECT * FROM media WHERE id = ?', [$id]) : null;
    }

    /**
     * @param array|null $file one entry of $_FILES
     * @return array{id:int}|array{error:string}|null null when no file was chosen
     */
    public static function store(?array $file, string $kind, ?int $userId, ?int $durationMs = null): ?array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['error' => 'The file is larger than the server allows (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').'];
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['error' => 'The upload did not finish. Try again.'];
        }
        $tmp = $file['tmp_name'];
        $size = (int) filesize($tmp);
        $name = (string) ($file['name'] ?? 'file');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';

        if ($kind === 'audio') {
            if (!isset(self::AUDIO[$ext])) {
                return ['error' => 'Audio must be MP3, M4A, AAC, OGG/Opus, WAV, FLAC or WebM.'];
            }
            if ($size > self::maxAudioBytes()) {
                return ['error' => 'Audio is ' . round($size / 1048576, 1) . ' MB; the limit is ' . Env::int('MAX_AUDIO_MB', 60) . ' MB.'];
            }
            [$allowed, $served] = self::AUDIO[$ext];
            if (!in_array($mime, $allowed, true) || !self::audioMagicOk($tmp, $ext)) {
                return ['error' => 'This file does not look like a real ' . strtoupper($ext) . ' file (' . $mime . ').'];
            }
            $rel = self::target($ext);
            if (!self::moveUploaded($tmp, $rel)) {
                return ['error' => 'Could not save the file. Check that uploads/ is writable.'];
            }
            $id = Db::insert('media', [
                'kind' => 'audio', 'path' => $rel, 'original_name' => mb_substr(basename($name), 0, 180), 'mime' => $served,
                'size' => $size, 'duration' => $durationMs && $durationMs > 0 && $durationMs < 86400000 ? $durationMs : null,
                'created_by' => $userId, 'created_at' => now(),
            ]);
            return ['id' => $id];
        }

        // image
        if (!in_array($ext, self::IMAGE, true)) {
            return ['error' => 'Images must be JPG, PNG, WebP or GIF.'];
        }
        if ($size > self::maxImageBytes()) {
            return ['error' => 'Image is ' . round($size / 1048576, 1) . ' MB; the limit is ' . Env::int('MAX_IMAGE_MB', 8) . ' MB.'];
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return ['error' => 'This file is not a JPG, PNG, WebP or GIF image (' . $mime . ').'];
        }
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 16 || $info[1] < 16 || $info[0] * $info[1] > 40_000_000) {
            return ['error' => 'The image could not be read or is too large in pixels.'];
        }
        $src = @imagecreatefromstring((string) file_get_contents($tmp));
        if (!$src) {
            return ['error' => 'The image could not be decoded.'];
        }
        $max = $kind === 'logo' ? 800 : 1600;
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $rel = self::target('webp');
        $abs = self::uploadDir() . '/' . $rel;
        @mkdir(dirname($abs), 0755, true);
        $ok = imagewebp($dst, $abs, 84);
        // small thumbnail for lists
        $tw = min(480, $nw);
        $th = max(1, (int) round($nh * $tw / $nw));
        $thumb = imagecreatetruecolor($tw, $th);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagewebp($thumb, preg_replace('/\.webp$/', '.thumb.webp', $abs), 80);
        imagedestroy($src);
        imagedestroy($dst);
        imagedestroy($thumb);
        if (!$ok) {
            return ['error' => 'Could not save the image.'];
        }
        $id = Db::insert('media', [
            'kind' => $kind === 'logo' ? 'logo' : 'image', 'path' => $rel, 'original_name' => mb_substr(basename($name), 0, 180),
            'mime' => 'image/webp', 'size' => (int) filesize($abs), 'width' => $nw, 'height' => $nh,
            'created_by' => $userId, 'created_at' => now(),
        ]);
        return ['id' => $id];
    }

    public static function thumbUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        $thumb = preg_replace('/\.webp$/', '.thumb.webp', $path);
        return is_file(self::uploadDir() . '/' . $thumb) ? self::url($thumb) : self::url($path);
    }

    private static function target(string $ext): string
    {
        return gmdate('Y/m') . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    }

    private static function moveUploaded(string $tmp, string $rel): bool
    {
        $abs = self::uploadDir() . '/' . $rel;
        if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0755, true)) {
            return false;
        }
        if (!move_uploaded_file($tmp, $abs)) {
            return false;
        }
        @chmod($abs, 0644);
        return true;
    }

    private static function audioMagicOk(string $file, string $ext): bool
    {
        $h = (string) file_get_contents($file, false, null, 0, 64);
        return match ($ext) {
            'mp3' => str_starts_with($h, 'ID3') || (strlen($h) > 1 && ord($h[0]) === 0xFF && (ord($h[1]) & 0xE0) === 0xE0),
            'aac' => strlen($h) > 1 && ord($h[0]) === 0xFF && (ord($h[1]) & 0xF0) === 0xF0,
            'm4a' => substr($h, 4, 4) === 'ftyp',
            'ogg', 'opus' => str_starts_with($h, 'OggS'),
            'wav' => str_starts_with($h, 'RIFF') && substr($h, 8, 4) === 'WAVE',
            'flac' => str_starts_with($h, 'fLaC'),
            'webm' => str_starts_with($h, "\x1A\x45\xDF\xA3"),
            default => false,
        };
    }
}
