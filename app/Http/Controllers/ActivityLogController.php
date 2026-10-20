<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->string('action')->value(), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->integer('user'), fn ($query, int $userId) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
