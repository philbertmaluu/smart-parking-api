<?php

namespace App\Repositories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleRepository
{
    protected $model;

    public function __construct(Role $model)
    {
        $this->model = $model;
    }

    /**
     * Get all roles with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllRolesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('users')->paginate($perPage);
    }

    /**
     * Get all active roles
     *
     * @return Collection
     */
    public function getAllActiveRoles(): Collection
    {
        return $this->model->active()->with('users')->get();
    }

    /**
     * Get role by ID with users
     *
     * @param int $id
     * @return Role|null
     */
    public function getRoleByIdWithUsers(int $id): ?Role
    {
        return $this->model->with('users')->find($id);
    }

    /**
     * Get role by name
     *
     * @param string $name
     * @return Role|null
     */
    public function getRoleByName(string $name): ?Role
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * Get default roles
     *
     * @return Collection
     */
    public function getDefaultRoles(): Collection
    {
        return $this->model->default()->get();
    }

    /**
     * Get roles by level
     *
     * @param int $level
     * @return Collection
     */
    public function getRolesByLevel(int $level): Collection
    {
        return $this->model->where('level', $level)->get();
    }

    /**
     * Create a new role
     *
     * @param array $data
     * @return Role
     */
    public function createRole(array $data): Role
    {
        return $this->model->create($data);
    }

    /**
     * Update role by ID
     *
     * @param int $id
     * @param array $data
     * @return Role|null
     */
    public function updateRole(int $id, array $data): ?Role
    {
        $role = $this->model->find($id);
        if ($role) {
            $role->update($data);
            return $role->fresh();
        }
        return null;
    }

    /**
     * Delete role by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteRole(int $id): bool
    {
        $role = $this->model->find($id);
        if ($role) {
            return $role->delete();
        }
        return false;
    }

    /**
     * Get roles with user count
     *
     * @return Collection
     */
    public function getRolesWithUserCount(): Collection
    {
        return $this->model->withCount('users')->get();
    }

    /**
     * Search roles by name
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchRoles(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->with('users')
            ->paginate($perPage);
    }

    /**
     * Get roles for dropdown/select
     *
     * @return Collection
     */
    public function getRolesForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'level')
            ->active()
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }
}
