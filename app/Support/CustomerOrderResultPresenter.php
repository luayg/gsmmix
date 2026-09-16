<?php

namespace App\Support;

final class CustomerOrderResultPresenter
{
    public static function present(mixed $response): array
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                $text = trim($response);
                $items = self::parseItems($text);
                return ['image' => null, 'items' => $items, 'text' => $items === [] ? $text : null];
            }
            $response = $decoded;
        }
        if (!is_array($response)) return ['image' => null, 'items' => [], 'text' => null];
        $image = self::safeImage($response['result_image'] ?? null);
        $items = collect($response['result_items'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => [
            'label' => trim((string) ($item['label'] ?? '')), 'value' => self::stringValue($item['value'] ?? ''),
        ])->filter(fn ($item) => $item['label'] !== '' || $item['value'] !== '')->values()->all();
        $text = null;
        foreach (['result_text','result','code','codes','reply','answer','response'] as $key) {
            $value = data_get($response, $key);
            if (is_scalar($value) && trim((string) $value) !== '') { $text = trim((string) $value); break; }
            if (is_array($value) && $value !== []) { $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); break; }
        }
        if ($items === [] && $text) $items = self::parseItems($text);
        return ['image' => $image, 'items' => $items, 'text' => $items === [] ? $text : null];
    }

    private static function parseItems(string $text): array
    {
        $labels = ['Estimated Purchase Date','Telephone Technical Support','Repairs and Service Coverage','Next Activation Policy ID','SIM-Lock Status','Find My iPhone','iCloud Status','Warranty Status','Activation Status','Replaced by Apple','IMEI2 Number','IMEI Number','Serial Number','Model','Network','Carrier','Country','Blacklist Status','Purchase Date','Coverage Status'];
        $labelPattern = implode('|', array_map(fn ($label) => preg_quote($label, '/'), $labels));
        if ($text === '' || !preg_match_all('/(?:^|\s)('.$labelPattern.'):\s*/iu', $text, $matches, PREG_OFFSET_CAPTURE)) return [];
        $items = [];
        foreach ($matches[0] as $index => $match) {
            $label = trim($matches[1][$index][0]);
            $start = $match[1] + strlen($match[0]);
            $end = $matches[0][$index + 1][1] ?? strlen($text);
            $value = trim(substr($text, $start, $end - $start));
            if ($label !== '' && $value !== '') $items[] = ['label' => $label, 'value' => $value];
        }
        return count($items) >= 2 ? $items : [];
    }

    private static function safeImage(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        return preg_match('#^(https?://|data:image/(?:png|jpe?g|webp);base64,)#i', $value) ? $value : null;
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) || $value === null ? trim((string) $value) : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
