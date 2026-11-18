<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Plugin;

use Commercepundit\PaymentTransactionFailed\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Force Magento native order rate limiting (sales/backpressure) when our module asks for it.
 *
 * Uses ScopeConfig (always present) instead of Magento\Quote\...\OrderLimitConfigManager
 * so this is safe on Magento 2.4.6+ even when the Rate Limiting admin UI / class is absent.
 * On older builds nothing enforces sales/backpressure — forcing the flag is then a no-op.
 */
class ForceOrderRateLimitingPlugin
{
    public const PATH_ENABLED = 'sales/backpressure/enabled';

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param ScopeConfigInterface $subject
     * @param bool $result
     * @param string $path
     * @param string|null $scopeType
     * @param mixed $scopeCode
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsSetFlag(
        ScopeConfigInterface $subject,
        $result,
        $path,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) {
        if ($path !== self::PATH_ENABLED) {
            return (bool)$result;
        }

        try {
            // Respect Enable Module: when disabled, never force Magento rate limiting.
            if (!$this->config->isEnabled()) {
                return (bool)$result;
            }
            if ($this->config->forceMagentoOrderRateLimiting()) {
                return true;
            }
        } catch (\Throwable $e) {
            // Never break config reads.
        }

        return (bool)$result;
    }
}
