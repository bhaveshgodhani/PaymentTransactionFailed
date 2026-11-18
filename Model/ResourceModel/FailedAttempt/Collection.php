<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model\ResourceModel\FailedAttempt;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Commercepundit\PaymentTransactionFailed\Model\FailedAttempt as FailedAttemptModel;
use Commercepundit\PaymentTransactionFailed\Model\ResourceModel\FailedAttempt as FailedAttemptResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(FailedAttemptModel::class, FailedAttemptResource::class);
    }
}
