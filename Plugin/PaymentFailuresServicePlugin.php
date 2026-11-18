<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Plugin;

use Commercepundit\PaymentTransactionFailed\Model\IpGuard;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Magento\Sales\Model\Service\PaymentFailuresService;
use Psr\Log\LoggerInterface;

/**
 * Log Magento payment-failed emails into our table and optionally suppress flood/bots.
 * Fail-open: if our logic errors, Magento still sends its email (unless we already decided to suppress).
 */
class PaymentFailuresServicePlugin
{
    /**
     * @var IpGuard
     */
    private $ipGuard;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        IpGuard $ipGuard,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->ipGuard = $ipGuard;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * @param PaymentFailuresService $subject
     * @param callable $proceed
     * @param int $cartId
     * @param string $message
     * @param string $checkoutType
     * @return PaymentFailuresInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundHandle(
        PaymentFailuresService $subject,
        callable $proceed,
        int $cartId,
        string $message,
        string $checkoutType = 'onepage'
    ): PaymentFailuresInterface {
        if (!$this->ipGuard->isEnabled()) {
            return $proceed($cartId, $message, $checkoutType);
        }

        $quote = null;
        try {
            $quote = $this->cartRepository->get($cartId);
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $this->logger->info('PaymentFailuresService handle', [
                'cart_id' => $cartId,
                'checkout_type' => $checkoutType,
                'remote_ip' => $this->ipGuard->getRemoteIp($quote),
                'message' => $message,
            ]);

            $this->ipGuard->recordFailure(
                $quote,
                $message,
                400,
                'payment_failed_email/' . $checkoutType
            );

            if ($this->ipGuard->shouldSuppressPaymentFailedEmail(null, $quote)) {
                $this->logger->info('Suppressed payment-failed email notification', [
                    'cart_id' => $cartId,
                    'remote_ip' => $this->ipGuard->getRemoteIp($quote),
                ]);
                return $subject;
            }
        } catch (\Throwable $e) {
            $this->logger->error('PaymentFailuresServicePlugin failed (fail-open to Magento email): '
                . $e->getMessage());
        }

        return $proceed($cartId, $message, $checkoutType);
    }
}
