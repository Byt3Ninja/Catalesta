# Geidea Payment Integration

## Integration Role

Geidea is the primary self-service payment gateway for SaaS subscriptions.

The implementation must remain behind provider interfaces so enterprise manual billing and future gateways can coexist.

## Provider Interfaces

- BillingProvider
- PaymentCheckoutProvider
- RecurringPaymentProvider
- PaymentMethodProvider
- RefundProvider
- TransactionQueryProvider
- PaymentWebhookVerifier

## Recommended Initial Flow

Use Geidea Hosted Payment Page for the first customer-authorized payment.

```text
Tenant selects plan
→ Platform creates internal pending subscription
→ Platform creates Geidea subscription
→ Platform creates Geidea payment session
→ Customer completes HPP checkout
→ Geidea callback/webhook received
→ Signature and payload verified
→ Payment and subscription synchronized
→ Entitlements activated
```

## Recurring Modes

Support provider mapping for:

- recurring automatic payment
- recurring payment link

The platform's domain model must not depend on provider-specific names.

## Data to Store

- provider customer ID
- provider subscription ID
- provider agreement ID
- provider order ID
- provider transaction ID
- masked payment-method summary
- subscription occurrence ID
- next occurrence date
- provider status
- signature verification result
- raw payload reference
- reconciliation timestamp

Do not store raw card numbers, CVV, or unmasked card data.

## Regional Endpoint Configuration

Configuration must distinguish environments and countries.

Example variables:

```env
GEIDEA_ENVIRONMENT=test
GEIDEA_REGION=EG
GEIDEA_API_BASE_URL=
GEIDEA_MERCHANT_PUBLIC_KEY=
GEIDEA_API_PASSWORD=
GEIDEA_CALLBACK_SECRET=
GEIDEA_HPP_RETURN_URL=
GEIDEA_HPP_CALLBACK_URL=
```

Do not hardcode endpoint URLs inside domain code.

## Signature Handling

- isolate request-signature generation in the Geidea adapter
- isolate response-signature verification
- use constant-time comparison
- log verification outcome without secrets
- reject invalid signatures
- preserve raw webhook payload for audit
- use idempotency based on provider event, order, transaction, or occurrence identifiers

## Webhooks and Callbacks

Support:

- success
- failure
- cancellation
- recurring occurrence status
- payment retry updates
- refund
- void
- subscription cancellation
- transaction reconciliation

Browser return is not authoritative. Server-to-server notification and transaction query are authoritative.

## Reconciliation

Implement scheduled reconciliation for:

- pending payments
- missed callbacks
- mismatched statuses
- recurring occurrence failures
- duplicate notifications
- refunds and voids
