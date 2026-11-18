<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Admin config field: textarea + delete button for clearing failed rows by IP.
 */
class DeleteByIp extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Commercepundit_PaymentTransactionFailed::system/config/delete_by_ip.phtml';

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('paymenttransactionfailed/failed/deleteByIp');
    }

    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData([
                'id' => 'ptf_delete_by_ip_button',
                'label' => __('Delete entries for entered IPs'),
                'class' => 'scalable delete',
            ]);
        return $button->toHtml();
    }
}
