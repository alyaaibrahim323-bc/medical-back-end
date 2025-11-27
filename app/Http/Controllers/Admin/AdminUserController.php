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
    /**
     * قائمة ال Admin/Staff Users
     * GET /admin/users?status=active|blocked&role=...&search=...
     */
    public function index(Request $r)
    {
        $q = User::query()
            ->whereIn('role', ['admin','doctor']); // عدلي حسب الأدوار اللي عندك

        $q->when($r->filled('status'), function ($x) use ($r) {
            if (in_array($r->status, ['active','blocked','inactive'], true)) {
                $x->where('status', $r->status);
            }
        });

        $q->when($r->filled('search'), function ($x) use ($r) {
            $s = $r->search;
            $x->where(function ($q) use ($s) {
                $q->where('name','like',"%{$s}%")
                  ->orWhere('email','like',"%{$s}%")
                  ->orWhere('phone','like',"%{$s}%")
                  ->orWhere('id', $s);
            });
        });

        $q->when($r->filled('role'), function ($x) use ($r) {
            $role = $r->role;
            $x->whereHas('roles', fn($q)=>$q->where('name',$role));
        });

        $users = $q->with('roles')->orderByDesc('id')->paginate(20);

        return response()->json(['data' => $users]);
    }

    /**
     * Add User (Admin / Staff)
     * POST /admin/users
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','unique:users,email'],
            'phone'    => ['nullable','string','max:30'],
            'password' => ['required','string','min:8'],
            'role'     => ['required', Rule::in(['admin','doctor','staff','user'])],
            'status'   => ['nullable', Rule::in(['active','blocked','inactive'])],
        ]);

        $user = User::create([
            'name'   => $data['name'],
            'email'  => $data['email'],
            'phone'  => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role'   =>  $data['role'],
            'status' => $data['status'] ?? 'active',
        ]);

        // Assign Spatie role
        $user->syncRoles([$data['role']]);

        return response()->json([
            'message' => 'Admin user created',
            'data'    => $user->load('roles'),
        ], 201);
    }

    /**
     * View Details popup
     * GET /admin/users/{id}
     */
    public function show($id)
    {
        $user = User::with('roles')->whereIn('role',['admin','doctor'])->findOrFail($id);

        return response()->json(['data' => $user]);
    }

    /**
     * Edit basic info + status + roles
     * PATCH /admin/users/{id}
     */
   public function update(Request $r, $id)
{
    $user = User::whereIn('role', ['admin','doctor'])->findOrFail($id);

    $data = $r->validate([
        'name'      => ['sometimes','string','max:120'],
        'email'     => ['sometimes','email', Rule::unique('users','email')->ignore($user->id)],
        'phone'     => ['sometimes','string','max:30'],
        'status'    => ['sometimes', Rule::in(['active','blocked'])],
        'roles'     => ['sometimes','array'],   // ["admin","support_agent"]
        'roles.*'   => ['string'],
        'password'  => ['sometimes','string','min:8'], // 👈 جديد
    ]);

    // نجهز الداتا اللي هتروح لـ update
    $updateData = $data;

    // مانبعّتش roles ولا password raw على الـ update()
    unset($updateData['roles'], $updateData['password']);

    // لو فيه password في الريكوست → نعمله Hash ونضيفه
    if (array_key_exists('password', $data)) {
        $updateData['password'] = Hash::make($data['password']);
    }

    // نعمل تحديث لكل الفيلدز العادية + الباسورد لو موجود
    $user->update($updateData);

    // لو فيه roles في الريكوست → نعمل syncRoles
    if (array_key_exists('roles', $data)) {
        $user->syncRoles($data['roles']);
    }

    return response()->json([
        'message' => 'Admin user updated',
        'data'    => $user->fresh()->load('roles'),
    ]);
}
}
