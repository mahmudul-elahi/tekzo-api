<?php

namespace App\Repositories\Settings;

use App\Models\Setting\Setting;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SettingRepository extends BaseRepository
{
    public function __construct(Setting $setting)
    {
        $this->model = $setting;
    }

    /**
     * Retrieve formatted settings based on the context, model type, and model ID.
     */
    public function getFormattedSettings(string $context = 'app', ?string $modelType = null, ?int $modelId = null): array
    {
        $settings = $this->basicQuery($context, $modelType, $modelId)
            ->get(['id', 'name', 'value']);

        return $this->formatSettings($settings);
    }

    /**
     * Find a specific app setting by its name and context.
     */
    public function findAppSettingByName(string $name, string $context = 'app'): null
    {
        return $this->basicQuery($context)
            ->where('name', $name)
            ->first();
    }

    /**
     * Create or retrieve a setting instance.
     */
    public function createOrRetrieveSetting(string $name, string $context)
    {
            return $this->basicQuery($context)
                ->where('name', $name)
                ->firstOrNew();


    }

    /**
     * Format settings into a key-value array.
     */
    private function formatSettings(Collection $settings): array
    {
        return $settings->pluck('value', 'name')->toArray();
    }

    /**
     * Retrieve delivery-related settings as a formatted list.
     */
    public function getDeliverySettings($context): array
    {

            $settings = $this->basicQuery($context)
                ->get(['id', 'name', 'value']);


        return $this->formatSettings($settings);
    }

    /**
     * Build a base query for settings with optional filters.
     */
    private function basicQuery($context = null, $settingAbleType = null, $settingAbleId = null): Builder
    {
        return $this->model::query()
            ->when($context, fn(Builder $query) => $query->whereIn('context', is_array($context) ? $context : [$context]))
            ->when($settingAbleType, fn(Builder $query) => $query->where('settingable_type', $settingAbleType))
            ->when($settingAbleId, fn(Builder $query) => $query->where('settingable_id', $settingAbleId));
    }

    /**
     * Retrieve settings for multiple contexts.
     */
    public function getSettingsByContext($context): \Illuminate\Database\Eloquent\Collection
    {
        $contexts = is_array($context) ? $context : func_get_args();

        return $this->model::query()
            ->whereIn('context', $contexts)
            ->get();
    }

    /**
     * Get the default mail configuration key.
     */
    public function getDefaultMailKey(string $key = 'default_mail'): ?Setting
    {
        return $this->model::query()
            ->where('name', $key)
            ->select(['id', 'name', 'value', 'context'])
            ->first();
    }

    public function createSettingInstance(string $name, string $context, $settingAbleType = null, $settingAbleId = null): \Illuminate\Database\Eloquent\Model
    {
        return $this->basicQuery($context, $settingAbleType, $settingAbleId)
            ->where('name', $name)
            ->firstOrNew();
    }

}
