<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Controller\Adminhtml\Failed;

use Commercepundit\PaymentTransactionFailed\Model\FailedAttemptFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Commercepundit\PaymentTransactionFailed\Model\ResourceModel\FailedAttempt\CollectionFactory;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'Commercepundit_PaymentTransactionFailed::failed_delete';

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var FailedAttemptFactory
     */
    private $failedAttemptFactory;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        FailedAttemptFactory $failedAttemptFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->failedAttemptFactory = $failedAttemptFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('paymenttransactionfailed/failed/index');

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $item) {
                $model = $this->failedAttemptFactory->create()->load((int)$item->getId());
                if ($model->getId()) {
                    $model->delete();
                    $deleted++;
                }
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $deleted));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect;
    }
}
