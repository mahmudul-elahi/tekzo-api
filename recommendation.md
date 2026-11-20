# Tekzo API — Recommended Changes

This document is a work order for an AI coding agent. Each item states the problem, where it is, what to change, and how to verify it. Work through items in priority order (P0 → P3). Do one item (or one tightly related group) per commit.

## ⚠️ HARD RULE: do not change API responses

The owner does **not** want any API response changed. This rule overrides every item below. If an item seems to need a response change, do not do it. Mark the item blocked and ask the owner.

For every existing endpoint, keep all of the following exactly as it is today:

- **JSON structure:** the wrapper (`{message, data}` / `{data}`), key names, key casing (some endpoints use `camelCase`, others `snake_case` — keep each as is), nesting, ordering of keys, value types (string vs int vs float vs null), and pagination format.
- **Messages:** the text of success and error messages, including typos (e.g. "Invalid your coupon", "Incorrect your password").
- **Status codes** of normal (non-attack) flows.
- **Redirect URLs** and their query parameters (payment success/cancel/fail/bKash callback redirects to the frontend).
- **Route URLs, HTTP methods and request field names.**
- **PDF invoice content and layout.**

Allowed, because they only affect abusive or invalid requests and were not working responses before:

- `429 Too Many Requests` from new rate limits (P0-4).
- `404`, using the existing `responseMessage('... not found', 404)` style, when a customer accesses another customer's resource (P0-2). A request that currently crashes with a 500 may return a proper error instead.
- Rejecting invalid uploads with a `422` in the existing validation error format (P0-7).

How to enforce it: before refactoring any endpoint, write a feature test that captures the current response with `assertExactJson` (or `assertJsonStructure` + specific value assertions for dynamic fields like timestamps). The test must still pass unchanged after the refactor.

## Project context (read first)

- Laravel **13** (just upgraded from 12), PHP 8.5, PHPUnit 12, Sanctum 4, Scout + Meilisearch, spatie/laravel-medialibrary, DomPDF.
- This is a JSON **storefront API** consumed by a separate frontend (`config('services.front_base_url')`). Customers are `App\Models\Contact\Contact`, authenticated with Sanctum bearer tokens on the `customer` guard. Routes: `routes/api.php`, `routes/customer/*.php`.
- **The database schema appears to be owned by another application (admin panel).** Only 28 migrations exist and they only *alter* tables (products, orders, contacts etc. are created elsewhere). Do **not** create migrations that recreate or drop existing tables. New columns/tables are fine but must be called out in the commit message so the admin app owner knows.
- Follow `AGENTS.md` in the repo root (Laravel Boost guidelines): explicit return types, curly braces always, PHPDoc over inline comments, `php artisan make:*` for new files, run `vendor/bin/pint --dirty --format agent` after PHP changes, use PHPUnit (not Pest).
- Existing response helpers live in `app/Helpers/Global/ResponseHelper.php` (`responseMessage()`, `successResponse()`, `errorResponse()`). Keep the existing response shape (`{message, data}` / `{data}`) — the frontend depends on it.
- Do not add or upgrade Composer/NPM dependencies unless an item says to.
- API docs are already installed: **dedoc/scramble** (`config/scramble.php`). UI at `/docs/api`, spec at `/docs/api.json`. See section P3 for doc-quality follow-ups.

## Items needing a product/owner decision before coding

Mark these as blocked and ask the human owner; do not guess:

1. **P0-1**: the full fix needs a new token in responses and redirect URLs, which the hard rule forbids. Implement only the parts that don't change responses (rate limiting, logged-in scoping) and ask the owner before doing anything else.
2. **P0-6** — confirm whether the admin app marks pre-owned devices sold / adjusts stock when an order is placed or confirmed. If it does, only the "re-check at checkout" part applies.
3. **P1-2** — confirm the intended discount semantics (is care charge applied before or after item discount?). Logged-in cart (`CartService`) and guest buy-now (`BuyNowController`) currently disagree.

---

## P0 — Security and money bugs (fix first)

### P0-1. Public invoice / order endpoints expose customer PII by guessable invoice number

