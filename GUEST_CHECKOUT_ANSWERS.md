# Guest checkout: answers from the backend

**Date:** 2026-11-28
**Base URL:** `/api/v1`

These answers to the four guest-checkout questions come from reading the current API code. Each one gives a short answer, the details you need to build against, and anything still to be fixed on the backend.

| # | Question | Short answer |
|---|---|---|
| 1 | Does `gust-buy-now` accept Care, gifts and pre-owned devices? | ✅ **Yes.** It uses different field names, and it has backend pricing gaps (listed below) |
| 2 | Does `/customer/recent-order` work without a token? | ✅ **Yes, it's public.** ⚠️ It exposes too much data and will be locked down |
| 3 | Do SSLCommerz and bKash return guests to the success, failed and canceled pages? | ✅ **Yes, no login needed.** ⚠️ The query parameter names differ by gateway |
| 4 | Do guest coupons and order tracking work without a token? | ✅ **Yes**, with caveats |

---

## 1. Guest order: `POST /customer/gust-buy-now`

No token is needed. It's rate-limited to 10 requests per minute per IP.

### Request

```json
{
  "full_name": "Rahim Uddin",
  "email": "rahim@example.com",
  "phone": "01712345678",
  "district_id": 1,
  "area_id": 12,
  "address": "House 12, Road 5, Dhanmondi",
  "note": "Call before delivery",
  "payment_method": "cod",
  "booking_amount_only": 0,
  "coupon_code": "SAVE10",
  "is_agree": 1,
  "carts": [
    { "product_id": 10, "product_variant_id": 55, "quantity": 1, "care_id": 3, "gift_id": 7 },
    { "product_id": 21, "product_variant_id": 90, "quantity": 1, "pre_owned_id": 4, "care_id": 2 }
  ]
}
```

| Field | Rules |
|---|---|
| `full_name`, `email`, `phone`, `district_id`, `area_id`, `address` | Required. `email` must be real; disposable addresses are rejected. |
| `payment_method` | `cod`, `online` (SSLCommerz) or `bkash` |
| `is_agree` | Must be `1` |
| `booking_amount_only` | `0` or `1`. Optional. |
| `coupon_code`, `note` | Optional |
| `carts` | Required array of items |

### Care, gifts and pre-owned devices in each item

| Feature | Field name in **guest** `carts[]` | Supported? |
|---|---|---|
| Tekzo Care | **`care_id`** | ✅ Yes, the price is added to the line |
| Free gift | **`gift_id`** | ✅ Yes, saved on the order line |
| Pre-owned device | **`pre_owned_id`** | ✅ Yes, priced from the device. Quantity must be `1`. |

- **Use these exact names.** The signed-in cart uses different ones (`product_care_id`, `gift_product_variant_id`, `pre_owned_care_id`). Any other field name is **silently ignored**.
- **One `care_id` field for both kinds of care.** On a pre-owned line, `care_id` means a *pre-owned care* ID. On a normal line, it means a *product care* ID.
- **`product_id` is still required on pre-owned lines.** The backend takes the real product and variant from the device.

### Response

| `payment_method` | Response | What to do |
|---|---|---|
| `cod` | `{ "message": "Congratulations! ...", "data": { "invoice_number": "ORD-20261128-000123", "grand_total": 85000 } }` | Go to `/order/successful?invoice=<invoice_number>` |
| `online` | `{ "message": "success", "url": "<SSLCommerz page>" }` | Redirect the browser to `url` |
| `bkash` | `{ "message": "success", "url": "<bKash page>" }` | Redirect the browser to `url` |

For `online` and `bkash`, the invoice number is **not** returned here. You get it from the redirect after payment (see section 3).

### Phone OTP for guests

It's **optional** today. The order does not check whether the phone was verified. If you show OTP in the UI, it is only a front-end check:

- `POST /customer/gust-phone-verify-request` with body `{ "phone_number": "01712345678" }`
- `POST /customer/gust-phone-verify` with body `{ "phone_number": "01712345678", "code": "123456" }`

Outside production the code is always `123456`.

### ⚠️ Backend issues found (the backend team will fix these; nothing changes for the frontend)

- **Care price base:** Tekzo Care is priced on the price *before* the item discount, while the signed-in checkout uses the price *after* it. A guest can pay slightly more for the same basket with Care.
- **Item discounts on the order:** the guest order's `sub_total` doesn't account for item discounts, so the order totals don't add up the way signed-in orders do.
- **Booking amount of 0:** if `booking_amount_only: 1` is sent but the items have no booking amount, the payment starts at 0. **Frontend: only send `1` when the cart actually has a booking amount.**
- **Quantity cap:** there's no 10-per-item limit like the signed-in cart has. **Frontend: keep the same cap in the UI.**
- **No retry for failed payments:** guests can't retry a failed online payment. The retry endpoint only exists for signed-in customers.
- **No confirmation email for online payments:** online-paid orders (both flows) don't send the confirmation email. Only COD does.

