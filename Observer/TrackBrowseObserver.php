<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Observer;

use Commercepundit\PaymentTransactionFailed\Model\BrowseTracker;
use Commercepundit\PaymentTransactionFailed\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Record non-payment frontend page hits per IP so bots (payment-only) can use a stricter limit.
 */
class TrackBrowseObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var BrowseTracker
     */
    private $browseTracker;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        Config $config,
        BrowseTracker $browseTracker,
        RemoteAddress $remoteAddress,
        RequestInterface $request
    ) {
        $this->config = $config;
        $this->browseTracker = $browseTracker;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        // Module disabled → no frontend browse tracking.
        if (!$this->config->isEnabled()) {
            return;
        }
        if (!$this->config->trackBrowse()) {
            return;
        }

        try {
            $ip = (string)$this->remoteAddress->getRemoteAddress();
            if ($ip === '') {
                return;
            }

            $path = (string)$this->request->getPathInfo();
            if ($path === '') {
                $path = (string)$this->request->getRequestUri();
            }

            $this->browseTracker->record($ip, $path);
        } catch (\Throwable $e) {
            // Never break frontend page loads.
        }
    }
}
