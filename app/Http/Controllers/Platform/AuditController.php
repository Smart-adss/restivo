<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission('platform.audit.view'), 403);

        return view('platform.audit.index', [
            'logs' => AuditLog::query()->latest('id')->limit(50)->get(),
        ]);
    }
}
