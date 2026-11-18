<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Controller\Adminhtml\Failed;

use Commercepundit\PaymentTransactionFailed\Model\FailedAttemptFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Commercepundit_PaymentTransactionFailed::failed_delete';

    /**
     * @var FailedAttemptFactory
     */
    private $failedAttemptFactory;

    public function __construct(
        Context $context,
        FailedAttemptFactory $failedAttemptFactory
    ) {
        parent::__construct($context);
        $this->failedAttemptFactory = $failedAttemptFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('paymenttransactionfailed/failed/index');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('We can\'t find a record to delete.'));
            return $resultRedirect;
        }

        try {
            $model = $this->failedAttemptFactory->create()->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('We can\'t find a record to delete.'));
                return $resultRedirect;
            }
            $model->delete();
            $this->messageManager->addSuccessMessage(__('The record has been deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect;
    }
}
