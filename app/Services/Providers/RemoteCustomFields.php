<?php

namespace App\Services\Providers;

use Illuminate\Support\Str;

class RemoteCustomFields
{
    private function mapRemoteFieldType($t): string
    {
        $x = strtolower(trim((string)$t));
        if (in_array($x, ['dropdown', 'select'], true)) return 'select';
        if (in_array($x, ['textarea', 'text_area'], true)) return 'textarea';
        if ($x === 'password') return 'password';
        if ($x === 'email') return 'email';
        if (in_array($x, ['number', 'numeric', 'int', 'integer'], true)) return 'number';

        return 'text';
    }

    public function extractRemoteAdditionalFields($r): array
    {
        $primary = $this->normalizeAdditionalFieldsPayload($r->additional_fields ?? null);
        if (!empty($primary)) {
            return $primary;
        }

        $ad = $r->additional_data ?? null;
        if (is_string($ad)) {
            $ad = json_decode($ad, true);
        }

        if (!is_array($ad)) {
            return [];
        }

        $candidates = [
            $ad['Requires.Custom'] ?? null,
            $ad['CustomFields'] ?? null,
            $ad['custom_fields'] ?? null,
            $ad['additional_fields'] ?? null,
            $ad['CUSTOM']['fields'] ?? null,
            $ad['CUSTOM']['FIELDS'] ?? null,
            $ad['CUSTOM'] ?? null,
            $ad['custom']['fields'] ?? null,
            $ad['custom']['FIELDS'] ?? null,
            $ad['custom'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeAdditionalFieldsPayload($candidate);
            if (!empty($normalized)) {
                return $normalized;
            }
        }

        return [];
    }

    private function normalizeAdditionalFieldsPayload($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw) || $raw === []) {
            return [];
        }

        if ($this->isAssociativeArray($raw)) {
            $isDirectFieldShape = array_key_exists('fieldname', $raw)
                || array_key_exists('name', $raw)
                || array_key_exists('label', $raw);

            if ($isDirectFieldShape) {
                $label = trim((string)($raw['fieldname'] ?? $raw['name'] ?? $raw['label'] ?? ''));
                return $label !== '' ? [$raw] : [];
            }
        }

        $flattened = [];
        foreach ($raw as $value) {
            if (is_array($value)) {
                $sub = $this->normalizeAdditionalFieldsPayload($value);
                if (!empty($sub)) {
                    $flattened = array_merge($flattened, $sub);
                }
            }
        }

        $out = [];
        foreach (($flattened ?: $raw) as $item) {
            if (!is_array($item) || !$this->isAssociativeArray($item)) {
                continue;
            }
            $label = trim((string)($item['fieldname'] ?? $item['name'] ?? $item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    private function isAssociativeArray(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function normalizeRemoteFieldsToLocal(array $remoteFields): array
    {
        $out = [];
        $seen = [];
        $maxFields = 20;

        foreach ($remoteFields as $rf) {
            if (!is_array($rf) || !$this->isAssociativeArray($rf)) {
                continue;
            }

            $label = trim((string)($rf['fieldname'] ?? $rf['name'] ?? $rf['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $providedInput = trim((string)($rf['input'] ?? ''));
            $input = $providedInput !== ''
                ? Str::snake($providedInput)
                : 'service_fields_' . (count($out) + 1);

            $dedupeKey = Str::lower($label) . '|' . Str::lower($input);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $required = $this->parseStrictRequiredFlag($rf['required'] ?? null);
            $type = $this->mapRemoteFieldType($rf['fieldtype'] ?? $rf['type'] ?? 'text');

            $optionsRaw = $rf['fieldoptions'] ?? $rf['options'] ?? [];
            $optionsArr = [];
            if (is_string($optionsRaw)) {
                $optionsArr = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $optionsRaw))));
            } elseif (is_array($optionsRaw)) {
                $optionsArr = $optionsRaw;
            }

            $out[] = [
                'active'      => 1,
                'name'        => $label,
                'type'        => $type,
                'input'       => $input,
                'description' => (string)($rf['description'] ?? ''),
                'minimum'     => 0,
                'maximum'     => 0,
                'validation'  => null,
                'required'    => $required,
                'options'     => $type === 'select' ? $optionsArr : [],
            ];

            $seen[$dedupeKey] = true;
            if (count($out) >= $maxFields) {
                break;
            }
        }

        return $out;
    }

    private function parseStrictRequiredFlag($required): int
    {
        if (is_bool($required)) {
            return $required ? 1 : 0;
        }

        $value = Str::lower(trim((string)$required));
        return in_array($value, ['on', '1', 'true', 'yes'], true) ? 1 : 0;
    }
}
