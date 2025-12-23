<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
   
    public function index()
    {
        $roles = Role::withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id'                => $role->id,
                    'name'              => $role->name,
                    'permissions_count' => $role->permissions_count,
                    'created_at'        => $role->created_at,
                ];
            });

        return response()->json(['data' => $roles]);
    }

   
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'        => ['required','string','max:100','unique:roles,name'],
            'permissions' => ['nullable','array'],
            'permissions.*'=> ['string'],
            'status'      => ['nullable','in:active,blocked'], 
        ]);

        $role = Role::create(['name' => $data['name']]);

        if (!empty($data['permissions'])) {
            $perms = Permission::whereIn('name', $data['permissions'])->get();
            $role->syncPermissions($perms);
        }

        return response()->json([
            'message' => 'Role created',
            'data'    => $role->load('permissions'),
        ], 201);
    }

  
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json(['data' => $role]);
    }

    public function update(Request $r, $id)
    {
        $role = Role::findOrFail($id);

        $data = $r->validate([
            'name'        => ['sometimes','string','max:100','unique:roles,name,' . $role->id],
            'permissions' => ['sometimes','array'],
            'permissions.*'=> ['string'],
        ]);

        if (array_key_exists('name', $data)) {
            $role->name = $data['name'];
            $role->save();
        }

        if (array_key_exists('permissions', $data)) {
            $perms = Permission::whereIn('name', $data['permissions'] ?? [])->get();
            $role->syncPermissions($perms);
        }

        return response()->json([
            'message' => 'Role updated',
            'data'    => $role->fresh()->load('permissions'),
        ]);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'admin') {
            return response()->json(['message' => 'Cannot delete admin role'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
