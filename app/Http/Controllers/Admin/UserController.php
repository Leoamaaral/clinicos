<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'password' => Hash::make($request->validated('password')),
            'role' => User::ROLE_STAFF,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional criado com sucesso.']);

        return to_route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/edit', [
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário atualizado com sucesso.']);

        return to_route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'O administrador do sistema não pode ser removido.']);

            return back();
        }

        if ($user->id === auth()->id()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Você não pode remover seu próprio usuário.']);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário removido com sucesso.']);

        return to_route('admin.users.index');
    }
}
