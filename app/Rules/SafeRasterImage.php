<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeRasterImage implements ValidationRule
{
    private const MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const EXT = ['jpg', 'jpeg', 'png', 'webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) { $fail('Upload a valid image.'); return; }
        $extension = strtolower($value->getClientOriginalExtension());
        $mime = strtolower((string) $value->getMimeType());
        $image = @getimagesize($value->getRealPath());
        if (!in_array($extension, self::EXT, true) || !in_array($mime, self::MIME, true) || $image === false
            || !in_array(strtolower((string) ($image['mime'] ?? '')), self::MIME, true)) {
            $fail('Only genuine JPG, PNG or WEBP images are allowed.');
        }
    }
}
