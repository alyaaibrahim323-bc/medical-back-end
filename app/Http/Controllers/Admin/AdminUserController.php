<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
  
   public function index(Request $r)
{
    $base = User::query()
        ->whereIn('role', ['admin', 'doctor']);

    $base->when($r->filled('search'), function ($q) use ($r) {
        $s = trim($r->search);

        $q->where(function ($x) use ($s) {
            $x->where('name', 'like', "%{$s}%")
              ->orWhere('email', 'like', "%{$s}%")
              ->orWhere('phone', 'like', "%{$s}%");

            if (is_numeric($s)) {
                $x->orWhere('id', (int) $s);
            }
        });
    });

    $base->when($r->filled('role'), function ($q) use ($r) {
        $q->where('role', $r->role);
    });


    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('status', 'active')->count(),
        'inactive' => (clone $base)->where('status', 'inactive')->count(),
    ];

   
    $q = clone $base;

    $q->when($r->filled('status'), function ($qq) use ($r) {
        if (in_array($r->status, ['active', 'inactive'], true)) {
            $qq->where('status', $r->status);
        }
    });

    $users = $q->with('roles')->orderByDesc('id')->paginate(20);

    return response()->json([
        'data'   => $users,
        'counts' => $counts,
    ]);
}



  
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','unique:users,email'],
            'phone'    => ['nullable','string','max:30'],
            'password' => ['required','string','min:8'],
            'role'     => ['required', Rule::in(['admin','doctor'])],
            'status'   => ['nullable', Rule::in(['active','inactive'])],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
            'status'   => $data['status'] ?? 'active',
        ]);

        // Assign Spatie role
        $user->syncRoles([$data['role']]);

        return response()->json([
            'message' => 'User created successfully',
            'data'    => $user->load('roles'),
        ], 201);
    }


    
    public function show($id)
    {
        $user = User::with('roles')
            ->whereIn('role',['admin','doctor'])
            ->findOrFail($id);

        return response()->json(['data' => $user]);
    }


 
    public function update(Request $r, $id)
    {
        $user = User::whereIn('role', ['admin','doctor'])->findOrFail($id);

        $data = $r->validate([
            'name'      => ['sometimes','string','max:120'],
            'email'     => ['sometimes','email', Rule::unique('users','email')->ignore($user->id)],
            'phone'     => ['sometimes','string','max:30'],
            'status'    => ['sometimes', Rule::in(['active','inactive'])],
            'roles'     => ['sometimes','array'],
            'roles.*'   => ['string'],
            'password'  => ['sometimes','string','min:8'],
        ]);

        $updateData = $data;

        unset($updateData['roles'], $updateData['password']);

        if (isset($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => $user->fresh()->load('roles'),
        ]);
    }
}
