<?php

namespace App\Modules\Auth\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\Auth\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * List every user account.
     */
    public function index(): View
    {
        $users = User::query()->orderBy('name')->paginate(10);

        return view('auth::users.index', compact('users'));
    }

    /**
     * Show the user creation form.
     */
    public function create(): View
    {
        return view('auth::users.create', ['user' => null]);
    }

    /**
     * Persist a new user account.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'active' => ['required', 'boolean'],
        ]);

        User::create($data);

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', 'Usuário criado com sucesso.');
    }

    /**
     * Show the user edit form.
     */
    public function edit(Request $request, User $user): View
    {
        return view('auth::users.edit', [
            'user' => $user,
            'isSelf' => $user->is($request->user()),
        ]);
    }

    /**
     * Update a user account. Self-edits keep role and active untouched.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $isSelf = $user->is($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => $isSelf ? ['sometimes'] : ['required', Rule::enum(Role::class)],
            'active' => $isSelf ? ['sometimes'] : ['required', 'boolean'],
        ]);

        if ($isSelf) {
            unset($data['role'], $data['active']);
        }

        $password = $data['password'] ?? null;
        unset($data['password']);

        $user->fill($data);

        if (filled($password)) {
            $user->password = $password;
        }

        $user->save();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    /**
     * Delete a user account (never the current one).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors([
                'user' => 'Você não pode excluir a própria conta.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', 'Usuário excluído com sucesso.');
    }
}
