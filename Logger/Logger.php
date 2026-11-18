<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Logger;

use Magento\Framework\Logger\Monolog;

/**
 * Dedicated logger name for BotPaymentFail.log.
 * Extends Magento Monolog wrapper for PHP 8.1–8.4 / Monolog 3 compatibility.
 */
class Logger extends Monolog
{
}
