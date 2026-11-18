<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;

/**
 * Central fraud guard: decide block / record failures for guest and customer.
 * Fail-safe: unexpected errors never break Magento checkout — they are logged only.
 */
class IpGuard
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var FailedAttemptRepository
     */
    private $failedAttemptRepository;

    /**
     * @var BrowseTracker
     */
    private $browseTracker;

    /**
     * @var BotDetector
     */
    private $botDetector;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        FailedAttemptRepository $failedAttemptRepository,
        BrowseTracker $browseTracker,
        BotDetector $botDetector,
        RemoteAddress $remoteAddress,
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->failedAttemptRepository = $failedAttemptRepository;
        $this->browseTracker = $browseTracker;
        $this->botDetector = $botDetector;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
        $this->logger = $logger;
    }

    public function getRemoteIp(?CartInterface $quote = null): string
    {
        try {
            if ($quote && method_exists($quote, 'getRemoteIp') && $quote->getRemoteIp()) {
                return (string)$quote->getRemoteIp();
            }
            return (string)$this->remoteAddress->getRemoteAddress();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function isEnabled(?int $storeId = null): bool
    {
        try {
            return $this->config->isEnabled($storeId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isBypassed(?string $ip = null, ?int $storeId = null): bool
    {
        try {
            $ip = $ip ?? $this->getRemoteIp();
            return $this->config->isBypassIp($ip, $storeId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Effective failure threshold for this IP (bot vs real visitor).
     */
    public function getEffectiveLimit(
        string $ip,
        ?int $storeId = null,
        ?CartInterface $quote = null,
        ?string $requestEmail = null
    ): int {
        if ($this->botDetector->isHighConfidenceBot($quote, $requestEmail)
            || !$this->browseTracker->looksLikeRealVisitor($ip, $storeId)
        ) {
            return $this->config->getBotFailureLimit($storeId);
        }
        return $this->config->getFailureLimit($storeId);
    }

    /**
     * true when IP, email, or quote_id failure counts exceed the configured limit.
     */
    public function isBlocked(
        ?string $ip = null,
        ?int $storeId = null,
        ?CartInterface $quote = null,
        ?string $requestEmail = null
    ): bool {
        try {
            if (!$this->isEnabled($storeId)) {
                return false;
            }

            $ip = $ip ?? $this->getRemoteIp($quote);
            if ($ip === '' || $this->isBypassed($ip, $storeId)) {
                return false;
            }

            // High-confidence live probes (zero total / scanner email / Lab Probe, etc.)
            if ($this->config->blockHighConfidenceBots($storeId)
                && $this->botDetector->isHighConfidenceBot($quote, $requestEmail)
            ) {
                $this->logger->warning('High-confidence bot blocked immediately', [
                    'remote_ip' => $ip,
                    'evaluation' => $this->botDetector->evaluate($quote, $requestEmail),
                ]);
                return true;
            }

            $window = $this->config->getTimeWindowMinutes($storeId);
            $limit = $this->getEffectiveLimit($ip, $storeId, $quote, $requestEmail);

            $ipCount = $this->failedAttemptRepository->countRecentByIp($ip, $window);
            $email = '';
            $quoteId = 0;
            $emailCount = 0;
            $quoteCount = 0;

            if ($quote) {
                $email = (string)($requestEmail
                    ?: $quote->getCustomerEmail()
                    ?: ($quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : ''));
                $quoteId = (int)$quote->getId();
                if ($email !== '') {
                    $emailCount = $this->failedAttemptRepository->countRecentByEmail($email, $window);
                }
                if ($quoteId > 0) {
                    $quoteCount = $this->failedAttemptRepository->countRecentByQuoteId($quoteId, $window);
                }
            }

            $blocked = ($ipCount >= $limit) || ($emailCount >= $limit) || ($quoteCount >= $limit);

            $this->logger->info('checkPaymentFailedData', [
                'remote_ip' => $ip,
                'email' => $email,
                'quote_id' => $quoteId,
                'ip_count' => $ipCount,
                'email_count' => $emailCount,
                'quote_count' => $quoteCount,
                'limit' => $limit,
                'browse_hits' => $this->browseTracker->getBrowseHitCount($ip),
                'bot_score' => $this->botDetector->evaluate($quote, $requestEmail)['score'],
                'blocked' => $blocked,
            ]);

            return $blocked;
        } catch (\Throwable $e) {
            $this->logger->error('isBlocked failed (fail-open): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @throws LocalizedException
     */
    public function assertNotBlocked(
        ?int $storeId = null,
        ?CartInterface $quote = null,
        ?string $requestEmail = null
    ): void {
        if ($this->isBlocked(null, $storeId, $quote, $requestEmail)) {
            $ip = $this->getRemoteIp($quote);
            $evaluation = $this->botDetector->evaluate($quote, $requestEmail);
            $this->recordFailure(
                $quote,
                'Blocked: limit exceeded or high-confidence bot score=' . $evaluation['score'],
                400,
                $this->resolveRequestPath()
            );
            $this->logger->warning(sprintf(
                'Blocked payment attempt for IP %s (limit=%d, browse_hits=%d, bot_score=%d)',
                $ip,
                $this->getEffectiveLimit($ip, $storeId, $quote, $requestEmail),
                $this->browseTracker->getBrowseHitCount($ip),
                $evaluation['score']
            ));
            throw new LocalizedException(
                __('Multiple unsuccessful attempts stopped your order from being placed. Please try to contact support.')
            );
        }
    }

    /**
     * Legacy probe check kept for compatibility; prefer BotDetector.
     */
    public function isProbeAttack(?CartInterface $quote, ?string $requestEmail = null): bool
    {
        return $this->botDetector->isHighConfidenceBot($quote, $requestEmail);
    }

    /**
     * Record a failed payment attempt for guest or logged-in customer.
     */
    public function recordFailure(
        ?CartInterface $quote = null,
        ?string $reason = null,
        ?int $httpStatus = 400,
        ?string $requestPath = null
    ): void {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $ip = $this->getRemoteIp($quote);
            if ($ip === '' || $this->isBypassed($ip)) {
                $this->logger->info('Skip recordFailure (empty or bypassed IP)', ['remote_ip' => $ip]);
                return;
            }

            $email = null;
            $quoteId = null;
            $isGuest = 1;
            if ($quote) {
                $quoteId = (int)$quote->getId();
                $email = $quote->getCustomerEmail()
                    ?: ($quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : null);
                $isGuest = ($quote->getCustomerIsGuest() || !(int)$quote->getCustomerId()) ? 1 : 0;
            }

            $evaluation = $this->botDetector->evaluate($quote);
            if ($evaluation['score'] > 0 && $reason) {
                $reason = $reason . ' | bot_score=' . $evaluation['score']
                    . ' [' . implode(',', $evaluation['reasons']) . ']';
            }

            $this->logger->info('savePaymentFailedData', [
                'quote_id' => $quoteId,
                'customer_email' => $email,
                'remote_ip' => $ip,
                'request_path' => $requestPath,
                'reason' => $reason,
                'is_guest' => $isGuest,
                'bot' => $evaluation,
            ]);

            $this->failedAttemptRepository->record([
                'quote_id' => $quoteId,
                'customer_email' => $email,
                'remote_ip' => $ip,
                'request_path' => $requestPath ?: $this->resolveRequestPath(),
                'http_status' => $httpStatus,
                'is_guest' => $isGuest,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('recordFailure failed (ignored): ' . $e->getMessage());
        }
    }

    public function shouldSuppressPaymentFailedEmail(
        ?int $storeId = null,
        ?CartInterface $quote = null,
        ?string $requestEmail = null
    ): bool {
        try {
            if (!$this->config->isPaymentFailedEmailEnabled($storeId)) {
                return true;
            }

            if ($this->config->suppressEmailForBots($storeId)
                && $this->botDetector->isHighConfidenceBot($quote, $requestEmail)
            ) {
                return true;
            }

            return $this->config->suppressEmailWhenBlocked($storeId)
                && $this->isBlocked(null, $storeId, $quote, $requestEmail);
        } catch (\Throwable $e) {
            // Fail-open for email: do not suppress if our logic breaks.
            return false;
        }
    }

    private function resolveRequestPath(): string
    {
        try {
            $path = (string)$this->request->getRequestUri();
            return mb_substr($path, 0, 255);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
