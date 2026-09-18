<?php
namespace App\Services\Content;
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p','br','strong','b','em','i','u','ul','ol','li','h1','h2','h3','h4','h5','h6',
        'a','table','thead','tbody','tr','th','td','img','div','span','small','sup','sub',
        'blockquote','pre','code','hr',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href','title','target','rel'],
        'img' => ['src','alt','title','width','height'],
        'th' => ['colspan','rowspan'],
        'td' => ['colspan','rowspan'],
    ];

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return $html;
        if (!class_exists(\DOMDocument::class)) return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><a><table><thead><tbody><tr><th><td><img><div><span><small><sup><sub><blockquote><pre><code><hr>');

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="sanitized-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('sanitized-root');
        if (!$root) return '';
        $this->cleanNode($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    private function cleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $removeContents = in_array($tag, ['script','style','iframe','object','embed','form','base','meta','link'], true);
                if (!$removeContents) while ($child->firstChild) $child->parentNode?->insertBefore($child->firstChild, $child);
                $child->parentNode?->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attribute) {
                if (!in_array(strtolower($attribute->name), self::ALLOWED_ATTRIBUTES[$tag] ?? [], true)) $child->removeAttributeNode($attribute);
            }
            foreach (['href','src'] as $attribute) {
                if ($child->hasAttribute($attribute) && !$this->safeUrl($child->getAttribute($attribute), $tag === 'img')) $child->removeAttribute($attribute);
            }
            if ($tag === 'a') $child->setAttribute('rel', 'noopener noreferrer');
            $this->cleanNode($child);
        }
    }

    private function safeUrl(string $url, bool $image): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) return true;
        if ($image && preg_match('~^data:image/(?:png|gif|jpe?g|webp);base64,~i', $url)) return true;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http','https','mailto','tel'], true);
    }
}
