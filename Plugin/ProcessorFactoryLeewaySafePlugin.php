<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Plugin;

use Magento\Framework\App\Config\Data\ProcessorFactory;
use Magento\Framework\App\Config\Data\ProcessorInterface;

/**
 * Portable fix for Magento 2FA Leeway null-value bug without hard-depending on Magento_TwoFactorAuth.
 *
 * - 2FA enabled (live): wraps Leeway processor so empty/null does not block config saves.
 * - 2FA disabled / module absent: class check skips wrap — no conflict, no compile break.
 */
class ProcessorFactoryLeewaySafePlugin
{
    private const LEEWAY_CLASS = 'Magento\\TwoFactorAuth\\Model\\Config\\Backend\\Leeway';
    public const DEFAULT_LEEWAY = 29;

    /**
     * @param ProcessorFactory $subject
     * @param ProcessorInterface $result
     * @param string $processorModel
     * @return ProcessorInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(ProcessorFactory $subject, ProcessorInterface $result, $processorModel): ProcessorInterface
    {
        if ($processorModel !== self::LEEWAY_CLASS && strpos((string)$processorModel, 'TwoFactorAuth\\Model\\Config\\Backend\\Leeway') === false) {
            return $result;
        }

        if (!class_exists(self::LEEWAY_CLASS)) {
            return $result;
        }

        return new class ($result) implements ProcessorInterface {
            /**
             * @var ProcessorInterface
             */
            private $inner;

            public function __construct(ProcessorInterface $inner)
            {
                $this->inner = $inner;
            }

            /**
             * @param mixed $value
             * @return mixed
             */
            public function processValue($value)
            {
                if ($value === null || $value === '') {
                    return ProcessorFactoryLeewaySafePlugin::DEFAULT_LEEWAY;
                }
                return $this->inner->processValue($value);
            }
        };
    }
}
