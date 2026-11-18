<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Persist / query failed payment attempts by IP, email, and quote.
 */
class FailedAttemptRepository
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * @param array{
     *     quote_id?: int|null,
     *     customer_email?: string|null,
     *     remote_ip: string,
     *     request_path?: string|null,
     *     http_status?: int|null,
     *     is_guest?: int|bool,
     *     reason?: string|null
     * } $data
     */
    public function record(array $data): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(ResourceModel\FailedAttempt::TABLE_NAME);
            $row = [
                'quote_id' => isset($data['quote_id']) ? (int)$data['quote_id'] : null,
                'customer_email' => $data['customer_email'] ?? null,
                'remote_ip' => (string)($data['remote_ip'] ?? ''),
                'request_path' => $data['request_path'] ?? null,
                'http_status' => isset($data['http_status']) ? (int)$data['http_status'] : null,
                'is_guest' => !empty($data['is_guest']) ? 1 : 0,
                'reason' => isset($data['reason']) ? mb_substr((string)$data['reason'], 0, 255) : null,
                'created_at' => $this->dateTime->gmtDate(),
            ];
            $connection->insert($table, $row);
            $this->logger->info('Recorded payment_transaction_failed row', $row);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Unable to record failed attempt: ' . $e->getMessage()
            );
        }
    }

    public function countRecentByIp(string $ip, int $windowMinutes): int
    {
        return $this->countRecent('remote_ip', $ip, $windowMinutes);
    }

    public function countRecentByEmail(string $email, int $windowMinutes): int
    {
        return $this->countRecent('customer_email', $email, $windowMinutes);
    }

    public function countRecentByQuoteId(int $quoteId, int $windowMinutes): int
    {
        if ($quoteId <= 0) {
            return 0;
        }
        return $this->countRecent('quote_id', $quoteId, $windowMinutes);
    }

    /**
     * @param string|int $value
     */
    private function countRecent(string $column, $value, int $windowMinutes): int
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(ResourceModel\FailedAttempt::TABLE_NAME);
        $since = $this->dateTime->gmtDate(null, $this->dateTime->gmtTimestamp() - ($windowMinutes * 60));

        $select = $connection->select()
            ->from($table, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where($column . ' = ?', $value)
            ->where('created_at >= ?', $since);

        return (int)$connection->fetchOne($select);
    }

    /**
     * Delete rows matching exact IPs.
     *
     * @param string[] $ips
     * @return int Deleted row count
     */
    public function deleteByIps(array $ips): int
    {
        $ips = array_values(array_filter(array_map('trim', $ips)));
        if ($ips === []) {
            return 0;
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(ResourceModel\FailedAttempt::TABLE_NAME);
        $deleted = (int)$connection->delete($table, ['remote_ip IN (?)' => $ips]);
        $this->logger->info('Deleted payment_transaction_failed rows by IP', [
            'ips' => $ips,
            'deleted' => $deleted,
        ]);
        return $deleted;
    }
}
