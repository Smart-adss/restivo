<?php

namespace App\Http\Controllers;

use App\Support\Features\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function show(FeatureGate $gate): View
    {
        abort_unless($gate->allows('module.pos'), 403);

        return view('app.pos');
    }

    public function store(FeatureGate $gate): RedirectResponse
    {
        abort_unless($gate->allows('module.pos'), 403);
        $gate->assertWritable();

        return redirect()->route('app.pos')->with('status', __('app.pos_recorded'));
    }
}
