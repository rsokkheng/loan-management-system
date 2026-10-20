<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('branch')->withCount('managedLoans')->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create', $this->formData(new User(['is_active' => true, 'locale' => 'en'])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        ActivityLog::record('created', "User {$user->name} created", $user);

        return redirect()->route('users.index')->with('success', __('User created.'));
    }

    public function edit(User $user): View
    {
        return view('users.edit', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($user->is($request->user())) {
            unset($data['role'], $data['is_active'], $data['branch_id']);
        }

        $user->update($data);

        ActivityLog::record('updated', "User {$user->name} updated", $user);

        return redirect()->route('users.index')->with('success', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        $user->delete();

        ActivityLog::record('deleted', "User {$user->name} deleted");

        return redirect()->route('users.index')->with('success', __('User deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => UserRole::cases(),
            'branches' => Branch::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
