# Product Exchange — Feature Specification

> Laravel 12 (PHP 8.5) + Vue 3 (SFC / Vuex / vue-router) + Vite + TailwindCSS
> Mobile repair eCommerce — `hrmobilerepair`
> Canonical reference implementation: **Return & Refund** vertical slice.

---

## 1. Overview

**Product Exchange** lets a customer swap an item from a delivered order for another
catalog product. The customer picks the order item(s) to give back, selects the
replacement product(s) from the catalog, states a reason, and submits. An admin then
accepts or rejects the request. When the replacement is cheaper than the returned item,
the difference is credited to the customer wallet on acceptance.

The feature is a 1:1 mirror of the **Return & Refund** module across every layer
(database → models → enums → requests → resources → services → controllers → routes →
Vuex → Vue components → router → seeders / permissions / menu / i18n).

---

## 2. Goals & Non-goals

### Goals
- Customers can request an exchange for a delivered order (one exchange request per order).
- Each exchange line links an original order item to a chosen replacement product and quantity.
- Price difference is computed and stored per request.
- Admin can accept or reject (with a mandatory reason) an exchange request.
- Cheaper replacement → difference credited to wallet (subject to site setting).
- Admin list + show UI, customer list + detail + request UI.
- Excel export of exchange requests.

### Non-goals
- No physical stock movement / inventory deduction on exchange (tracking only, matching Return & Refund).
- No payment-gateway refunds on exchange (wallet credit only).
- No multi-reason selection per request (single reason, like Return & Refund).

---

## 3. Domain model

### 3.1 Tables

#### `exchange_reasons`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| title | string | required |
| status | tinyint | `Status::ACTIVE` / `Status::INACTIVE` |
| details | text nullable | |
| timestamps | | |

#### `product_exchanges`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| exchange_reason_id | FK → exchange_reasons | |
| note | text nullable | customer note |
| reject_reason | text nullable | filled on rejection |
| order_id | FK (index only) | |
| user_id | FK (index only) | |
| order_serial_no | unsignedBigInteger | |
| exchange_price_difference | decimal(19,6) default 0 | negative = refund to wallet |
| status | tinyint | `ExchangeOrderStatus` |
| timestamps | | |

#### `product_exchange_products`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| product_exchange_id | FK (index only) | |
| product_id | FK (index only) | original (returned) item |
| variation_id | unsignedBigInteger nullable | |
| variation_names | string nullable | |
| quantity | bigint default 1 | returned quantity |
| price | decimal(19,6) default 0 | unit price of original |
| total | decimal(19,6) default 0 | price × quantity |
| exchange_price | decimal(19,6) | mirrors `total` (kept for parity) |
| exchange_product_id | FK nullable | chosen replacement product |
| exchange_product_quantity | integer default 1 | |
| exchange_product_price | decimal(19,6) default 0 | replacement unit price |
| exchange_product_total | decimal(19,6) default 0 | replacement price × qty |
| user_id | FK (index only) | |
| timestamps | | |

> **Price difference formula** (per line):
> `lineDiff = exchange_product_total − total`
> `exchange_price_difference = Σ lineDiff`
> A **negative** total means the replacement costs less → wallet credit of `|diff|` on accept.

### 3.2 Enums
`App\Enums\ExchangeOrderStatus` (parity with `ReturnOrderStatus`):
```php
const PENDING  = 5;
const ACCEPT   = 10;
const REJECTED = 15;
```

### 3.3 Eloquent models
| Model | Table | Notes |
|---|---|---|
| `App\Models\ExchangeReason` | `exchange_reasons` | `hasMany(ProductExchange)` |
| `App\Models\ProductExchange` | `product_exchanges` | `implements HasMedia` + `InteractsWithMedia` (collection `exchange`, `thumb` conversion 168×180); relations `exchangeProducts`, `order`, `exchangeReason`, `user` (withTrashed); accessor `images` |
| `App\Models\ProductExchangeProduct` | `product_exchange_products` | relations `product` (withTrashed), `exchangeProduct` (withTrashed) |

`App\Models\Order` gains:
```php
public function productExchange(): HasOne
{
    return $this->hasOne(ProductExchange::class);
}
```

`App\Http\Resources\OrderDetailsResource` exposes `'product_exchange' => !$this->productExchange`
so the customer order-detail page can show/hide the *Exchange Request* button.

---

## 4. HTTP API

All routes are registered in `routes/api.php`. Mutating/read endpoints are protected by
`auth:sanctum`; the admin controller additionally enforces `permission:product-exchanges`.

### 4.1 Admin — `admin/product-exchange`
| Method | URI | Action | Permission |
|---|---|---|---|
| GET | `/api/admin/product-exchange` | `index` (paginated list) | `product-exchanges` |
| GET | `/api/admin/product-exchange/show/{productExchange}` | `show` (details) | `product-exchanges` |
| POST | `/api/admin/product-exchange/change-status/{productExchange}` | `changeStatus` | `product-exchanges` |
| GET | `/api/admin/product-exchange/export` | `export` (xlsx) | `product-exchanges` |

