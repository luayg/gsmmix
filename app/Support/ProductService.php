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
        $name = $service->name ?? '';
        if ($name instanceof \Illuminate\Support\Collection) {
            $name = $name->all();
        }
        if (is_array($name)) {
            return trim((string) ($name['en'] ?? $name['fallback'] ?? reset($name) ?: ''));
        }
        $raw = trim((string) $name);
        $decoded = json_decode($raw, true);
        return is_array($decoded)
            ? trim((string) ($decoded['en'] ?? $decoded['fallback'] ?? reset($decoded) ?: ''))
            : $raw;
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
}
