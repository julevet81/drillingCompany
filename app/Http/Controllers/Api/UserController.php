<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends BaseApiController
{
    /** GET /api/users */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['roles', 'managedRigs:id,name,code,manager_id']) // ← أضف manager_id
            ->withCount('managedRigs');

        if ($request->has('role') && $request->role != '') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('search') && $request->search != '') {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $users = $query->latest()->paginate($request->per_page ?? 15);

        // $users->getCollection()->transform(function (User $user) {
        //     $user->rigs = $user->hasRole('Rig_Manager')
        //         ? $user->managedRigs
        //         : [];

        //     return $user;
        // });

        return $this->paginated($users);
    }

    /** GET /api/users/stats */
    public function stats(): JsonResponse
    {
        return $this->success([
            'total'    => User::count(),
            'active'   => User::where('is_active', true)->count(),
            'admins'   => User::role('Super_Admin')->count(),
            'managers' => User::role('Rig_Manager')->count(),
        ]);
    }

    /** POST /api/users */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // إزالة حقول الصورة من $data لأننا سنتعامل معها يدوياً
        unset($data['photo'], $data['image'], $data['avatar'], $data['file']);

        $data['password'] = Hash::make($request->password);

        // معالجة رفع الصورة
        if ($request->hasFile('photo')) {
            $file     = $request->file('photo');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/users'), $filename);

            // حفظ المسار الكامل في قاعدة البيانات
            $data['photo'] = 'uploads/users/' . $filename;
        }

        $user = User::create($data);

        return $this->created($user->refresh()->load('roles'), 'User created');
    }

    /** GET /api/users/{user} */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'managedRigs:id,name,code,status']);
        return $this->success($user);
    }

    /** PUT /api/users/{user} */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // إزالة حقول الصورة من $data لأننا سنتعامل معها يدوياً
        unset($data['photo'], $data['image'], $data['avatar'], $data['file']);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // معالجة رفع الصورة الجديدة
        if ($request->hasFile('photo')) {
            // حذف الصورة القديمة إن وُجدت
            if ($user->photo && file_exists(public_path($user->photo))) {
                unlink(public_path($user->photo));
            }

            $file     = $request->file('photo');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/users'), $filename);

            // حفظ المسار الكامل في قاعدة البيانات
            $data['photo'] = 'uploads/users/' . $filename;
        }

        $user->update($data);

        return $this->success($user->refresh()->load('roles'), 'User updated');
    }

    /** DELETE /api/users/{user} */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->error('Cannot delete your own account', 422);
        }

        // حذف الصورة عند حذف المستخدم
        if ($user->photo && file_exists(public_path($user->photo))) {
            unlink(public_path($user->photo));
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->success(null, 'User deleted');
    }

    /** PATCH /api/users/{user}/toggle-active */
    public function toggleActive(User $user, Request $request): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->error('Cannot deactivate your own account', 422);
        }

        $user->update(['is_active' => !$user->is_active]);

        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return $this->success(
            ['id' => $user->id, 'is_active' => $user->is_active],
            $user->is_active ? 'User activated' : 'User deactivated'
        );
    }

    /** DELETE /api/users/{user}/photo */
    public function deletePhoto(User $user): JsonResponse
    {
        if (!$user->photo) {
            return $this->error('No photo to delete', 404);
        }

        if (file_exists(public_path($user->photo))) {
            unlink(public_path($user->photo));
        }

        $user->update(['photo' => null]);

        return $this->success(null, 'Photo deleted');
    }

    /** GET /api/roles */
    public function roles(): JsonResponse
    {
        return $this->success(Role::all());
    }

    /** POST /api/users/{user}/assign-role */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_id' => ['required', 'exists:roles,id']
        ]);

        $role = Role::find($request->role_id);
        $user->syncRoles([$role]);

        return $this->success([
            'user' => $user->fresh('roles')
        ], 'Role assigned successfully');
    }
}