- **Where:** `app/Http/Controllers/Invoice/InvoiceDownoloadController.php` (`invoiceDownload`, `recentOrder`), routes `GET api/v1/customer/invoice-download` and `GET api/v1/customer/recent-order` (unauthenticated group in `routes/customer/index.php`). Invoice numbers are generated in `BuyNowController::generateInvoiceNumber()` and `CheckoutController::checkout()` as `ORD-YYYYMMDD-<max(id)+1 zero-padded>`.
- **Problem:** Invoice numbers are sequential and predictable. Anyone can iterate them and download every customer's invoice PDF (name, phone, email, address, items) or the `recent-order` JSON (full order + contact + address + order count).
- **Fix, allowed now (no response change):**
  1. Rate-limit both public endpoints and `order-tracking` hard per IP (see P0-4). This makes enumeration slow without changing any response.
  2. Make new invoice numbers harder to guess *without changing their format*. **BLOCKED:** the frontend or admin app may parse the format, so ask the owner first.
- **Fix, BLOCKED (changes responses/URLs, needs owner approval):** add an unguessable `public_token` column to orders, require it on both endpoints, and return it in the place-order responses and payment redirect URLs. Write this up as a proposal only. Do not implement it.
- **Acceptance:** Feature tests: the endpoints return exactly the same JSON/PDF as before for valid requests, and return 429 once the rate limit is exceeded.

### P0-2. Customer address endpoints have no ownership check (IDOR)

- **Where:** `app/Http/Controllers/Customer/Profile/AddressController.php` — `show`, `update`, `defaultAddress` use route-model binding on `CustomerAddress` with no `contact_id` check. `destroy` is **missing entirely** although `apiResource('addresses', ...)` registers `DELETE addresses/{address}` (currently a 500).
- **Also:** `CheckoutController::checkout()` validates `shipping_address_id` only with `exists:customer_addresses,id`, so a customer can check out with another customer's address.
- **Problem:** Any logged-in customer can read or overwrite any other customer's address by ID.
- **Fix:** Create a `CustomerAddressPolicy` (`php artisan make:policy`) with `view/update/delete` = `$address->contact_id === $contact->id`, and call `$this->authorize(...)` (or `Gate::authorize`) in each method, returning 404 rather than 403 if that matches existing style (`SellDeviceRequestController::authorizeOwner` returns 404). Implement `destroy` (or remove it from the resource route). In checkout, use `Rule::exists('customer_addresses', 'id')->where('contact_id', $customerId)`.
- Also stop reading the auth user in the controller constructor (`$this->customerId = auth()->guard('customer')->id()` in `__construct`). Read it inside each method instead; constructor-time auth is fragile.
- **Acceptance:** Feature tests for each action proving customer B gets 404 on customer A's address, and checkout rejects a foreign `shipping_address_id`.

### P0-3. Payment callbacks can overwrite already-paid orders

- **Where:**
  - `BuyNowController::cancel()` and `::failed()` (public `POST api/v1/customer/cancel` / `fail`): set `payment_status` to Canceled/Failed, `payable_amount = 0`, `due_amount = grand_total` **unconditionally**, even when the SSLCommerz IPN already marked the order Successful.
  - `App\Services\Bkash\BkashService::callback()` (public `GET api/v1/customer/bkash-callback`): no status guard. A replayed `status=success` callback re-runs `execute`, which bKash rejects for an already-executed payment, and then `markFailed()` overwrites a successful order. A replayed `status=cancel`/`failure` likewise overwrites it.
  - `SSLCommerzPaymentService::processCallback()` checks status, but without a row lock, so the IPN and a concurrent callback can both process.
- **Fix:**
  1. In every callback handler, only transition from `Initiated` (or `Pending`) state. If the order is already `Successful`/`PartiallyPaid`, do not change it and redirect to the **existing** success URL, using exactly the format already used (`/order/successful?invoice=...` for SSLCommerz, `/order/successful?invoice_number=...` for bKash). Do not change any redirect URL format.
  2. Wrap the read-check-write in `DB::transaction` with `Order::lockForUpdate()` re-fetching the order inside the transaction.
  3. For SSLCommerz cancel/fail, optionally validate the request with `SslCommerzNotification` hash verification (`verify_sign`), or simply never let these browser redirects change payment state and rely on the IPN (preferred).
- **Acceptance:** Tests (fake the gateway HTTP with `Http::fake()` / mock `BkashPaymentHandler`) showing: a cancel/fail/bKash-cancel/bKash-failure callback on a Successful order leaves it Successful; a duplicate bKash success callback leaves it Successful with exactly one `OrderPaymentHistory` row.

### P0-4. No rate limiting anywhere; OTPs are brute-forceable → account takeover

