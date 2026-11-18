<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Controller\Adminhtml\Failed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Commercepundit_PaymentTransactionFailed::failed_view';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Commercepundit_PaymentTransactionFailed::failed_menu');
        $resultPage->getConfig()->getTitle()->prepend(__('Payment Transaction Failed'));
        $resultPage->addBreadcrumb(__('Payment Transaction Failed'), __('Payment Transaction Failed'));
        return $resultPage;
    }
}
