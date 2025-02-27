<?php

namespace MultiPackageShipping\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    private const CONFIG_KEY = 'MultiPackageShipping.config.maxPackageWeight';

    private SystemConfigService $configService;

    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function getMaxPackageWeight(): float
    {
        return (float) ($this->configService->get(self::CONFIG_KEY) ?? 31.5);
    }

    public function setMaxPackageWeight(float $weight): void
    {
        $this->configService->set(self::CONFIG_KEY, $weight);
    }
}
