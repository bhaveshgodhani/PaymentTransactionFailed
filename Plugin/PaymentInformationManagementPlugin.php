<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Plugin;

use Commercepundit\PaymentTransactionFailed\Model\IpGuard;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

/**
 * Guard + record failures for logged-in customer payment-information.
 * Fail-open on unexpected errors so live checkout is never broken by this module.
 */
class PaymentInformationManagementPlugin
{
    private const REQUEST_PATH = '/V1/carts/mine/payment-information';

    /**
     * @var IpGuard
     */
    private $ipGuard;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        IpGuard $ipGuard,
        CartRepositoryInterface $cartRepository,
        CheckoutHelper $checkoutHelper,
        LoggerInterface $logger
    ) {
        $this->ipGuard = $ipGuard;
        $this->cartRepository = $cartRepository;
        $this->checkoutHelper = $checkoutHelper;
        $this->logger = $logger;
    }

    /**
     * @param PaymentInformationManagement $subject
     * @param int|string $cartId
     * @param PaymentInterface $paymentMethod
     * @param QuoteAddressInterface|null $billingAddress
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagement $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?QuoteAddressInterface $billingAddress = null
    ): array {
        if (!$this->ipGuard->isEnabled()) {
            return [$cartId, $paymentMethod, $billingAddress];
        }

        try {
            $quote = $this->loadQuote($cartId);
            $this->ipGuard->assertNotBlocked(null, $quote);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Customer before-guard failed (fail-open): ' . $e->getMessage());
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * @param PaymentInformationManagement $subject
     * @param callable $proceed
     * @param int|string $cartId
     * @param PaymentInterface $paymentMethod
     * @param QuoteAddressInterface|null $billingAddress
     * @return int
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagement $subject,
        callable $proceed,
        $cartId,
        PaymentInterface $paymentMethod,
        ?QuoteAddressInterface $billingAddress = null
    ) {
        // Module disabled → do not intercept frontend/API payment flow at all.
        if (!$this->ipGuard->isEnabled()) {
            return $proceed($cartId, $paymentMethod, $billingAddress);
        }

        try {
            return $proceed($cartId, $paymentMethod, $billingAddress);
        } catch (CouldNotSaveException $e) {
            $this->safeHandleFailure($cartId, $e->getMessage());
            throw $e;
        } catch (LocalizedException $e) {
            $this->safeHandleFailure($cartId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param int|string $cartId
     */
    private function safeHandleFailure($cartId, string $reason): void
    {
        try {
            if (!$this->ipGuard->isEnabled()) {
                return;
            }
            $quote = $this->loadQuote($cartId);
            $this->logger->info('Customer payment-information failed', [
                'cart_id' => $cartId,
                'quote_id' => $quote ? $quote->getId() : null,
                'remote_ip' => $this->ipGuard->getRemoteIp($quote),
                'reason' => $reason,
            ]);

            if ($quote instanceof Quote) {
                try {
                    // Magento 2.4.8+/2.4.9 Checkout Helper requires Quote (not CartInterface).
                    $this->checkoutHelper->sendPaymentFailedEmail($quote, $reason);
                } catch (\Throwable $mailEx) {
                    $this->logger->error('sendPaymentFailedEmail failed: ' . $mailEx->getMessage());
                    $this->ipGuard->recordFailure($quote, $reason, 400, self::REQUEST_PATH);
                }
            } elseif ($quote) {
                $this->ipGuard->recordFailure($quote, $reason, 400, self::REQUEST_PATH);
            } else {
                $this->ipGuard->recordFailure(null, $reason, 400, self::REQUEST_PATH);
            }
        } catch (\Throwable $e) {
            $this->logger->error('safeHandleFailure ignored: ' . $e->getMessage());
        }
    }

    /**
     * @param int|string $cartId
     */
    private function loadQuote($cartId): ?CartInterface
    {
        try {
            return $this->cartRepository->getActive((int)$cartId);
        } catch (\Throwable $e) {
            try {
                return $this->cartRepository->get((int)$cartId);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }
}