- **Where:** No `throttle` middleware or `RateLimiter` is defined anywhere. Affected: `routes/customer/auth.php` (login, register, forgot-password, verify-otp, confirm-password), guest OTP and buy-now routes in `routes/customer/index.php`, `order-tracking`, `product-verify`, `contacts`, `job-apply`.
- **Problem:** `ForgotPasswordController` uses a 6-digit OTP valid for 3 minutes with unlimited attempts, so an attacker can brute-force it and reset any customer's password. Login is open to credential stuffing. OTP endpoints can be used to spam SMS (cost) and email.
- **Fix:**
  1. Define named limiters in `AppServiceProvider::boot()` with `RateLimiter::for(...)`, e.g. `auth` (5/min per IP+email), `otp-send` (3/10min per phone/email + per IP), `otp-verify` (5 attempts per identifier, then invalidate the code), `public-write` (contact, job-apply, buy-now). Apply them with `->middleware('throttle:<name>')` on the routes.
  2. Add an `attempts` counter to verification codes (or track it in the rate limiter) and delete the code after N failures.
  3. Add a general `api` limiter (e.g. 120/min per IP) to the `api` middleware group in `bootstrap/app.php`.
- **Acceptance:** Feature tests asserting a 429 after the limit for login, forgot-password, verify-otp and gust-phone-verify-request.

### P0-5. Guest buy-now: percent discounts are subtracted as currency

- **Where:** `BuyNowController::processCartItems()`, normal-product branch: `$discountTotal += $offer['discount_amount'] * $quantity;`
- **Problem:** When `discount_type` is `percent`, `discount_amount` is a percentage (e.g. `10`), but it is subtracted as 10 BDT per unit. A 10%-off 100,000 BDT phone is charged 99,990 instead of 90,000. The guest total also diverges from the logged-in cart total (`CartService::recalculate()` handles percent correctly).
- **Fix:** Compute the per-line discount amount in currency (`percent` → `price * qty * pct / 100`, `fixed` → `amount * qty`) and sum that. Better: extract a shared pricing service (see P1-2) used by `BuyNowController`, `CheckoutController`, `CartService` and coupon checks.
- **Response note:** the response *shape* must stay identical. Only the numeric `grand_total` value changes for orders with percent discounts, because it is currently wrong. Confirm with the owner before shipping, since customers will see different (correct) totals.
- **Acceptance:** Unit tests for the pricing calculation covering percent, fixed, no discount, care charge, and pre-owned items; feature test for `gust-buy-now` asserting `grand_total` and the unchanged response structure.

### P0-6. Inventory is never reserved/decremented by this API (oversell risk)

- **Where:** `BuyNowController::processCartItems()` and `CartController::store()` lock `PreOwned` rows with `lockForUpdate()` and check `is_sold = false`, but nothing ever sets `is_sold = true`. `ProductVariant.stock` is a boolean and is never changed. `CheckoutController::checkout()` does not re-check stock or `is_sold` at all.
- **Problem:** The same pre-owned device can be sold to multiple customers. The lock does nothing because the row is never updated.
- **Fix (after owner confirms, see decisions above):** In the order-creation transaction, mark pre-owned items sold (or reserved, released on payment failure/cancel). In `checkout`, re-validate each cart item's availability and current price before creating the order, and fail with a clear 400 if anything changed.
- **Acceptance:** Test that two sequential orders for the same pre-owned device: first succeeds, second gets 400.

### P0-7. Chunked image upload accepts arbitrary content and lets users overwrite each other's chunks

- **Where:** `SellDeviceRequestController::uploadImage()`.
- **Problems:** (a) `identifier` is global, not namespaced per customer, so another user sending the same identifier can inject or overwrite chunks. (b) No per-chunk size limit, and `total_chunks` is unbounded, so disk-filling is possible. (c) The type check uses only the client-supplied `filename` extension. The merged file's real content is never checked before it goes to the public S3/CloudFront bucket.
- **Fix:** Prefix the chunk dir with the customer id (`{contact_id}/{identifier}`). Add `max:` on `file` (e.g. 2048 KB per chunk) and `max:` on `total_chunks` (e.g. `ceil(MAX_IMAGE_SIZE / chunk size)`). After merging, verify the content with `finfo`/`getimagesize()` against an allowed MIME list and derive the stored extension from the detected MIME, not the client filename.
- Same pattern in `CareerApplieController::store()`: the CV check uses `getClientOriginalExtension()`. Use `mimes:pdf,doc,docx` / `mimetypes:` rules instead.
- **Acceptance:** Feature tests with `Storage::fake()`: a PHP/HTML payload named `x.jpg` is rejected; chunks from customer B cannot affect customer A's upload.

