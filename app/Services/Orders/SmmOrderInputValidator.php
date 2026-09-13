<?php

namespace App\Services\Orders;

use App\Models\SmmService;

final class SmmOrderInputValidator
{
    public function validate(SmmService $service, array $fields, string $device = ''): array
    {
        $params = $this->decodeArray($service->params ?? []);
        $customFields = $params['custom_fields'] ?? [];
        if (!is_array($customFields)) {
            $customFields = [];
        }

        $errors = [];
        $hasTargetField = false;
        $targetInputs = ['link', 'username', 'usernames', 'target', 'url', 'page', 'channel', 'post', 'custom'];

        foreach ($customFields as $field) {
            if (!is_array($field) || (int)($field['active'] ?? 1) !== 1) {
                continue;
            }

            $input = strtolower(trim((string)($field['input'] ?? '')));
            if ($input === '') {
                continue;
            }

            if (in_array($input, $targetInputs, true)) {
                $hasTargetField = true;
            }

            $name = trim((string)($field['name'] ?? $input));
            $required = (int)($field['required'] ?? 0) === 1;
            $type = strtolower(trim((string)($field['type'] ?? 'text')));
            $validation = strtolower(trim((string)($field['validation'] ?? '')));
            $minimum = is_numeric($field['minimum'] ?? null) ? (int)$field['minimum'] : 0;
            $maximum = is_numeric($field['maximum'] ?? null) ? (int)$field['maximum'] : 0;

            $value = $fields[$input] ?? null;
            $valueText = $this->textValue($value);

            if ($required && $valueText === '') {
                $errors["required.{$input}"] = "{$name} is required.";
                continue;
            }

            if ($valueText === '') {
                continue;
            }

            $isNumeric = $type === 'number' || $validation === 'numeric';
            if ($isNumeric) {
                if (preg_match('/^-?\d+$/', $valueText) !== 1) {
                    $errors["required.{$input}"] = "{$name} must be an integer.";
                    continue;
                }

                $numericValue = (int)$valueText;
                if ($minimum > 0 && $numericValue < $minimum) {
                    $errors["required.{$input}"] = "{$name} must be at least {$minimum}.";
                    continue;
                }
                if ($maximum > 0 && $numericValue > $maximum) {
                    $errors["required.{$input}"] = "{$name} must be at most {$maximum}.";
                    continue;
                }

                continue;
            }

            $length = mb_strlen($valueText);
            if ($minimum > 0 && $length < $minimum) {
                $errors["required.{$input}"] = "{$name} must be at least {$minimum} characters.";
                continue;
            }
            if ($maximum > 0 && $length > $maximum) {
                $errors["required.{$input}"] = "{$name} must be at most {$maximum} characters.";
                continue;
            }

            if ($validation === 'email' && !filter_var($valueText, FILTER_VALIDATE_EMAIL)) {
                $errors["required.{$input}"] = "{$name} must be a valid email.";
            }
        }

        if (!$hasTargetField && trim($device) === '') {
            $errors['device'] = 'Link / Username / Target is required.';
        }

        return $errors;
    }

    private function decodeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function textValue($value): string
    {
        if (is_array($value)) {
            $parts = array_values(array_filter(array_map(
                static fn ($item) => trim((string)$item),
                $value
            ), static fn ($item) => $item !== ''));

            return implode("\n", $parts);
        }

        return trim((string)$value);
    }
}
