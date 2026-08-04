<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    private const PAYROLL_FIELDS = [
        'base_salary',
        'meal_allowance',
        'overtime_rate_per_hour',
        'late_penalty_per_minute',
    ];

    public function index()
    {
        $columns = ['id', 'name', 'email', 'role'];

        foreach (self::PAYROLL_FIELDS as $field) {
            if (Schema::hasColumn('users', $field)) {
                $columns[] = $field;
            }
        }

        return Inertia::render('Users', [
            // Daftar user (password otomatis hidden oleh model)
            'users' => User::orderBy('name')
                ->get($columns)
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'base_salary' => (float) ($user->base_salary ?? 0),
                    'meal_allowance' => (float) ($user->meal_allowance ?? 0),
                    'overtime_rate_per_hour' => (float) ($user->overtime_rate_per_hour ?? 0),
                    'late_penalty_per_minute' => (float) ($user->late_penalty_per_minute ?? 0),
                ]),
            // Peta role untuk dropdown form
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $validationRules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
        ];
        foreach (self::PAYROLL_FIELDS as $field) {
            if (Schema::hasColumn('users', $field)) {
                $validationRules[$field] = ['required', 'numeric', 'min:0'];
            }
        }

        $data = $request->validate($validationRules);
        foreach (self::PAYROLL_FIELDS as $field) {
            if (! Schema::hasColumn('users', $field)) {
                unset($data[$field]);
            }
        }

        User::create($data);

        return redirect()->route('users.index')->with('status', 'User ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $validationRules = [
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
        ];
        foreach (self::PAYROLL_FIELDS as $field) {
            if (Schema::hasColumn('users', $field)) {
                $validationRules[$field] = ['required', 'numeric', 'min:0'];
            }
        }

        $data = $request->validate($validationRules);

        // password hanya diganti bila diisi
        if (empty($data['password'])) {
            unset($data['password']);
        }
        foreach (self::PAYROLL_FIELDS as $field) {
            if (! Schema::hasColumn('users', $field)) {
                unset($data[$field]);
            }
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'User diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('status', 'Tidak bisa menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User dihapus.');
    }
}
