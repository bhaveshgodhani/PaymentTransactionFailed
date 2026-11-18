<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model;

use Magento\Framework\Model\AbstractModel;
use Commercepundit\PaymentTransactionFailed\Model\ResourceModel\FailedAttempt as FailedAttemptResource;

class FailedAttempt extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(FailedAttemptResource::class);
    }
}
