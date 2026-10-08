<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Setting;

class Settings
{
    /**
     * Per-request cache of already resolved keys.
     *
     * @var array<string, mixed>
     */
    protected array $resolved = [];

    /**
     * Get a setting value by key, falling back to the given default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->resolved)) {
            $setting = Setting::query()->where('key', $key)->first();

            $this->resolved[$key] = $setting?->value;
        }

        return $this->resolved[$key] ?? $default;
    }

    /**
     * Persist a setting value (upsert by key).
     */
    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );

        $this->resolved[$key] = $value;
    }

    /**
     * Get all settings, optionally filtered by group.
     *
     * @return array<string, mixed>
     */
    public function all(?string $group = null): array
    {
        $query = Setting::query();

        if ($group !== null) {
            $query->where('group', $group);
        }

        return $query->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
            ->all();
    }

    /**
     * Remove a setting by key.
     */
    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        unset($this->resolved[$key]);
    }
}
