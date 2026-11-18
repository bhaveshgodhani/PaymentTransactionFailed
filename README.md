# Commercepundit_PaymentTransactionFailed

**Version:** 1.1.0

Magento 2 fraud guard for checkout payment failures and bot payment probes (guest and logged-in customers).

## Purpose

- Log failed payment attempts (IP, email, quote, path, reason) in `payment_transaction_failed`
- Block IPs that exceed failure limits within a configurable time window
- Use stricter limits for payment-only IPs (bots) vs IPs that also browsed catalog/CMS pages
- Optionally block high-confidence bot/probe patterns on first attempt
- Reduce admin email flood from Magento “Payment Transaction Failed” notices (suppress for bots / when blocked)
- Optionally force Magento native order rate limiting (`sales/backpressure`) on 2.4.7+
- Admin grid to review and delete failed attempts (including delete-by-IP from config)

Fail-open design: if this module errors, checkout continues as Magento core.

## Admin

- **Config:** Stores → Configuration → Commercepundit → Payment Transaction Failed
- **Grid:** Sales → Payment Transaction Failed (menu ACL required)

## Enable

```bash
php bin/magento module:enable Commercepundit_PaymentTransactionFailed
php bin/magento setup:upgrade
php bin/magento cache:flush
```