### 4.2 Admin — `admin/setting/exchange-reason`
| Method | URI | Action | Permission |
|---|---|---|---|
| GET | `/api/admin/setting/exchange-reason` | `index` | `settings` |
| GET | `/api/admin/setting/exchange-reason/show/{exchangeReason}` | `show` | `settings` |
| POST | `/api/admin/setting/exchange-reason` | `store` | `settings` |
| PUT/PATCH | `/api/admin/setting/exchange-reason/{exchangeReason}` | `update` | `settings` |
| DELETE | `/api/admin/setting/exchange-reason/{exchangeReason}` | `destroy` | `settings` |

### 4.3 Frontend — `frontend/*` (auth:sanctum)
| Method | URI | Action |
|---|---|---|
| GET | `/api/frontend/exchange-order` | list own exchanges |
| POST | `/api/frontend/exchange-order/request/{order}` | create request |
| GET | `/api/frontend/exchange-order/show/{productExchange}` | own exchange details |
| GET | `/api/frontend/exchange-reason` | active exchange reasons |

### 4.4 Request payload — create exchange
`ProductExchangeRequest`:
```php
'exchange_reason_id' => ['required', 'numeric'],
'note'               => ['nullable', 'string', 'max:5000'],
'order_id'           => ['required', 'numeric'],
'order_serial_no'    => ['required', 'string'],
'products'           => ['required', 'json'],
'image[]'            => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
```
`products` is a JSON array of items, each shaped:
```json
{
  "product_id": 12,
  "product_name": "Screen Assembly",
  "quantity": 1,
  "price": 120.0,
  "total": 120.0,
  "has_variation": false,
  "variation_id": null,
  "variation_names": null,
  "exchange_product_id": 45,
  "exchange_product_quantity": 1,
  "exchange_product_price": 95.0,
  "exchange_product_currency_price": "95.00"
}
```

### 4.5 Status change payload — accept/reject
Uses the shared `OrderStatusRequest`. Rejection additionally validates:
```php
'reason' => 'required|max:700'
```

---

## 5. Service layer

### 5.1 `App\Services\ProductExchangeService`
| Method | Responsibility |
|---|---|
| `list(PaginateRequest $request, $auth = false)` | filterable/paginated list; `$auth=true` scopes to the logged-in user; filters `order_serial_no`, `user_id`, `status`, `from_date`/`to_date`, `excepts` |
| `store(ProductExchangeRequest $request)` | guards one-exchange-per-order (`trans('all.message.exchange_request_exists')`), runs in a DB transaction: creates `ProductExchange`, creates `ProductExchangeProduct` rows, accumulates `exchange_price_difference`, attaches media to the `exchange` collection |
| `show(ProductExchange $productExchange, $auth = false)` | returns the model, or `[]` when `$auth=true` and it does not belong to the user |
| `changeStatus(ProductExchange $productExchange, OrderStatusRequest $request)` | on `REJECTED` requires `reason`; on `ACCEPT`, when `exchange_price_difference < 0`, payment method ≠ 1 and `site_is_return_product_price_add_to_credit === Ask::YES`, credits `abs(diff)` to `user.balance`; then persists status |

### 5.2 `App\Services\ExchangeReasonService`
Standard CRUD + list (`title`, `status`, `details` filters), identical in shape to
`ReturnReasonService`. Throws via `QueryExceptionLibrary::message()`.

---

## 6. Controllers & resources

| Layer | Class |
|---|---|
| Admin controller | `App\Http\Controllers\Admin\ProductExchangeController` |
| Admin reason controller | `App\Http\Controllers\Admin\ExchangeReasonController` |
| Frontend controller | `App\Http\Controllers\Frontend\ProductExchangeController` |
| Frontend reason controller | `App\Http\Controllers\Frontend\ExchangeReasonController` |
| List resource | `App\Http\Resources\ProductExchangeResource` |
| Detail resource | `App\Http\Resources\ProductExchangeDetailsResource` |
| Line resource | `App\Http\Resources\ProductExchangeProductResource` |
| Reason resource | `App\Http\Resources\ExchangeReasonResource` |
| Export | `App\Exports\ProductExchangeExport` (Maatwebsite Excel) |

All controllers wrap work in `try/catch` and return
`response(['status' => false, 'message' => $exception->getMessage()], 422)` on failure,
matching the existing codebase convention.


---

## 7. Frontend (Vue 3)

### 7.1 Vuex store modules
`resources/js/store/index.js` registers:

| Module | File | Purpose |
|---|---|---|
| `productExchange` | `store/modules/productExchange.js` | admin list/show/changeStatus/export |
| `exchangeReason` | `store/modules/exchangeReason.js` | admin reason CRUD |
| `frontendProductExchange` | `store/modules/frontend/frontendProductExchange.js` | customer list/show/save |
| `frontendExchangeReason` | `store/modules/frontend/frontendExchangeReason.js` | customer reason list |

