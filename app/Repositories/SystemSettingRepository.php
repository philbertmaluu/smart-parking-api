<?php

namespace App\Repositories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SystemSettingRepository
{
    protected $model;

    public function __construct(SystemSetting $model)
    {
        $this->model = $model;
    }

    /**
     * Get all system settings with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllSystemSettingsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('updatedBy')->paginate($perPage);
    }

    /**
     * Get system setting by key
     *
     * @param string $key
     * @return SystemSetting|null
     */
    public function getSystemSettingByKey(string $key): ?SystemSetting
    {
        return $this->model->byKey($key)->first();
    }

    /**
     * Get system setting value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSystemSettingValue(string $key, $default = null)
    {
        $setting = $this->getSystemSettingByKey($key);
        return $setting ? $setting->typed_value : $default;
    }

    /**
     * Set system setting value
     *
     * @param string $key
     * @param mixed $value
     * @param string $dataType
     * @param string $description
     * @param int $updatedBy
     * @return SystemSetting
     */
    public function setSystemSettingValue(string $key, $value, string $dataType = 'string', string $description = '', int $updatedBy = null): SystemSetting
    {
        $setting = $this->getSystemSettingByKey($key);

        if ($setting) {
            $setting->data_type = $dataType;
            $setting->description = $description;
            $setting->updated_by = $updatedBy;
            $setting->setTypedValue($value);
            $setting->save();
            return $setting;
        }

        return $this->model->create([
            'setting_key' => $key,
            'data_type' => $dataType,
            'description' => $description,
            'updated_by' => $updatedBy,
        ])->tap(function ($setting) use ($value) {
            $setting->setTypedValue($value);
            $setting->save();
        });
    }

    /**
     * Get system settings by data type
     *
     * @param string $dataType
     * @return Collection
     */
    public function getSystemSettingsByDataType(string $dataType): Collection
    {
        return $this->model->byDataType($dataType)->get();
    }

    /**
     * Create a new system setting
     *
     * @param array $data
     * @return SystemSetting
     */
    public function createSystemSetting(array $data): SystemSetting
    {
        return $this->model->create($data);
    }

    /**
     * Update system setting by ID
     *
     * @param int $id
     * @param array $data
     * @return SystemSetting|null
     */
    public function updateSystemSetting(int $id, array $data): ?SystemSetting
    {
        $setting = $this->model->find($id);
        if ($setting) {
            $setting->update($data);
            return $setting->fresh();
        }
        return null;
    }

    /**
     * Delete system setting by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteSystemSetting(int $id): bool
    {
        $setting = $this->model->find($id);
        if ($setting) {
            return $setting->delete();
        }
        return false;
    }

    /**
     * Delete system setting by key
     *
     * @param string $key
     * @return bool
     */
    public function deleteSystemSettingByKey(string $key): bool
    {
        $setting = $this->getSystemSettingByKey($key);
        if ($setting) {
            return $setting->delete();
        }
        return false;
    }

    /**
     * Search system settings by key or description
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchSystemSettings(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('setting_key', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->with('updatedBy')
            ->paginate($perPage);
    }

    /**
     * Get system settings for configuration
     *
     * @return array
     */
    public function getSystemSettingsForConfig(): array
    {
        $settings = $this->model->all();
        $config = [];

        foreach ($settings as $setting) {
            $config[$setting->setting_key] = $setting->typed_value;
        }

        return $config;
    }

    /**
     * Get system settings by group (using key prefix)
     *
     * @param string $prefix
     * @return Collection
     */
    public function getSystemSettingsByGroup(string $prefix): Collection
    {
        return $this->model->where('setting_key', 'like', "{$prefix}%")->get();
    }

    /**
     * Bulk update system settings
     *
     * @param array $settings
     * @param int $updatedBy
     * @return bool
     */
    public function bulkUpdateSystemSettings(array $settings, int $updatedBy): bool
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $this->setSystemSettingValue($key, $value['value'], $value['data_type'] ?? 'string', $value['description'] ?? '', $updatedBy);
            } else {
                $this->setSystemSettingValue($key, $value, 'string', '', $updatedBy);
            }
        }
        return true;
    }

    /**
     * Get system settings updated by specific user
     *
     * @param int $userId
     * @return Collection
     */
    public function getSystemSettingsUpdatedByUser(int $userId): Collection
    {
        return $this->model->where('updated_by', $userId)->get();
    }
}
