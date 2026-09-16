<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLanguageRequest;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Settings\ContentTranslator;

final class LanguageController extends Controller
{
    public function __construct(private readonly ContentTranslator $translator) {}

    public function index(Request $request)
    {
        $languages = Language::query()->withCount('translations')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%')->orWhere('native_name', 'like', '%'.$request->string('q').'%')->orWhere('locale', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->input('status') === 'active'))
            ->orderBy('ordering')->orderBy('id')->paginate(15)->withQueryString();
        return view('admin.settings.languages.index', compact('languages'));
    }

    public function store(SaveLanguageRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $this->payload($request);
            $data['is_default'] = $request->boolean('is_default') || !Language::query()->where('is_default', true)->exists();
            if ($data['is_default']) {
                Language::query()->lockForUpdate()->update(['is_default' => false]);
                $data['active'] = true;
            }
            Language::create($data);
        });
        $this->translator->forgetLocale((string) $request->input('locale'));
        return back()->with('ok', 'Language created.');
    }

    public function update(SaveLanguageRequest $request, Language $language): RedirectResponse
    {
        $oldLocale = $language->locale;
        DB::transaction(function () use ($request, $language): void {
            Language::query()->lockForUpdate()->findOrFail($language->id);
            $data = $this->payload($request);
            $makeDefault = $request->boolean('is_default');
            if ($language->is_default && !$makeDefault) {
                throw ValidationException::withMessages(['is_default' => 'Choose another default language before removing this default.']);
            }
            if ($makeDefault) {
                Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
                $data['is_default'] = true;
                $data['active'] = true;
            }
            $language->update($data);
        });
        $this->translator->forgetLocale($oldLocale);
        $this->translator->forget($language->fresh());
        return back()->with('ok', 'Language updated.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->is_default) {
            return back()->withErrors(['language' => 'The default language cannot be deleted.']);
        }
        $locale = $language->locale;
        $language->delete();
        $this->translator->forgetLocale($locale);
        return back()->with('ok', 'Language deleted.');
    }

    public function translations(Language $language)
    {
        $translations = $language->translations()->orderBy('translation_key')->get();
        return view('admin.settings.languages.translations', compact('language', 'translations'));
    }

    public function updateTranslations(Request $request, Language $language): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.edit'), 403);
        $data = $request->validate([
            'translations' => ['nullable', 'array', 'max:500'],
            'translations.*.key' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'translations.*.value' => ['nullable', 'string', 'max:10000'],
        ]);
        $rows = collect($data['translations'] ?? []);
        if ($rows->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['translations' => 'Translation keys must be unique.']);
        }
        DB::transaction(function () use ($language, $rows): void {
            foreach ($rows as $row) {
                $value = trim((string) ($row['value'] ?? ''));
                if ($value === '') {
                    $language->translations()->where('translation_key', $row['key'])->delete();
                } else {
                    $language->translations()->updateOrCreate(['translation_key' => $row['key']], ['value' => $value]);
                }
            }
        });
        $this->translator->forget($language);
        return back()->with('ok', 'Translations updated.');
    }

    public function export(Language $language)
    {
        $json = json_encode(['locale' => $language->locale, 'name' => $language->name, 'translations' => $language->translations()->orderBy('translation_key')->pluck('value', 'translation_key')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return response($json, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="'.$language->locale.'.json"']);
    }

    public function import(Request $request, Language $language): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.edit'), 403);
        $request->validate(['translation_file' => ['required', 'file', 'mimes:json,txt', 'max:2048']]);
        try {
            $decoded = json_decode($request->file('translation_file')->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['translation_file' => 'The uploaded file is not valid JSON.']);
        }
        $rows = $decoded['translations'] ?? $decoded;
        if (!is_array($rows) || count($rows) > 5000) throw ValidationException::withMessages(['translation_file' => 'The translation file must contain a JSON object with no more than 5,000 entries.']);
        DB::transaction(function () use ($language, $rows): void {
            foreach ($rows as $key => $value) {
                if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.-]{1,191}$/', $key) || !is_scalar($value) || mb_strlen((string)$value) > 10000) continue;
                $language->translations()->updateOrCreate(['translation_key' => $key], ['value' => (string)$value]);
            }
        });
        $this->translator->forget($language);
        return back()->with('ok', 'Translations imported.');
    }

    private function payload(SaveLanguageRequest $request): array
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active');
        $data['is_default'] = $request->boolean('is_default');
        $data['ordering'] = (int) ($data['ordering'] ?? 0);
        return $data;
    }
}
