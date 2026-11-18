<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Writes module fraud/payment-fail events to var/log/BotPaymentFail.log only.
 */
class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/BotPaymentFail.log';
}