### P0-8. Guest checkout attaches orders to existing accounts by phone OR email

- **Where:** `BuyNowController::resolveContact()` — `where('email', ...)->orWhere('phone', ...)->first()`.
- **Problem:** A guest who enters someone else's phone number gets their order attached to that person's existing contact/account. The order then shows in the victim's order history, and the "OTP verified" phone (`GustPhoneController`) is never actually required by `gustBuyNow`.
- **Fix:** Either require a verified phone OTP for guest checkout (persist a short-lived "phone verified" token from `GustPhoneController::phoneVerify` and require it in `gustBuyNow`), or match the contact on **both** email and phone and otherwise create a new guest contact. Never attach guest orders to a contact that has a password (registered account) without verification.

---

## P1 — Correctness and robustness

### P1-1. Invoice numbers are race-prone

`max('id') + 1` (in both `BuyNowController` and `CheckoutController`) produces duplicates under concurrent orders. Generate the invoice number **after** insert from the real `$order->id` (in the same transaction), or use a DB sequence/unique column with retry. Put it in one place (e.g. an `Order` model `creating`/`created` hook or an `InvoiceNumberGenerator` class). Make sure `orders.invoice_number` has a unique index (check with the schema owner).

### P1-2. Pricing logic is duplicated with inconsistent rules

Totals are calculated separately in `BuyNowController::processCartItems()` / `sumGuestItems()`, `CartService::recalculate()`, `CartController`, `CouponController`, `InvoiceDownoloadController::generatePdf()` and `OrderConfirmJob::handle()`, and they disagree. For example, care charge is computed before discount in buy-now and after discount in `CartService`, and `OrderConfirmJob` sums `discount_amount` without multiplying by quantity while the invoice controller does. Create one `App\Services\Pricing\OrderPricingService` (pure, unit-tested) that returns line totals, discounts, care charges, booking amount and grand total, and use it everywhere, including the invoice/email PDF data.

### P1-3. Coupons are not re-validated at checkout and usage limits race

`CheckoutController` trusts `cart.coupon_discount` stored when the coupon was applied. Expiry, usage limit and min purchase are not rechecked, and `CartService::recalculate()` does not recompute it. In `BuyNowController::resolveCoupon()` the `usages_count >= usage_limit` check is done without a lock, so concurrent orders can exceed the limit. There is also no per-customer usage limit check even though `CustomerCouponLog` stores `contact_id`. Revalidate the coupon inside the order transaction with `lockForUpdate()` on the coupon row.

### P1-4. Repay overwrites order data

`OrderController::repay()` writes the repay amount into `orders.total_booking_amount` so that `SSLCommerzPaymentService` validates against it. This corrupts the order's booking amount. Store the expected amount per payment attempt instead (e.g. on `OrderPaymentHistory` with status Initiated, or a `payment_attempts` table keyed by `tran_id`) and validate the IPN against that.

### P1-5. Password reset / OTP handling

- `ForgotPasswordController`: OTP stored in plain text (email-change OTP in `ProfileController` is hashed; be consistent and hash all OTPs). `verifyOtp` uses `!=`, `confirmPassword` uses `!==` (string vs. possibly int) — compare as strings with `hash_equals`, or `Hash::check` once hashed.
- After a password reset or password change, revoke the customer's other Sanctum tokens (`$contact->tokens()->delete()` except current).
- `login` and `forgot-password` use `exists:contacts,email` with a "No customer account found" message, which lets anyone enumerate registered emails. **BLOCKED** because fixing it changes response messages. List it for the owner, but don't change it. The rate limits in P0-4 reduce the risk.
- `config/sanctum.php` has `'expiration' => null`, so tokens never expire. Set a sensible expiration (e.g. 30 days) and schedule `sanctum:prune-expired`.
- Non-production environments use the fixed OTP `123456` (`app()->isProduction()` check). Make sure any publicly reachable staging uses `APP_ENV=production`, or gate this on an explicit `OTP_FAKE=true` env flag instead.

### P1-6. `OrderConfirmJob` swallows failures and writes to the public disk

