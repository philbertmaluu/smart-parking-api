<?php

namespace App\Repositories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionRepository
{
    protected $model;

    public function __construct(Permission $model)
    {
        $this->model = $model;
    }

    /**
     * Get all permissions with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPermissionsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('users')->paginate($perPage);
    }

    /**
     * Get all active permissions
     *
     * @return Collection
     */
    public function getAllActivePermissions(): Collection
    {
        return $this->model->active()->with('users')->get();
    }

    /**
     * Get permission by ID with users
     *
     * @param int $id
     * @return Permission|null
     */
    public function getPermissionByIdWithUsers(int $id): ?Permission
    {
        return $this->model->with('users')->find($id);
    }

    /**
     * Get permission by name
     *
     * @param string $name
     * @return Permission|null
     */
    public function getPermissionByName(string $name): ?Permission
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * Get permissions by guard
     *
     * @param string $guard
     * @return Collection
     */
    public function getPermissionsByGuard(string $guard): Collection
    {
        return $this->model->where('guard', $guard)->get();
    }

    /**
     * Create a new permission
     *
     * @param array $data
     * @return Permission
     */
    public function createPermission(array $data): Permission
    {
        return $this->model->create($data);
    }

    /**
     * Update permission by ID
     *
     * @param int $id
     * @param array $data
     * @return Permission|null
     */
    public function updatePermission(int $id, array $data): ?Permission
    {
        $permission = $this->model->find($id);
        if ($permission) {
            $permission->update($data);
            return $permission->fresh();
        }
        return null;
    }

    /**
     * Delete permission by ID
     *
     * @param int $id
     * @return bool
     */
    public function deletePermission(int $id): bool
    {
        $permission = $this->model->find($id);
        if ($permission) {
            return $permission->delete();
        }
        return false;
    }

    /**
     * Get permissions with user count
     *
     * @return Collection
     */
    public function getPermissionsWithUserCount(): Collection
    {
        return $this->model->withCount('users')->get();
    }

    /**
     * Search permissions by name or description
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchPermissions(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('guard', 'like', "%{$search}%")
                          ->with('users')
                          ->paginate($perPage);
    }

    /**
     * Get permissions for dropdown/select
     *
     * @return Collection
     */
    public function getPermissionsForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'guard')
                          ->active()
                          ->orderBy('guard')
                          ->orderBy('name')
                          ->get();
    }

    /**
     * Assign permission to user
     *
     * @param int $userId
     * @param int $permissionId
     * @param int $assignedBy
     * @return bool
     */
    public function assignPermissionToUser(int $userId, int $permissionId, int $assignedBy): bool
    {
        $permission = $this->model->find($permissionId);
        if ($permission) {
            $permission->users()->attach($userId, [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy
            ]);
            return true;
        }
        return false;
    }

    /**
     * Remove permission from user
     *
     * @param int $userId
     * @param int $permissionId
     * @return bool
     */
    public function removePermissionFromUser(int $userId, int $permissionId): bool
    {
        $permission = $this->model->find($permissionId);
        if ($permission) {
            $permission->users()->detach($userId);
            return true;
        }
        return false;
    }

    /**
     * Get user permissions
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserPermissions(int $userId): Collection
    {
        return $this->model->whereHas('users', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();
    }
}
