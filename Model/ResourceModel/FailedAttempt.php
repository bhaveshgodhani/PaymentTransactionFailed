<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FailedAttempt extends AbstractDb
{
    public const TABLE_NAME = 'payment_transaction_failed';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }
}
