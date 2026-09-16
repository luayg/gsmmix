<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeOrderFile implements ValidationRule
{
    private const BLOCKED = ['php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','rb','sh','bash','exe','com','bat','cmd','msi','dll','js','mjs','html','htm','shtml','svg','xml','xhtml','htaccess','ini','env'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) { $fail('Upload a valid file.'); return; }
        $name = strtolower($value->getClientOriginalName());
        $parts = array_filter(explode('.', str_replace("\0", '', $name)));
        $mime = strtolower((string) $value->getMimeType());
        if (array_intersect($parts, self::BLOCKED) || str_contains($name, '..')
            || in_array($mime, ['text/x-php','application/x-httpd-php','application/x-executable','application/x-sh','text/html','image/svg+xml'], true)) {
            $fail('Executable, script or browser-active files are not allowed.');
        }
    }
}
