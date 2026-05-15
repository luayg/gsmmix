<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalReply;
use App\Models\LocalSource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LocalReplyController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $sourceId = (int) $request->get('source_id', 0);
        $usage = trim((string) $request->get('usage', ''));
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $rows = LocalReply::query()
            ->with(['source', 'productOrder'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($where) use ($q) {
                    $where->where('reply', 'like', "%{$q}%")
                        ->orWhere('device_identifier', 'like', "%{$q}%")
                        ->orWhereHas('source', fn ($source) => $source->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($sourceId > 0, fn ($query) => $query->where('local_source_id', $sourceId))
            ->when($usage === 'used', fn ($query) => $query->whereNotNull('used_by_product_order_id'))
            ->when($usage === 'unused', fn ($query) => $query->whereNull('used_by_product_order_id'))
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $sources = LocalSource::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.local-replies.index', compact('rows', 'sources', 'q', 'sourceId', 'usage', 'perPage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'local_source_id' => ['nullable', 'integer', 'exists:local_sources,id'],
            'device_based' => ['nullable', 'boolean'],
            'reply' => ['nullable', 'string'],
            'bulk' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'string', 'max:100'],
        ]);

        $deviceBased = $request->boolean('device_based');
        $expiresAt = $this->parseExpiration($data['expires_at'] ?? null);
        $items = $this->extractReplyItems((string) ($data['reply'] ?? ''), (string) ($data['bulk'] ?? ''), $deviceBased);

        if (empty($items)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['reply' => ['Please add at least one reply.']],
            ], 422);
        }

        foreach ($items as $item) {
            LocalReply::create([
                'local_source_id' => $data['local_source_id'] ?? null,
                'device_based' => $deviceBased,
                'device_identifier' => $item['device_identifier'],
                'reply' => $item['reply'],
                'expires_at' => $expiresAt,
            ]);
        }

        return response()->json([
            'ok' => true,
            'msg' => count($items) === 1 ? 'Reply created' : count($items) . ' replies created',
        ]);
    }

    public function update(Request $request, LocalReply $reply)
    {
        $data = $request->validate([
            'local_source_id' => ['nullable', 'integer', 'exists:local_sources,id'],
            'device_based' => ['nullable', 'boolean'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'reply' => ['required', 'string'],
            'expires_at' => ['nullable', 'string', 'max:100'],
        ]);

        $reply->update([
            'local_source_id' => $data['local_source_id'] ?? null,
            'device_based' => $request->boolean('device_based'),
            'device_identifier' => trim((string) ($data['device_identifier'] ?? '')) ?: null,
            'reply' => $data['reply'],
            'expires_at' => $this->parseExpiration($data['expires_at'] ?? null),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Reply updated']);
    }

    public function destroy(LocalReply $reply)
    {
        $reply->delete();

        return response()->json(['ok' => true, 'msg' => 'Reply deleted']);
    }

    public function modalCreate()
    {
        $sources = LocalSource::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.local-replies.modals.create', compact('sources'));
    }

    public function modalView(LocalReply $reply)
    {
        $reply->load(['source', 'productOrder']);

        return view('admin.local-replies.modals.view', compact('reply'));
    }

    public function modalEdit(LocalReply $reply)
    {
        $reply->load('source');
        $sources = LocalSource::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.local-replies.modals.edit', compact('reply', 'sources'));
    }

    public function modalDelete(LocalReply $reply)
    {
        $reply->load(['source', 'productOrder']);

        return view('admin.local-replies.modals.delete', compact('reply'));
    }

    private function extractReplyItems(string $reply, string $bulk, bool $deviceBased): array
    {
        $lines = [];
        $reply = trim($reply);
        $bulk = trim($bulk);

        if ($reply !== '') {
            $lines[] = $reply;
        }

        if ($bulk !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $bulk) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        $items = [];
        foreach ($lines as $line) {
            if ($deviceBased) {
                [$device, $text] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, '');
                $device = trim((string) $device);
                $text = trim((string) $text);
                if ($device === '' || $text === '') {
                    continue;
                }

                $items[] = [
                    'device_identifier' => $device,
                    'reply' => $text,
                ];
            } else {
                $items[] = [
                    'device_identifier' => null,
                    'reply' => $line,
                ];
            }
        }

        return $items;
    }

    private function parseExpiration(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'none') === 0) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}