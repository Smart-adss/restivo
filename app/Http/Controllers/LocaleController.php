<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'in:ar,en'],
        ])['locale'];

        $request->session()->put('locale', $locale);

        if ($request->user() !== null) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