### 7.2 Enum
`resources/js/enums/modules/exchangeStatusEnum.js` → `{ PENDING: 5, ACCEPT: 10, REJECTED: 15 }`.

### 7.3 Components

#### Admin (`resources/js/components/admin/productExchanges/`)
| File | Role |
|---|---|
| `ProductExchangeComponent.vue` | shell with `<router-view>` + breadcrumb |
| `ProductExchangeListComponent.vue` | filterable table, status badges, export/print, pagination |
| `ProductExchangeShowComponent.vue` | detail view, accept/reject actions, attachment preview |
| `ProductExchangeReasonComponent.vue` | reject-reason modal (POST `change-status`) |

#### Customer (`resources/js/components/frontend/account/exchangeOrders/`)
| File | Role |
|---|---|
| `ExchangeOrdersComponent.vue` | list of own exchange requests |
| `ExchangeOrderDetailsComponent.vue` | detail: returned items ↔ replacement items, status, reject reason |
| `ExchangeOrderRequestComponent.vue` | request form: select order items, choose replacement product per line, reason, note, attachments |

### 7.4 Router
- `resources/js/router/modules/productExchangeRoutes.js` — admin routes
  (`admin.productExchange.list`, `admin.productExchange.show`), `permissionUrl: 'product-exchanges'`.
- `resources/js/router/modules/frontendRoutes.js` — account children:
  - `frontend.account.exchangeOrders` → `exchange-orders`
  - `frontend.account.exchangeOrder.details` → `exchange-order-details/:id`
  - `frontend.account.exchangeOrder.request` → `exchange-request/:id`
- `resources/js/router/index.js` imports and registers `productExchangeRoutes`.

### 7.5 Navigation
- `resources/js/components/frontend/account/AccountMenuComponent.vue` gains an
  **Exchange Orders** link (`menu.exchange_orders`).
- `resources/js/components/frontend/account/orderDetails/OrderDetailsComponent.vue` shows an
  **Exchange Request** button for delivered orders where `order.product_exchange` is truthy.

---

## 8. Seeders, permissions, menu & i18n

| Item | Change |
|---|---|
| `database/seeders/ExchangeReasonTableSeeder.php` | new; 4 default reasons (DEMO-gated), registered in `DatabaseSeeder` |
| `database/seeders/PermissionTableSeeder.php` | new permission `product-exchanges` (title *Product Exchanges*); admin role auto-grants all |
| `database/seeders/MenuTableSeeder.php` | new menu item *Product Exchanges* → `product-exchanges` (`menu.product_exchanges`) |
| `lang/{en,ar,bn}/exchangeOrderStatus.php` | new status translation files |
| `lang/{en,ar,bn}/all.php` | new keys `label.total_exchange_price`, `message.exchange_request_exist` |
| `resources/js/languages/{en,ar,bn}.json` | new `menu.*`, `label.*`, `button.*` keys for all exchange strings |

---

## 9. End-to-end flow

1. Customer opens a **delivered** order → clicks **Exchange Request**.
2. `ExchangeOrderRequestComponent` loads the order (`frontendOrder/show`), active reasons
   (`frontendExchangeReason/lists`) and the catalog (`frontendProduct/lists`).
3. Customer ticks order items, adjusts quantities, and picks a replacement product per line.
4. Submit → `POST frontend/exchange-order/request/{order}` (multipart; `products` JSON + images).
5. `ProductExchangeService::store` creates the request + lines and stores `exchange_price_difference`.
6. Admin sees it in **Product Exchanges**; opens detail; **Accept** or **Reject** (reason).
7. On **Accept**: if cheaper and eligible, wallet is credited `|diff|`;
   status → `ACCEPT` (10). On **Reject**: reason stored; status → `REJECTED` (15).
8. Customer sees the outcome in **Exchange Orders** (list + detail).

---

## 10. Acceptance criteria

- [x] Migrations create `exchange_reasons`, `product_exchanges`, `product_exchange_products`.
- [x] One exchange request per order enforced in the service.
- [x] Price difference computed and persisted; cheaper replacement credits the wallet on accept (gated by site setting + payment method).
- [x] Rejection requires a reason.
- [x] 13 API routes registered under `exchange`.
- [x] Admin list/show + customer list/detail/request UI built and compile.
- [x] `npm run build` succeeds with all exchange chunks emitted.
- [x] Permission `product-exchanges`, admin menu entry, seeders and i18n (en/ar/bn) added.

---

## 11. Testing notes

- **Backend:** `php artisan route:list --path=exchange` → 13 routes.
- **Frontend:** `npm run build` (Vite) → exchange component chunks in `public/build/assets`.
- **DB:** `php artisan migrate` then seed with `php artisan db:seed` on an empty database
  (the bundled `DatabaseSeeder` guards against re-seeding a populated DB).
- Manual smoke test: create an exchange from a delivered order, accept it, verify the
  wallet balance delta equals `|exchange_price_difference|` when the replacement is cheaper.


