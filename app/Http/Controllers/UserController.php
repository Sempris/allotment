<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Exceptions\UserChangeRejected;
use App\Http\Requests\UserRequest;
use App\Models\Plot;
use App\Models\User;
use App\Services\UserAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserAdministration $administration) {}

    public function index(): View
    {
        return view('users.index', ['users' => User::query()->with('plot')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('users.form', $this->formData(new User(['role' => Role::Tenant, 'active' => true])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->administration->create($request->userData());

        return redirect()->route('users.index')->with('status', "Zugang für {$user->name} angelegt.");
    }

    public function edit(User $user): View
    {
        return view('users.form', $this->formData($user) + ['hasHistory' => $user->hasHistory()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->administration->update($user, $request->userData(), $request->user());
        } catch (UserChangeRejected $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput($request->except('password'));
        }

        return redirect()->route('users.index')->with('status', "Zugang für {$user->name} gespeichert.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->administration->delete($user, $request->user());
        } catch (UserChangeRejected $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('users.index')->with('status', "Zugang für {$user->name} gelöscht.");
    }

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::cases(),
            'plots' => Plot::query()->get()->sortBy('number', SORT_NATURAL),
        ];
    }
}
