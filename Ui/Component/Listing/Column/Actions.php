<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const URL_PATH_DELETE = 'paymenttransactionfailed/failed/delete';

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }
            $item[$this->getData('name')]['delete'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::URL_PATH_DELETE,
                    ['entity_id' => $item['entity_id']]
                ),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete record #%1', $item['entity_id']),
                    'message' => __('Are you sure you want to delete this failed payment record?'),
                ],
                'post' => true,
            ];
        }

        return $dataSource;
    }
}
