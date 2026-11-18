<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model\ResourceModel\FailedAttempt\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('remote_ip', 'main_table.remote_ip');
        $this->addFilterToMap('customer_email', 'main_table.customer_email');
        $this->addFilterToMap('quote_id', 'main_table.quote_id');
        return $this;
    }
}
