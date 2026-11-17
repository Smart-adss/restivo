<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FailedJobController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission('platform.audit.view'), 403);

        $jobs = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (object $job): object {
                $job->exception_preview = $this->preview((string) $job->exception);

                return $job;
            });

        return view('platform.failed-jobs.index', [
            'jobs' => $jobs,
        ]);
    }

    private function preview(string $exception): string
    {
        $line = strtok($exception, "\n") ?: $exception;

        return mb_strlen($line) > 240 ? mb_substr($line, 0, 240).'…' : $line;
    }
}
