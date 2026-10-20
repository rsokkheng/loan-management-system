<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingRequest;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['settings' => Setting::allValues()]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        Setting::setMany($request->validated());

        ActivityLog::record('updated', 'System settings updated');

        return back()->with('success', __('Settings saved.'));
    }
}
