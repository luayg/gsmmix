<?php

namespace App\Support;

final class ServiceDescriptionPresenter
{
    private const LABELS = [
        'Model Description', 'Full Model Description', 'IMEI Number', 'IMEI2 Number', 'Serial Number',
        'Warranty Status', 'Activation Status', 'Estimated Purchase Date', 'Purchase Date', 'Purchase Country',
        'Telephone Technical Support', 'Repairs and Service Coverage', 'Find My iPhone', 'iCloud Status',
        'US Block Status', 'SIM-Lock Status', 'Next Activation Policy ID', 'Replaced by Apple',
        'Replaced Status', 'Replacement Status', 'Refurbished Status', 'Demo Status', 'Loaner Device',
        'Blacklist Status', 'Network', 'Carrier', 'Country', 'iOS Version', 'Model', 'Sample',
    ];

    public static function format(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') return '';

        // Properly authored rich descriptions already have their own vertical structure.
        if (preg_match('/<(?:p|div|br|ul|ol|li|table|blockquote|h[1-6])\b/i', $html)) return $html;

        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($text === '') return '';

        $pattern = implode('|', array_map(fn (string $label) => preg_quote($label, '/'), self::LABELS));
        $text = preg_replace('/\s*('.$pattern.')\s*:\s*/iu', "\n$1: ", $text) ?? $text;
        $text = preg_replace('/\s*(Other data points include|Our data points include|Data points include)\s*:\s*/iu', "\n$1: ", $text) ?? $text;
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $text) ?: [])));

        if (count($lines) < 2) {
            return '<p class="service-description-intro">'.e($text).'</p>';
        }

        $out = '<div class="service-description-stack">';
        foreach ($lines as $index => $line) {
            if (preg_match('/^([^:]{2,55}):\s*(.*)$/u', $line, $match)) {
                $label = trim($match[1]);
                $value = trim($match[2]);
                $out .= '<div class="service-description-row"><span>'.e($label).'</span><div>'.e($value).'</div></div>';
            } else {
                $out .= '<p class="service-description-intro'.($index === 0 ? ' is-lead' : '').'">'.e($line).'</p>';
            }
        }
        return $out.'</div>';
    }
}
