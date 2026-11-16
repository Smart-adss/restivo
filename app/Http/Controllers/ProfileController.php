<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('app.profile', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'locale' => ['required', Rule::in(['ar', 'en'])],
        ]);

        $request->user()->fill($data)->save();
        $request->session()->put('locale', $data['locale']);

        return redirect()->route('app.profile')->with('status', __('app.profile_saved'));
    }
}
