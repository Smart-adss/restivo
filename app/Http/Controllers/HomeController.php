<?php

namespace App\Http\Controllers;

use App\Support\Ui\WorkspaceShortcuts;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        return view('app.home', [
            'branches' => $user->visibleBranches(),
            'shortcuts' => WorkspaceShortcuts::for($user),
            'subscription' => $user->tenant?->subscription?->loadMissing('plan'),
        ]);
    }
}
