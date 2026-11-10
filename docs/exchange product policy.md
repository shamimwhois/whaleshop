# Product Exchange Policy

> Mobile repair eCommerce — `hrmobilerepair`
> Business rules governing customer product exchanges.

---

## 1. Eligibility

### 1.1 Customer eligibility
- The order must be **DELIVERED** (`OrderStatus::DELIVERED`).
- The order must **not** already have an exchange request; **one exchange request per order**.
- The customer must own the order (frontend endpoints are scoped to `auth()->user()->id`).

### 1.2 Item eligibility
- Only line items flagged **refundable** (`is_refundable`) can be exchanged.
  Non-refundable items are shown as *Not Refundable* and cannot be selected.
- Exchanged quantity must not exceed the ordered quantity (`order_quantity`).

### 1.3 Reason
- A valid, **active** exchange reason (`exchange_reasons.status = Status::ACTIVE`) is required.
- The customer may add an optional free-text note (max 5000 chars).
- Optional supporting images (jpg/jpeg/png, ≤ 2 MB each).

---

## 2. Replacement product rules

- The replacement must be a **catalog product** (any active product).
- The customer chooses the replacement and quantity per exchanged line.
- **Price difference** is computed per line and summed:
  - `lineDiff = exchange_product_total − returned_total`
  - `exchange_price_difference = Σ lineDiff`

| Scenario | `exchange_price_difference` | Effect on acceptance |
|---|---|---|
| Replacement more expensive | **positive** | Customer pays the difference (no auto-charge; recorded for admin action) |
| Replacement equal price | `0` | No financial movement |
| Replacement cheaper | **negative** | `|diff|` credited to the **customer wallet** |

---

## 3. Wallet credit policy

On **acceptance** of an exchange request, the wallet is credited **only when all** of the
following hold:

1. `exchange_price_difference < 0` (the replacement is cheaper), and
2. the original order's `payment_method !== 1` (i.e. not a POS/cash method — mirrors Return & Refund), and
3. the site setting `site_is_return_product_price_add_to_credit` equals `Ask::YES`.

The credited amount is `abs(exchange_price_difference)`, added to `user.balance`.

> Rationale: this reuses the exact gate and semantics already used by the Return & Refund
> wallet credit so the two modules behave consistently.

No payment-gateway refunds are issued for exchanges.

---

## 4. Status lifecycle

| Status | Value | Set by | Meaning |
|---|---|---|---|
| `PENDING` | 5 | customer (on submit) | Awaiting admin decision |
| `ACCEPT` | 10 | admin | Approved; wallet credit applied if eligible |
| `REJECTED` | 15 | admin (reason required) | Declined; reason stored in `reject_reason` |

Transitions are performed through `POST admin/product-exchange/change-status/{id}` with an
`OrderStatusRequest` payload. Rejection additionally requires `reason` (`required|max:700`).

State machine:
```
PENDING ──accept──▶ ACCEPT
   └─────reject──▶ REJECTED
```
(Accepted/rejected requests are terminal; the customer must create a new request for a new
order, since one request per order is enforced.)

---

## 5. Data retention & attachments

- Exchange requests and their line items are retained for reporting/export.
- Uploaded images live in the media-library `exchange` collection on `ProductExchange`,
  with an auto-generated `thumb` conversion (168×180).
- `user` relations use `withTrashed()` so historical exchanges remain readable after a
  customer account is soft-deleted.
- `product` / `exchangeProduct` relations use `withTrashed()` so exchanges survive product
  soft-deletes.

---

## 6. Permissions & access control

| Surface | Gate |
|---|---|
| Admin exchange list/show/change-status/export | `permission:product-exchanges` |
| Admin exchange-reason CRUD | `permission:settings` |
| All admin exchange routes | `auth:sanctum` |
| Customer exchange routes | `auth:sanctum` + owner scoping in the service |

The `product-exchanges` permission is seeded by `PermissionTableSeeder` and granted to the
admin role automatically; a matching admin menu entry is seeded by `MenuTableSeeder`.

---

## 7. Export policy

`ProductExchangeExport` produces an `.xlsx` with columns:
`Order Serial No · Customer · Total Exchange Price · Date · Status`.
Exports honour the same filters as the admin list (no owner scoping).

---

## 8. Edge cases

| Case | Behaviour |
|---|---|
| Duplicate exchange for an order | Blocked: `trans('all.message.exchange_request_exist')` |
| Empty `products` payload | Validation fails (`products` required JSON) |
| Customer requests another user's exchange | `show` returns `[]` (frontend, `$auth = true`) |
| Replacement product soft-deleted later | Line still renders via `withTrashed()` |
| Wallet credit when replacement is dearer | Not applied (guard requires negative diff) |
| Gateway-refund expectations | Out of scope — policy is wallet-credit only |

---

## 9. Consistency with Return & Refund

This policy is deliberately aligned with the existing **Return & Refund** policy:

| Concern | Return & Refund | Product Exchange |
|---|---|---|
| Status values | 5 / 10 / 15 | 5 / 10 / 15 |
| One per order | yes | yes |
| Reject requires reason | yes | yes |
| Wallet credit gate | `payment_method !== 1` **and** `site_is_return_product_price_add_to_credit === Ask::YES` | same |
| Reason table | `return_reasons` | `exchange_reasons` |
| Media collection | `return` | `exchange` |