---

## 2. Order success page: `GET /customer/recent-order?invoice_number=...`

**Public: no token needed.** It's rate-limited to 10 requests per minute per IP. It works the same for guest and signed-in orders.

```
GET /api/v1/customer/recent-order?invoice_number=ORD-20261128-000123
```

The response is `{ "data": { ...the full order, its items and the customer... } }`. If the invoice number is unknown, you get `404 { "message": "Order not found for the provided invoice number" }`.

### ⚠️ Planned change: please build for it now

Invoice numbers are easy to guess: `ORD-<date>-<order id>`. This endpoint currently returns the customer's **name, phone, address and payment details** to anyone who has the number. The backend will reduce it to what the success page needs, and may add a second check value. To make that change painless:

- **Only read** the invoice number, totals, payment method and status, and the items (name, variant, quantity, price) from this response.
- **Don't rely on** the customer's phone, email, address, `transaction_id`, `paymentId` or `additional_value`.

`GET /customer/invoice-download?invoice_number=...` (the PDF) is also public and will get the same protection.

---

## 3. SSLCommerz and bKash return URLs

**No login or session is needed.** Guest and signed-in orders go through the same callback code. After payment, the browser lands on the storefront at `FRONT_BASE_URL` (currently `https://salamatinnovation.com`):

| Gateway | Result | Storefront URL |
|---|---|---|
| SSLCommerz | Success | `/order/successful?invoice=ORD-…` |
| SSLCommerz | Cancelled | `/order/canceled?invoice=ORD-…` |
| SSLCommerz | Failed | `/order/failed` (no invoice) |
| bKash | Success | `/order/successful?invoice_number=ORD-…` ⚠️ |
| bKash | Cancelled | `/order/failed?invoice=ORD-…&cancel` ⚠️ (no `/order/canceled`) |
| bKash | Failed | `/order/failed?invoice=ORD-…&failure` |
| Either | Unknown or invalid payment | `/order/failed` |

### Frontend to-do

- [ ] On `/order/successful`, read the invoice from **`invoice` or `invoice_number`**. Both are in use.
- [ ] On `/order/failed`, treat a `cancel` query flag as "you cancelled the payment" (bKash cancels arrive here).
- [ ] On `/order/canceled` and `/order/failed`, the invoice may be missing. Show a generic message in that case.
- [ ] Fire purchase analytics **only once per invoice**, for example by remembering it in `sessionStorage`. The success page can be reloaded.

**Payment confirmation can lag.** For SSLCommerz, the success redirect can arrive *before* the payment is confirmed in the background, so the order may briefly still show "Initiated". If you show payment status, poll `recent-order` a couple of times, or show "Payment received, confirming…".

---

## 4. Guest coupons and order tracking

### Coupon check: `POST /customer/gust-check-coupon` (public)

```json
{
  "coupon_code": "SAVE10",
  "carts": [ { "product_id": 10, "product_variant_id": 55, "quantity": 1, "pre_owned_id": null, "care_id": null } ]
}
```

Response: `{ "data": { "coupon_code": "SAVE10", "sub_total": 90000, "discount_amount": 9000, "grand_total": 81000 } }`

- `grand_total` here **does not include shipping**. Add shipping in the UI.
- The coupon is **applied again for real** when `gust-buy-now` is called with `coupon_code`. Always send it with the order.
- ⚠️ **The final discount can differ from the quote.** At order time, the minimum-purchase check and the discount cap are measured *with* shipping, while this quote is *without* it. Show the discount from the order result where possible.
- ⚠️ **Backend issue:** one-per-customer coupons aren't enforced for guests yet. The backend team will fix this.

### Order tracking: `POST /order-tracking` (public, 10 requests per minute)

```json
{ "order_number": "ORD-20261128-000123" }
```

Response: `{ order_number, updated_at, status: { name, color } }`. This endpoint is safe: it returns no personal data.

---

## Summary for the frontend

1. Use **`care_id`, `gift_id` and `pre_owned_id`** in guest `carts[]`, with quantity `1` for pre-owned devices.
2. For `online` and `bkash`, redirect to the returned `url`. For `cod`, use `data.invoice_number`.
3. On the success page, accept **`invoice` or `invoice_number`**. Fire analytics once per invoice.
4. Treat **`/order/failed?…&cancel`** as a cancellation.
5. From `recent-order`, use only the order and item fields, not customer personal data.
6. Add shipping to the coupon quote yourself. Only send `booking_amount_only: 1` when the cart has a booking amount.
