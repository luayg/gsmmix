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

    public function index()
    {
        $languages = Language::query()->withCount('translations')->orderBy('ordering')->orderBy('id')->get();
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

    private function payload(SaveLanguageRequest $request): array
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active');
        $data['is_default'] = $request->boolean('is_default');
        $data['ordering'] = (int) ($data['ordering'] ?? 0);
        return $data;
    }
}
