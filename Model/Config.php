<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module configuration reader.
 */
class Config
{
    public const XML_PATH_ENABLED = 'commercepundit/paymenttransactionfailed/enabled';
    public const XML_PATH_SEND_EMAIL = 'commercepundit/paymenttransactionfailed/send_payment_failed_email';
    public const XML_PATH_NUMBER = 'commercepundit/paymenttransactionfailed/number';
    public const XML_PATH_NUMBER_BOT = 'commercepundit/paymenttransactionfailed/number_bot';
    public const XML_PATH_TIME_WINDOW = 'commercepundit/paymenttransactionfailed/time_window_minutes';
    public const XML_PATH_BYPASS = 'commercepundit/paymenttransactionfailed/bypass_ids';
    public const XML_PATH_SUPPRESS_EMAIL = 'commercepundit/paymenttransactionfailed/suppress_email_when_blocked';
    public const XML_PATH_SUPPRESS_EMAIL_BOTS = 'commercepundit/paymenttransactionfailed/suppress_email_for_bots';
    public const XML_PATH_BLOCK_HIGH_CONFIDENCE = 'commercepundit/paymenttransactionfailed/block_high_confidence_bots';
    public const XML_PATH_FORCE_MAGENTO_RATE_LIMIT =
        'commercepundit/paymenttransactionfailed/force_magento_order_rate_limiting';
    public const XML_PATH_TRACK_BROWSE = 'commercepundit/paymenttransactionfailed/track_browse';
    public const XML_PATH_MIN_BROWSE = 'commercepundit/paymenttransactionfailed/min_browse_hits';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * When Yes, Magento sales/backpressure/enabled is treated as enabled at runtime (2.4.7+/2.4.9).
     * Harmless no-op on versions that do not enforce quote-order backpressure.
     */
    public function forceMagentoOrderRateLimiting(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FORCE_MAGENTO_RATE_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Master switch for Magento "Payment Transaction Failed" emails to admin/BCC.
     */
    public function isPaymentFailedEmailEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_EMAIL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getFailureLimit(?int $storeId = null): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_NUMBER, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getBotFailureLimit(?int $storeId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_NUMBER_BOT, ScopeInterface::SCOPE_STORE, $storeId);
        return max(1, $value ?: 3);
    }

    public function getTimeWindowMinutes(?int $storeId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_TIME_WINDOW, ScopeInterface::SCOPE_STORE, $storeId);
        return max(1, $value ?: 60);
    }

    /**
     * @return string[]
     */
    public function getBypassIps(?int $storeId = null): array
    {
        $raw = (string)$this->scopeConfig->getValue(self::XML_PATH_BYPASS, ScopeInterface::SCOPE_STORE, $storeId);
        if ($raw === '') {
            return [];
        }
        $ips = array_map('trim', explode(',', $raw));
        return array_values(array_filter($ips, static function ($ip) {
            return $ip !== '';
        }));
    }

    public function isBypassIp(string $ip, ?int $storeId = null): bool
    {
        return $ip !== '' && in_array($ip, $this->getBypassIps($storeId), true);
    }

    public function suppressEmailWhenBlocked(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SUPPRESS_EMAIL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function suppressEmailForBots(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SUPPRESS_EMAIL_BOTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function blockHighConfidenceBots(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_BLOCK_HIGH_CONFIDENCE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function trackBrowse(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TRACK_BROWSE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMinBrowseHits(?int $storeId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_BROWSE, ScopeInterface::SCOPE_STORE, $storeId);
        return max(1, $value ?: 2);
    }
}
