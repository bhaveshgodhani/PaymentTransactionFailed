<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Plugin;

use Commercepundit\PaymentTransactionFailed\Model\IpGuard;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;

/**
 * Guard + record failures for guest payment-information.
 * Fail-open on unexpected errors so live checkout is never broken by this module.
 */
class GuestPaymentInformationManagementPlugin
{
    private const REQUEST_PATH = '/V1/guest-carts/payment-information';

    /**
     * @var IpGuard
     */
    private $ipGuard;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

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
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CheckoutHelper $checkoutHelper,
        LoggerInterface $logger
    ) {
        $this->ipGuard = $ipGuard;
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->logger = $logger;
    }

    /**
     * @param GuestPaymentInformationManagement $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param QuoteAddressInterface|null $billingAddress
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagement $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?QuoteAddressInterface $billingAddress = null
    ): array {
        if (!$this->ipGuard->isEnabled()) {
            return [$cartId, $email, $paymentMethod, $billingAddress];
        }

        try {
            $quote = $this->loadQuoteByMaskedId((string)$cartId, (string)$email);
            $this->ipGuard->assertNotBlocked(null, $quote, (string)$email);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Guest before-guard failed (fail-open): ' . $e->getMessage());
        }

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    /**
     * @param GuestPaymentInformationManagement $subject
     * @param callable $proceed
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param QuoteAddressInterface|null $billingAddress
     * @return int
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagement $subject,
        callable $proceed,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?QuoteAddressInterface $billingAddress = null
    ) {
        // Module disabled → do not intercept frontend/API payment flow at all.
        if (!$this->ipGuard->isEnabled()) {
            return $proceed($cartId, $email, $paymentMethod, $billingAddress);
        }

        try {
            return $proceed($cartId, $email, $paymentMethod, $billingAddress);
        } catch (CouldNotSaveException $e) {
            $this->safeHandleFailure((string)$cartId, (string)$email, $e->getMessage());
            throw $e;
        } catch (LocalizedException $e) {
            $this->safeHandleFailure((string)$cartId, (string)$email, $e->getMessage());
            throw $e;
        }
    }

    private function safeHandleFailure(string $maskedId, string $email, string $reason): void
    {
        try {
            if (!$this->ipGuard->isEnabled()) {
                return;
            }
            $quote = $this->loadQuoteByMaskedId($maskedId, $email);
            $this->logger->info('Guest payment-information failed', [
                'cart_id' => $maskedId,
                'quote_id' => $quote ? $quote->getId() : null,
                'email' => $email,
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

    private function loadQuoteByMaskedId(string $maskedId, string $email = ''): ?CartInterface
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');
            if (!$quoteIdMask->getQuoteId()) {
                return null;
            }
            try {
                $quote = $this->cartRepository->getActive((int)$quoteIdMask->getQuoteId());
            } catch (\Throwable $e) {
                $quote = $this->cartRepository->get((int)$quoteIdMask->getQuoteId());
            }
            if ($email && !$quote->getCustomerEmail()) {
                $quote->setCustomerEmail($email);
            }
            return $quote;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
