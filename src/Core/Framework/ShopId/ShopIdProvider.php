<?php declare(strict_types=1);

namespace Swag\SaasConnect\Core\Framework\ShopId;

use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShopIdProvider
{
    public const SHOP_ID_SYSTEM_CONFIG_KEY = 'saas.shopId';
    public const SHOP_DOMAIN_CHANGE_CONFIG_KEY = 'saas.domainChange';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getShopId(): string
    {
        $shopId = $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY);

        if (!$shopId) {
            $newShopId = $this->generateShopId();
            $this->systemConfigService->set(self::SHOP_ID_SYSTEM_CONFIG_KEY, [
                'app_url' => getenv('APP_URL'),
                'value' => $newShopId,
            ]);

            return $newShopId;
        }

        if (getenv('APP_URL') !== ($shopId['app_url'] ?? '')) {
            $this->systemConfigService->set(self::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);
            /** @var string $appUrl */
            $appUrl = getenv('APP_URL');

            throw new AppUrlChangeDetectedException($shopId['app_url'], $appUrl);
        }

        return $shopId['value'];
    }

    private function generateShopId(): string
    {
        return Random::getAlphanumericString(12);
    }
}
