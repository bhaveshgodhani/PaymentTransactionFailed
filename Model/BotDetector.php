<?php
declare(strict_types=1);

namespace Commercepundit\PaymentTransactionFailed\Model;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Detect high-confidence bot / carding / scanner payloads like live "Payment Transaction Failed" probes.
 *
 * Signals from production emails:
 * - guest + zero/empty cart total
 * - disposable / probe emails (@example.test, @example.invalid, nuclei, probe)
 * - names like "Lab Probe"
 * - empty items, empty payment/shipping method
 * - broken address / template leftovers
 */
class BotDetector
{
    /**
     * Score at or above this is treated as a high-confidence bot.
     */
    public const HIGH_CONFIDENCE_SCORE = 4;

    /**
     * @return array{score:int,reasons:string[]}
     */
    public function evaluate(?CartInterface $quote, ?string $requestEmail = null): array
    {
        if (!$quote) {
            return ['score' => 0, 'reasons' => []];
        }

        $score = 0;
        $reasons = [];

        $email = strtolower(trim((string)(
            $requestEmail
            ?: $quote->getCustomerEmail()
            ?: ($quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : '')
        )));

        $isGuest = (bool)$quote->getCustomerIsGuest() || !(int)$quote->getCustomerId();
        if ($isGuest) {
            $score++;
            $reasons[] = 'guest';
        }

        $grandTotal = (float)$quote->getGrandTotal();
        $items = method_exists($quote, 'getAllVisibleItems') ? $quote->getAllVisibleItems() : [];
        if ($grandTotal <= 0.00001) {
            $score += 2;
            $reasons[] = 'zero_total';
        }
        if (!$items || count($items) === 0) {
            $score += 2;
            $reasons[] = 'empty_items';
        }

        if ($email !== '' && $this->isSuspiciousEmail($email)) {
            $score += 3;
            $reasons[] = 'suspicious_email';
        }

        $billing = $quote->getBillingAddress();
        if ($billing) {
            $name = strtolower(trim(
                (string)$billing->getFirstname() . ' ' . (string)$billing->getLastname()
            ));
            if ($this->isSuspiciousName($name)) {
                $score += 2;
                $reasons[] = 'suspicious_name';
            }

            $street = is_array($billing->getStreet())
                ? implode(' ', $billing->getStreet())
                : (string)$billing->getStreet();
            if ($street !== '' && (strpos($street, '{{') !== false || stripos($street, 'var ') !== false)) {
                $score += 3;
                $reasons[] = 'template_street';
            }

            $telephone = preg_replace('/\D+/', '', (string)$billing->getTelephone());
            if ($telephone === '' || preg_match('/^0+$/', (string)$telephone)) {
                $score++;
                $reasons[] = 'invalid_telephone';
            }

            if (!(string)$billing->getPostcode() || !(string)$billing->getCity()) {
                $score++;
                $reasons[] = 'incomplete_address';
            }
        } else {
            $score++;
            $reasons[] = 'missing_billing';
        }

        $payment = method_exists($quote, 'getPayment') ? $quote->getPayment() : null;
        if (!$payment || !(string)$payment->getMethod()) {
            $score++;
            $reasons[] = 'missing_payment_method';
        }

        $userAgent = method_exists($quote, 'getUserAgent') ? (string)$quote->getUserAgent() : '';
        if ($userAgent === '') {
            $score++;
            $reasons[] = 'missing_user_agent';
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    public function isHighConfidenceBot(?CartInterface $quote, ?string $requestEmail = null): bool
    {
        $result = $this->evaluate($quote, $requestEmail);
        return $result['score'] >= self::HIGH_CONFIDENCE_SCORE;
    }

    private function isSuspiciousEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $patterns = [
            '/@example\.(test|invalid|com)$/i',
            '/@(mailinator|guerrillamail|tempmail|10minutemail)\./i',
            '/(probe|nuclei|scanner|botattack|carding)/i',
            '/stylesmuggler/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }
        return false;
    }

    private function isSuspiciousName(string $name): bool
    {
        if ($name === '' || $name === ' ') {
            return true;
        }
        return (bool)preg_match('/\b(probe|nuclei|test user|bot|attacker|lab probe)\b/i', $name);
    }
}
