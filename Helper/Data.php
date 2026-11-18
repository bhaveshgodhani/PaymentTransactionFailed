<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Helper;

use Commercepundit\PaymentTransactionFailed\Model\BrowseTracker;
use Commercepundit\PaymentTransactionFailed\Model\Config;
use Commercepundit\PaymentTransactionFailed\Model\FailedAttemptRepository;
use Commercepundit\PaymentTransactionFailed\Model\IpGuard;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Thin helper for templates / admin / external reuse.
 */
class Data extends AbstractHelper
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var IpGuard
     */
    private $ipGuard;

    /**
     * @var FailedAttemptRepository
     */
    private $failedAttemptRepository;

    /**
     * @var BrowseTracker
     */
    private $browseTracker;

    public function __construct(
        Context $context,
        Config $config,
        IpGuard $ipGuard,
        FailedAttemptRepository $failedAttemptRepository,
        BrowseTracker $browseTracker
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->ipGuard = $ipGuard;
        $this->failedAttemptRepository = $failedAttemptRepository;
        $this->browseTracker = $browseTracker;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getIpGuard(): IpGuard
    {
        return $this->ipGuard;
    }

    public function getFailedAttemptRepository(): FailedAttemptRepository
    {
        return $this->failedAttemptRepository;
    }

    public function getBrowseTracker(): BrowseTracker
    {
        return $this->browseTracker;
    }
}
