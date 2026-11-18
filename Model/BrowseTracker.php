<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Lightweight per-IP browse history (non-payment pages) stored in Magento cache.
 */
class BrowseTracker
{
    private const CACHE_PREFIX = 'ptf_browse_';
    private const CACHE_TTL = 3600;
    private const MAX_PATHS = 30;

    /**
     * Paths / route prefixes that count as payment / checkout-only noise.
     */
    private const IGNORE_PATTERNS = [
        'rest/',
        'V1/carts',
        'payment-information',
        'checkout/onepage',
        'checkout/sidebar',
        'customer/section',
        'customer/ajax',
        'swagger',
        'static/',
        'media/',
        'admin',
    ];

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        CacheInterface $cache,
        Json $json,
        Config $config
    ) {
        $this->cache = $cache;
        $this->json = $json;
        $this->config = $config;
    }

    public function record(string $ip, string $path): void
    {
        if ($ip === '' || !$this->config->trackBrowse() || $this->shouldIgnore($path)) {
            return;
        }

        $paths = $this->getPaths($ip);
        $normalized = $this->normalizePath($path);
        if ($normalized === '' || in_array($normalized, $paths, true)) {
            return;
        }

        $paths[] = $normalized;
        if (count($paths) > self::MAX_PATHS) {
            $paths = array_slice($paths, -self::MAX_PATHS);
        }

        $this->cache->save(
            $this->json->serialize($paths),
            $this->getCacheKey($ip),
            ['PAYMENT_TRANSACTION_FAILED'],
            self::CACHE_TTL
        );
    }

    public function getBrowseHitCount(string $ip): int
    {
        return count($this->getPaths($ip));
    }

    public function looksLikeRealVisitor(string $ip, ?int $storeId = null): bool
    {
        if (!$this->config->trackBrowse($storeId)) {
            // Without browse tracking, treat everyone as "real" and use the higher limit.
            return true;
        }
        return $this->getBrowseHitCount($ip) >= $this->config->getMinBrowseHits($storeId);
    }

    /**
     * @return string[]
     */
    public function getPaths(string $ip): array
    {
        if ($ip === '') {
            return [];
        }
        $raw = $this->cache->load($this->getCacheKey($ip));
        if (!$raw) {
            return [];
        }
        try {
            $data = $this->json->unserialize($raw);
            return is_array($data) ? array_values(array_map('strval', $data)) : [];
        } catch (\InvalidArgumentException $e) {
            return [];
        }
    }

    private function shouldIgnore(string $path): bool
    {
        $path = ltrim(strtolower($path), '/');
        foreach (self::IGNORE_PATTERNS as $pattern) {
            if ($pattern !== '' && strpos($path, strtolower($pattern)) !== false) {
                return true;
            }
        }
        return $path === '';
    }

    private function normalizePath(string $path): string
    {
        $path = strtok(ltrim($path, '/'), '?') ?: '';
        return mb_substr($path, 0, 180);
    }

    private function getCacheKey(string $ip): string
    {
        return self::CACHE_PREFIX . md5($ip);
    }
}
