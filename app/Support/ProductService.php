<?php

namespace App\Support;

use App\Models\FileOrder;
use App\Models\FileService;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\ServerOrder;
use App\Models\ServerService;
use App\Models\SmmOrder;
use App\Models\SmmService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductService
{
    public const TYPES = ['imei', 'server', 'file', 'smm'];

    public static function serviceModel(string $type): string
    {
        return match ($type) {
            'imei' => ImeiService::class,
            'server' => ServerService::class,
            'file' => FileService::class,
            'smm' => SmmService::class,
            default => throw ValidationException::withMessages(['service_type' => 'Choose a supported service type.']),
        };
    }

    public static function orderModel(string $type): string
    {
        return match ($type) {
            'imei' => ImeiOrder::class,
            'server' => ServerOrder::class,
            'file' => FileOrder::class,
            'smm' => SmmOrder::class,
            default => throw ValidationException::withMessages(['service_type' => 'Choose a supported service type.']),
        };
    }

    public static function find(string $type, int $id, bool $activeOnly = false): ?Model
    {
        $query = self::serviceModel($type)::query()->whereKey($id);
        if ($activeOnly) {
            $query->where('active', true);
        }
        return $query->first();
    }

    public static function displayName(Model $service): string
    {
        return self::displayText($service->name ?? '');
    }

    public static function displayText(mixed $value): string
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            $value = $value->all();
        }
        if (is_array($value)) {
            $locale = app()->getLocale();
            return trim((string) ($value[$locale] ?? $value['en'] ?? $value['fallback'] ?? reset($value) ?: ''));
        }
        $raw = trim((string) $value);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? self::displayText($decoded) : $raw;
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::TYPES as $type) {
            $model = self::serviceModel($type);
            $instance = new $model;
            if (!Schema::hasTable($instance->getTable())) {
                $options[$type] = [];
                continue;
            }
            $options[$type] = $model::query()->orderBy('id')->get()->map(fn (Model $service) => [
                'id' => (int) $service->getKey(),
                'name' => self::displayName($service),
                'cost' => round((float) ($service->cost ?? 0), 4),
                'active' => (bool) $service->active,
            ])->all();
        }
        return $options;
    }

    public static function inputSchema(string $type, Model $service): array
    {
        $decode = static function (mixed $value): array {
            if (is_array($value)) return $value;
            $decoded = is_string($value) ? json_decode($value, true) : null;
            return is_array($decoded) ? $decoded : [];
        };
        $text = static function (mixed $value): string {
            if (is_array($value)) return trim((string)($value[app()->getLocale()] ?? $value['en'] ?? $value['fallback'] ?? reset($value) ?: ''));
            $decoded = is_string($value) ? json_decode($value, true) : null;
            return is_array($decoded) ? trim((string)($decoded[app()->getLocale()] ?? $decoded['en'] ?? $decoded['fallback'] ?? reset($decoded) ?: '')) : trim((string)$value);
        };

        $main = $decode($service->main_field ?? []);
        $mainType = strtolower($text($main['type'] ?? 'text')) ?: 'text';
        $rules = $decode($main['rules'] ?? []);
        $presets = [
            'imei' => ['IMEI', 'number', 15, 15], 'serial' => ['IMEI / Serial', 'text', 10, 13],
            'imei_serial' => ['IMEI / Serial', 'text', 10, 15], 'number' => ['Number', 'number', 1, 255],
            'email' => ['Email', 'email', 3, 255], 'text' => ['Target', 'text', 1, 255],
            'custom' => ['Target', 'text', 1, 255],
        ];
        $preset = $presets[$mainType] ?? $presets['text'];
        $mainField = in_array($type, ['imei', 'smm'], true) ? [
            'input' => 'device', 'name' => $text($main['label'] ?? '') ?: $preset[0],
            'type' => $preset[1], 'required' => true,
            'minimum' => (int)($main['minimum'] ?? $rules['minimum'] ?? $preset[2]),
            'maximum' => (int)($main['maximum'] ?? $rules['maximum'] ?? $preset[3]),
        ] : null;

        $rows = collect();
        if (Schema::hasTable('custom_fields')) {
            $rows = DB::table('custom_fields')->where('service_type', $type.'_service')
                ->where('service_id', $service->getKey())->where('active', true)->orderBy('ordering')->get();
        }
        if ($rows->isEmpty()) {
            $params = $decode($service->params ?? []);
            $rows = collect($params['custom_fields'] ?? [])->filter(fn($field) => is_array($field) && (int)($field['active'] ?? 1) === 1)->map(fn($field) => (object)$field);
        }
        $fields = $rows->map(function ($field) use ($text, $decode): array {
            $options = $decode($field->field_options ?? $field->options ?? []);
            if (!$options && is_string($field->field_options ?? null)) $options = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $field->field_options))));
            return [
                'input' => trim((string)($field->input ?? '')), 'name' => $text($field->name ?? $field->input ?? ''),
                'type' => strtolower((string)($field->field_type ?? $field->type ?? 'text')),
                'required' => (bool)($field->required ?? false), 'description' => $text($field->description ?? ''),
                'minimum' => (int)($field->minimum ?? 0), 'maximum' => (int)($field->maximum ?? 0), 'options' => $options,
            ];
        })->filter(fn($field) => $field['input'] !== '')->values()->all();

        if ($mainField && collect($fields)->contains(fn($field) => in_array(strtolower($field['input']), ['link','username','target','url','custom'], true))) $mainField = null;
        return ['main' => $mainField, 'fields' => $fields, 'file' => $type === 'file', 'quantity' => in_array($type, ['server','smm'], true)];
    }
}