It writes the invoice PDF to `storage/app/public/invoice_<invoice>.pdf` (publicly linkable while it exists) and catches all mail exceptions silently, leaving the file behind on failure. Generate the PDF in memory and attach it with `Attachment::fromData(...)`. Log the exception and let the job retry (`$tries`, `$backoff`). Also eager-load `orderLists.productCare.care` (currently N+1 inside the job).

### P1-7. Unbounded `per_page`

Many endpoints pass `request('per_page')` straight to `paginate()`: `OrderController::index`, `BlogController`, `PreOwnedController`, `OfferController`, `RefurbishedController`, `ProductController`, `CustomerReviewController`, `SellDeviceRequestController`, parts of `HomePageController`. `?per_page=100000` can take the server down. Clamp like `ShopController` already does (`min((int) $request->input('per_page', 12), 48)`), ideally via one shared helper.

### P1-8. Dead / duplicate payment code

`app/Jobs/ProcessBkashPayment.php` and `app/Jobs/ProcessSSLCommerzPayment.php` are not dispatched anywhere and duplicate (with different, buggier logic) what `BkashService`/`SSLCommerzPaymentService` do. Delete them once confirmed unused. `SSLCommerzPaymentService::checkTransactionStatus()` has its processing call commented out; either wire it into a scheduled reconciliation command for orders stuck in `Initiated` (recommended, since a missed IPN currently leaves orders unpaid forever) or remove it.

### P1-9. Exception handling leaks and inconsistencies

- `GeneralException` takes the HTTP status from the exception `code`, and several places throw it with a raw `$e->getMessage()` outside production. Fine for local use, but make sure `APP_DEBUG=false` and `APP_ENV=production` in production.
- `DB::rollBack()` is called in `catch` blocks after `DB::commit()` has already run (`BuyNowController::gustBuyNow`, `CheckoutController::checkout` — payment init happens after commit). Move gateway calls out of the try/transaction block or restructure with `DB::transaction(fn () => ...)` returning the order, then initiate payment.

---

## P2 — Code quality and maintainability

