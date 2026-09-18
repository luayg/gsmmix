<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    public function __invoke(Request $request, string $language): RedirectResponse
    {
        $selected = Language::query()
            ->where('code', $language)
            ->where('active', true)
            ->firstOrFail();

        $request->session()->put('locale', $selected->locale);

        return redirect()->back();
    }
}
