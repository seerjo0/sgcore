<?php

namespace App\Modules\Core\Services;

/**
 * Panel-controlled update policy: whether the schedule may check for releases,
 * download the package and install it on its own.
 *
 * Stored as settings (group "updater") so the admin can flip them without
 * touching .env; the environment variables remain the defaults, which keeps
 * existing deployments (CMS_UPDATER_AUTO=true) behaving as before.
 */
class UpdatePolicy
{
    private const GROUP = 'updater';

    private const KEY_CHECK = 'updater_auto_check';

    private const KEY_DOWNLOAD = 'updater_auto_download';

    private const KEY_INSTALL = 'updater_auto_install';

    public function __construct(private Settings $settings) {}

    public function autoCheck(): bool
    {
        return $this->flag(self::KEY_CHECK, true);
    }

    public function autoDownload(): bool
    {
        return $this->flag(self::KEY_DOWNLOAD, false);
    }

    /**
     * Installing implies downloading, so this is false whenever download is off.
     */
    public function autoInstall(): bool
    {
        return $this->autoDownload()
            && $this->flag(self::KEY_INSTALL, (bool) config('cms.updater.auto.enabled'));
    }

    /**
     * @param  array{auto_check:bool,auto_download:bool,auto_install:bool}  $values
     */
    public function save(array $values): void
    {
        $install = (bool) $values['auto_install'];
        $download = (bool) $values['auto_download'] || $install;

        $this->settings->set(self::KEY_CHECK, (bool) $values['auto_check'], self::GROUP);
        $this->settings->set(self::KEY_DOWNLOAD, $download, self::GROUP);
        $this->settings->set(self::KEY_INSTALL, $install && $download, self::GROUP);
    }

    private function flag(string $key, bool $default): bool
    {
        $value = $this->settings->get($key, $default);

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return $value === null ? $default : (bool) $value;
    }
}