1. **Tests:** The suite is two example tests. Add feature tests for every P0/P1 fix as you go. Because the base schema comes from another app, generate a schema dump that tests can load: get a dump of the production schema (structure only) from the owner, put it at `database/schema/mysql-schema.sql` (or an SQLite-compatible equivalent), and use `RefreshDatabase`. Create factories for `Contact`, `Order`, `OrderList`, `Product`, `ProductVariant`, `PreOwned`, `CustomerAddress`, `Cart`, `CartItem`, `NewCoupon` (only `UserFactory` exists).
2. **Form Requests:** Most controllers validate inline (`$request->validate([...])`). Move these to Form Request classes (`php artisan make:request`), matching the existing ones under `app/Http/Requests/`. This also improves the generated API docs.
3. **API Resources:** Many endpoints build arrays by hand (`OrderController::index/orderDetail`, `InvoiceDownoloadController::recentOrder` returns `$order->toArray()`, which exposes every column). Moving them to Eloquent API Resources is optional. If you do it, the resource must produce **exactly the same output** (same keys, casing, types, nesting and wrapper, with no extra `data`/`links`/`meta` wrapping). Prove it with an `assertExactJson` test written *before* the change. Do not remove the extra columns that `recentOrder` exposes; list them for the owner instead.
4. **Fat controllers:** `HomePageController` (631 lines), `BuyNowController` (592), `ProductController` (564). Extract services (order placement, pricing, payment initiation) so web checkout and guest buy-now share code.
5. **Duplicated SMS code:** `sendSms()` is copy-pasted in `ProfileController` and `GustPhoneController`. Extract an `App\Services\Sms\ReveSmsService` (bind in the container, fake it in tests) and use `Http::timeout()`.
6. **Naming typos:** internal class and method names can be renamed safely: `GustPhoneController`, `InvoiceDownoloadController`, `Getway` namespace, `CareerApplieController`, `handelDiscountType`, `popupDailog`. **Do not** rename route URLs (`gust-buy-now`, `gust-check-coupon`, ...), request field names (`deparment`, `choos_reason`), response keys or messages. These are part of the frontend contract (see the hard rule).
7. **Remove commented-out code** blocks (e.g. in `BuyNowController`, `CheckoutController`, `ProcessBkashPayment`).
8. **Hardcoded values:** logo URLs in `InvoiceDownoloadController` and `OrderConfirmJob` (two different URLs) should come from config/settings. When moving them, keep the current URL value of each as its default so the PDFs don't change. Leave the root route messages (`'Welcome to Salamat Innovation'` / `'Welcome to Tekzo'`) as they are.
9. **Enum usage:** order `status = 7` is hardcoded in `CustomerReviewController`. Use `StatusEnum::Delivered->value` (or whatever case 7 is).
10. **Models:** move `$casts` properties to the `casts()` method (Laravel 11+ convention). Mark `password` as `'hashed'` cast on `Contact` so manual `Hash::make` calls can be dropped.
11. **Composer:**
    - `composer.lock` is in `.gitignore`. For an application this should be committed so deploys are reproducible. Remove it from `.gitignore` and commit it.
    - `"php": "^8.2"` should be `"^8.3"` (Laravel 13's minimum).
12. **DomPDF `isRemoteEnabled`:** enabled for invoice generation. Keep it only if remote images are needed. Otherwise embed the logo locally (like `taka.png`) and disable remote fetching.

## P3 — Performance and operations

1. **Caching:** nothing in the app uses the cache. Public catalog endpoints (`settings`, `category-tree`, `brands`, `sliders`, `home-categories`, `policies`, `faqs`, `outlets`, `seos/{slug}`) are good candidates for `Cache::remember` with a short TTL, using **arrays, not models** (`config/cache.php` now has `serializable_classes => false`, so cached Eloquent objects won't unserialize). Add invalidation or keep TTLs short, since the admin app writes this data.
2. **N+1 queries:** enable `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider::boot()` and fix whatever it surfaces (likely in `HomePageController`, product resources, `OrderConfirmJob`).
3. **Queue:** `QUEUE_CONNECTION=database` with `OrderConfirmJob` doing PDF + mail. Make sure a worker runs in production (Supervisor/systemd) and failed jobs are monitored.
4. **Health:** `/up` exists. Consider adding gateway credential checks to a scheduled command, not to `/up`.

### API documentation (Scramble) follow-ups

Scramble is installed and configured (`config/scramble.php`): title "Tekzo API", bearer security applied automatically to `auth:sanctum` routes via `MiddlewareAuthSecurityStrategy`. Current output: 114 paths / 125 operations, 45 secured. To improve it:

1. **Access in non-local environments:** by default `/docs/api` is only reachable when `APP_ENV=local` (`RestrictedDocsAccess`). To expose it on staging/production, define a `viewApiDocs` gate in `AppServiceProvider::boot()` (e.g. allow by IP allowlist or an env flag). Do not make production docs fully public without the owner's approval.
2. **Response schemas:** Scramble infers responses from return types and API Resources. Endpoints that return `successResponse([...])` / `responseMessage(...)` arrays document poorly. Improve the docs **without changing what the endpoint returns**: add explicit return types (`JsonResponse`), PHPDoc `@response` annotations, and Form Requests (P2-2, which changes validation location only, not the 422 error format). Never change a response just to make the docs look better.
3. **Grouping and descriptions:** add `#[Group('Cart')]`-style attributes (`Dedoc\Scramble\Attributes\Group`) per controller and short PHPDoc summaries on each action. Scramble uses the first PHPDoc line as the operation summary.
4. **Known warnings** (harmless, rules depending on runtime values): `ProfileController::emailVerifyRequest`, `ProfileController::phoneVerifyRequest`, `OrderController::repay`. Moving those rules into Form Requests resolves them.
5. **Webhooks/redirect endpoints** (`webhook/sslcommerz/ipn`, `customer/success|cancel|fail`, `customer/bkash-callback`) are gateway-facing, not frontend-facing. Exclude them from docs with `#[ExcludeRouteFromDocs]` or document them under a separate "Payment callbacks" group.
6. Run `php artisan scramble:analyze` in CI to catch documentation regressions.

---

## Definition of done for each item

- Code follows `AGENTS.md` conventions; `vendor/bin/pint --dirty --format agent` run.
- New/updated feature tests pass: `php artisan test --compact`.
- **Zero changes to API responses**: JSON shape, keys, types, messages, status codes of normal flows, redirect URLs, route URLs, request fields, PDF output. See the hard rule at the top. There are no exceptions beyond the ones listed there. When in doubt, stop and ask the owner.
- Every refactored endpoint has an `assertExactJson`-style test written before the change that still passes after it.
- Any new DB column/table is listed in the commit message for the admin-app owner.
