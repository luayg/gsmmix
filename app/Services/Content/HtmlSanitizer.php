<?php
namespace App\Services\Content;
final class HtmlSanitizer
{
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return $html;
        $html=preg_replace('~<(script|iframe|object|embed|form|base|meta)[^>]*>.*?</\1\s*>~is','',$html) ?? '';
        $html=preg_replace('~<(script|iframe|object|embed|form|base|meta)[^>]*/?>~is','',$html) ?? '';
        $html=preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html) ?? '';
        return preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(?:javascript|data:text\/html):.*?\2/i',' $1="#"',$html) ?? '';
    }
}
