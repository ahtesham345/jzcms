<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('users.view');

        $users = $this->buildUserQuery($request)->paginate(10)->withQueryString();
        $roles = Role::all();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('users.create');

        return view('users.create', ['roles' => Role::all()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('users.create');

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('users.view');

        $user = User::with('roles')->findOrFail($id);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('users.edit');

        $user = User::with('roles')->findOrFail($id);
        $roles = Role::all();

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $this->authorize('users.edit');

        $user = User::findOrFail($id);

        $user->update($this->prepareUserData($request));
        $user->syncRoles([$request->role]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('users.delete');

        try {
            $user = User::findOrFail($id);

            if ($this->cannotDeleteUser($user)) {
                return redirect()->route('users.index')->with('error', $this->getDeletionErrorMessage($user));
            }

            $user->delete();

            return redirect()
                ->route('users.index')
                ->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('users.index')
                ->with('error', 'An error occurred while deleting the user. Please try again.');
        }
    }

    /**
     * Build the user query with search and filters.
     */
    private function buildUserQuery(Request $request)
    {
        $query = User::with('roles');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        return $query->latest();
    }

    /**
     * Prepare user data for update.
     */
    private function prepareUserData(UpdateUserRequest $request): array
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        return $data;
    }

    /**
     * Check if user cannot be deleted.
     */
    private function cannotDeleteUser(User $user): bool
    {
        return $user->id === auth()->id() || $user->hasRole('Super Admin');
    }

    /**
     * Get appropriate error message for deletion failure.
     */
    private function getDeletionErrorMessage(User $user): string
    {
        if ($user->id === auth()->id()) {
            return 'You cannot delete your own account.';
        }

        return 'Super Admin accounts cannot be deleted.';
    }
}
