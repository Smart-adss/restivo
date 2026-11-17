<?php

namespace App\Domain\Menu\Support;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageProcessor
{
    public const MAX_BYTES = 4_194_304;

    public const MAX_EDGE = 1600;

    public const JPEG_QUALITY = 82;

    /** @var list<string> */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function store(UploadedFile $file): string
    {
        if ($file->getSize() !== false && $file->getSize() > self::MAX_BYTES) {
            throw new InvalidProductImage('The image exceeds the 4MB limit.');
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $mime = $this->detectMime($path);

        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw new InvalidProductImage;
        }

        $source = $this->createImage($path, $mime);

        if ($source === false) {
            throw new InvalidProductImage;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);
        $scale = $longest > self::MAX_EDGE ? self::MAX_EDGE / $longest : 1.0;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
        $relative = 'tenants/'.$tenant->public_id.'/products/'.Str::ulid().'.jpg';
        $absolute = Storage::disk('local')->path($relative);
        Storage::disk('local')->makeDirectory(dirname($relative));

        if (! imagejpeg($canvas, $absolute, self::JPEG_QUALITY)) {
            imagedestroy($canvas);
            throw new InvalidProductImage('Failed to encode the image.');
        }

        imagedestroy($canvas);

        $encoded = @getimagesize($absolute);
        if ($encoded === false || max($encoded[0], $encoded[1]) > self::MAX_EDGE) {
            @unlink($absolute);
            throw new InvalidProductImage('Encoded image exceeds the allowed dimensions.');
        }

        return $relative;
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : '';
    }

    /**
     * @return \GdImage|false
     */
    private function createImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }
}
