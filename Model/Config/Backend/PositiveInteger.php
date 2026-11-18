<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Store positive integers for failure thresholds / time window.
 */
class PositiveInteger extends Value
{
    /**
     * @inheritdoc
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if ($value === null || $value === '') {
            return parent::beforeSave();
        }

        if (!preg_match('/^\d+$/', trim((string)$value)) || (int)$value < 1) {
            throw new LocalizedException(__('Please enter a whole number greater than zero.'));
        }

        $this->setValue((string)(int)$value);
        return parent::beforeSave();
    }
}
