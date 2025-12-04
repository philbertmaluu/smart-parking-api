<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records with optional pagination
     *
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getAll($perPage = null)
    {
        if ($perPage) {
            return $this->model->paginate($perPage);
        }
        return $this->model->all();
    }

    /**
     * Find record by ID
     *
     * @param int $id
     * @return Model|null
     */
    public function findById($id)
    {
        return $this->model->find($id);
    }

    /**
     * Find record by ID or throw exception
     *
     * @param int $id
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Create new record
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update record by ID
     *
     * @param int $id
     * @param array $data
     * @return Model|null
     */
    public function update($id, array $data)
    {
        $record = $this->findById($id);
        if ($record) {
            $record->update($data);
            return $record->fresh();
        }
        return null;
    }

    /**
     * Delete record by ID
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $record = $this->findById($id);
        if ($record) {
            return $record->delete();
        }
        return false;
    }

    /**
     * Soft delete record by ID
     *
     * @param int $id
     * @return bool
     */
    public function softDelete($id)
    {
        $record = $this->findById($id);
        if ($record && method_exists($record, 'delete')) {
            return $record->delete();
        }
        return false;
    }

    /**
     * Restore soft deleted record by ID
     *
     * @param int $id
     * @return bool
     */
    public function restore($id)
    {
        $record = $this->model->withTrashed()->find($id);
        if ($record && method_exists($record, 'restore')) {
            return $record->restore();
        }
        return false;
    }

    /**
     * Get active records only
     *
     * @return Collection
     */
    public function getActive()
    {
        if (method_exists($this->model, 'scopeActive')) {
            return $this->model->active()->get();
        }
        return $this->model->all();
    }

    /**
     * Find by specific field
     *
     * @param string $field
     * @param mixed $value
     * @return Model|null
     */
    public function findBy($field, $value)
    {
        return $this->model->where($field, $value)->first();
    }

    /**
     * Find by multiple fields
     *
     * @param array $criteria
     * @return Collection
     */
    public function findByMultiple(array $criteria)
    {
        $query = $this->model->query();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->get();
    }
}
