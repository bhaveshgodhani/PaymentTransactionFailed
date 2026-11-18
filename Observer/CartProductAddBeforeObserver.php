<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Observer;

use Commercepundit\PaymentTransactionFailed\Model\IpGuard;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Block add-to-cart when IP already exceeded failed-payment threshold.
 */
class CartProductAddBeforeObserver implements ObserverInterface
{
    /**
     * @var IpGuard
     */
    private $ipGuard;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        IpGuard $ipGuard,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->ipGuard = $ipGuard;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        // Module disabled → no frontend cart blocking.
        if (!$this->ipGuard->isEnabled()) {
            return;
        }

        try {
            if ($this->ipGuard->isBypassed()) {
                return;
            }

            if ($this->ipGuard->isBlocked()) {
                $message = __("We can't add this item to your shopping cart right now");
                $this->messageManager->addErrorMessage($message);
                throw new LocalizedException($message);
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('CartProductAddBeforeObserver failed (fail-open): ' . $e->getMessage());
        }
    }
}
