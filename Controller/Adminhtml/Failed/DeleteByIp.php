<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Controller\Adminhtml\Failed;

use Commercepundit\PaymentTransactionFailed\Model\FailedAttemptRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Delete payment_transaction_failed rows for exact IP matches.
 */
class DeleteByIp extends Action
{
    public const ADMIN_RESOURCE = 'Commercepundit_PaymentTransactionFailed::failed_delete';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var FailedAttemptRepository
     */
    private $failedAttemptRepository;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FailedAttemptRepository $failedAttemptRepository
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->failedAttemptRepository = $failedAttemptRepository;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $raw = (string)$this->getRequest()->getParam('ips', '');
        $ips = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));

        if ($ips === []) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Please enter at least one IP address.'),
            ]);
        }

        try {
            $deleted = $this->failedAttemptRepository->deleteByIps($ips);
            return $result->setData([
                'success' => true,
                'message' => (string)__(
                    'Deleted %1 row(s) for IP(s): %2',
                    $deleted,
                    implode(', ', $ips)
                ),
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to delete rows: %1', $e->getMessage()),
            ]);
        }
    }
}
