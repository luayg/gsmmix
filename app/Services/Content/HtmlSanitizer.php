<?php
namespace App\Services\Content;
final class HtmlSanitizer
{
    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return $html;
        $html=preg_replace('~<(script|style|link|iframe|object|embed|form|base|meta)[^>]*>.*?</\1\s*>~is','',$html) ?? '';
        $html=preg_replace('~<(script|style|link|iframe|object|embed|form|base|meta)[^>]*/?>~is','',$html) ?? '';
        $html=preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html) ?? '';
        $html=preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript|data:text\/html):.*?\2/i',' $1="#"',$html) ?? '';
        $html=preg_replace('/(style\s*=\s*["\'][^"\']*)(?:expression\s*\(|url\s*\(\s*["\']?\s*(?:javascript|data):)[^"\']*/i','$1',$html) ?? '';
        $html=preg_replace('/(style\s*=\s*["\'][^"\']*)(?:position|z-index|behavior|-moz-binding)\s*:[^;"\']*;?/i','$1',$html) ?? '';
        return preg_replace('/<a\b(?![^>]*\brel=)([^>]*)>/i','<a rel="noopener noreferrer"$1>',$html) ?? '';
    }
}
