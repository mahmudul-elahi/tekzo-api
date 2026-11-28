# Customer API changes: what the storefront needs to update

**Date:** 2026-11-28
**Branch:** `main`
**Base URL:** `/api/v1`

All endpoints return JSON. Send `Accept: application/json` on every request. Signed-in endpoints also need `Authorization: Bearer <access_token>`.

| # | Feature | Dashboard task | Frontend work |
|---|---|---|---|
| 1 | Log in with email **or** mobile number | n/a | Required |
| 2 | Phone-verified badge and verification flow | UPD-002 | Required |
| 3 | Secondary phone and address on the profile | UPD-004 | Required |
| 4 | Deliver to someone else (recipient on address) | UPD-005, UPD-007 | Required |
| 5 | One-call dashboard overview | UPD-001 | Recommended |
| 6 | Google / Facebook / Apple login and connections | UPD-003 | Required |

> **Before testing 4 and 6**, the backend must run the two new database migrations. Social login also needs the provider credentials in the API's `.env`. Until a provider is set up, its endpoints answer `503 "… login is not configured."`.

---

## 1. Log in with email or mobile number

`POST /auth/login`

### Request

```json
{
  "login": "01712345678",
  "password": "your-password"
}
```

- `login` accepts an **email** or a **Bangladeshi mobile number**. Any of these work: `01712345678`, `017-1234 5678`, `8801712345678`, `+8801712345678`.
- The old field `email` is still accepted, so nothing breaks before you switch. New code should send `login`.

### Success: `200`

```json
{ "data": { "access_token": "12|aBcDeF...", "token_type": "Bearer" } }
```

### Errors: `422`

| Case | Error key | Message |
|---|---|---|
| Wrong password, or no such account | `errors.password` | `The email/mobile number or password is incorrect.` |
| Not an email or a valid mobile number | `errors.login` | `Enter a valid email address or mobile number.` |
| Field missing | `errors.login` / `errors.password` | Laravel's required message |

Too many attempts returns `429` (5 per minute).

### Frontend to-do

- [ ] Rename the input label to **"Email or mobile number"**.
- [ ] Change the input from `type="email"` to `type="text"`. Otherwise the browser blocks phone numbers. Use `autocomplete="username"`.
- [ ] Send the value as `login`.
- [ ] Show `errors.login` under the input and `errors.password` under the password.
- [ ] Remove any handling of the old message `No customer account found with this email`. It no longer exists.

---

## 2. Phone-verified badge and verification flow (UPD-002)

### Reading the status: `GET /customer/profile`

```json
{
  "data": {
    "phone": "+8801712345678",
    "is_phone_verified": false,
    "phone_verified_at": null,
    "is_email_verified": true,
    "email_verified_at": "2026-11-20T10:15:00+06:00"
  }
}
```

This shows only the relevant fields. `phone_verified_at` and `email_verified_at` are **new**: ISO-8601 timestamps, or `null`.

### Verifying the number

1. `POST /customer/phone-verify-request` with body `{ "phone_number": "01712345678" }`, which sends an SMS OTP.
2. `POST /customer/phone-verify` with body `{ "phone_number": "01712345678", "code": "123456" }`, which marks the number verified.

What changed:

- **Bug fixed:** customers can now verify the number they **registered with**. Before, step 1 wrongly answered `400 "Phone number already verified"`. That answer now appears only when the number really is verified.
- **Saved format:** numbers are saved as `+8801XXXXXXXXX`, whatever format the customer types. Step 1 and step 2 can use different spellings of the same number.
- **Testing:** outside production, the OTP is always `123456` and no SMS is sent.

### Frontend to-do

- [ ] Next to the phone number: if `is_phone_verified` is `true`, show a **"Verified ✓"** badge. Optionally show the date from `phone_verified_at`.
- [ ] If it's `false`, show a **"Verify"** button. It calls step 1, opens an OTP input, then calls step 2. After success, re-fetch the profile.
- [ ] Handle `429` on step 1 (3 OTP sends per 10 minutes) and on step 2 (5 attempts per 10 minutes).

---

## 3. Secondary phone and address on the profile (UPD-004)

`POST /customer/profile`, the same endpoint as before. It now also accepts two optional fields:

```json
{
  "name": "Rahim Uddin",
  "gender": "male",
  "date_of_birth": "1998-05-10",
  "secondary_phone": "01812-345678",
  "address": "House 12, Road 5, Dhanmondi"
}
```

| Field | Rules |
|---|---|
| `name` | **Required** on every update, as before |
| `secondary_phone` | Optional Bangladeshi mobile number, saved as `+8801…`. Send `null` to remove it. If you leave it out, it stays unchanged. |
| `address` | Optional text, up to 191 characters |

`GET /customer/profile` now also returns `"secondary_phone": "+8801812345678"` (or `null`). `address` was already returned.

Errors are `422`. For example, `errors.secondary_phone`: `Enter a valid Bangladeshi mobile number.`

### Frontend to-do

- [ ] Add **Secondary phone** and **Address** inputs to the edit-profile form.
- [ ] Show them on the profile page.

---

## 4. Deliver to someone else (UPD-005, UPD-007)

A saved address can now carry a **recipient**: the person who receives the parcel, such as a relative. Checkout doesn't change. Choosing that address at checkout sends the order to the recipient.

### Create or update an address

- `POST /customer/addresses`
- `PUT /customer/addresses/{id}`

```json
{
  "new_district_id": 1,
  "area_id": 12,
  "street": "House 12, Road 5",
  "label": "home",
  "is_default_shipping": false,
  "is_default_billing": false,
  "recipient_name": "Karim Uddin",
  "recipient_phone": "01812345678"
}
```

| Field | Rules |
|---|---|
| `recipient_name` | Optional, up to 150 characters. **Required if `recipient_phone` is sent.** |
| `recipient_phone` | Optional Bangladeshi mobile number, saved as `+8801…`. **Required if `recipient_name` is sent.** |

- Leave both out, or send both as `null`, when the customer receives the order themselves.
- On update, send both as `null` to switch an address back to "deliver to me".

### Reading addresses

`GET /customer/addresses` and the address inside the dashboard both include:

```json
{ "recipient_name": "Karim Uddin", "recipient_phone": "+8801812345678" }
```

Both are `null` when the customer receives the order themselves.

### Errors: `422`

| Key | Message |
|---|---|
| `errors.recipient_phone` | `Enter the phone number of the person receiving the order` |
| `errors.recipient_name` | `Enter the name of the person receiving the order` |
| `errors.recipient_phone` | `Enter a valid Bangladeshi mobile number for the recipient` |

The invoice PDF now shows **Receiver** and **Receiver Phone** when a recipient is set.

### Frontend to-do

- [ ] In the address form, add a toggle: **"This order is for someone else"**. When it's on, show **Recipient name** and **Recipient phone** (both required).
- [ ] When it's off, send `recipient_name: null` and `recipient_phone: null`.
- [ ] In the address list and at checkout, show "Deliver to: Karim Uddin (+8801…)" when `recipient_name` is set.

---

## 5. One-call dashboard overview (UPD-001)

`GET /customer/dashboard` returns everything the dashboard home page needs in **one** request, instead of four.

```json
{
  "data": {
    "profile": { "...": "same object as GET /customer/profile" },
    "statistics": { "totalOrder": 4, "totalCart": 1, "totalWishlist": 0 },
    "recent_orders": [ { "...": "same items as GET /customer/recent-orders" } ],
    "default_addresses": {
      "shippingAddress": { "...": "address, or null" },
      "billingAddress": { "...": "address, or null" }
    }
  }
}
```

The separate endpoints still work: `/customer/profile`, `/customer/statistics`, `/customer/recent-orders` and `/customer/default-address`.

### Frontend to-do

- [ ] Build the dashboard overview from this single call: profile card with the verified badge, order and cart counts, the last 5 orders, and the default addresses.

---

## 6. Google, Facebook and Apple login (UPD-003)

The storefront signs the customer in with the provider's own JavaScript SDK, then sends the token it gets to the API. The API checks the token directly with Google, Facebook or Apple. There is **no redirect** through the API.

| Provider | What to send as `token` | How to get it |
|---|---|---|
| Google | An **access token** | Google Identity Services *token client*: `google.accounts.oauth2.initTokenClient({ client_id, scope: 'openid email profile', callback })`, then use `response.access_token` |
| Facebook | The **access token** | Facebook JS SDK `FB.login(cb, { scope: 'email' })`, then use `authResponse.accessToken` |
| Apple | The **ID token** | Sign in with Apple JS (`AppleID.auth.signIn()`), then use `authorization.id_token` |

- Use the same client IDs the backend has in its `.env` (`GOOGLE_CLIENT_ID`, `FACEBOOK_CLIENT_ID`, `APPLE_CLIENT_ID`). A token issued to any other app is refused.
- For Google, send the **access token**, not the one-tap `credential` ID token.

### Log in or sign up

`POST /auth/social/{provider}`, where `{provider}` is `google`, `facebook` or `apple`.

```json
{ "token": "<provider token>", "name": "Karim Uddin" }
```

- `name` is optional. Send it for **Apple**: Apple gives the customer's name only the first time, in `user.name` of the sign-in response.

**Success: `200`.** The response is the same as normal login, so store the token the same way.

```json
{ "data": { "access_token": "13|xYz...", "token_type": "Bearer" } }
```

How the API decides which account to use:

1. **Already connected:** if this Google, Facebook or Apple account is already connected, it logs in that customer.
2. **Matching verified email:** if a registered customer has the same email and the provider confirms the email is verified, it connects the account to that customer and logs them in.
3. **No match:** otherwise it creates a new customer and logs them in.

| Status | Body `message` | Meaning |
|---|---|---|
| `401` | `The Google sign-in could not be verified. Please try again.` | Invalid or expired token, or a token issued to another app |
| `409` | `An account already uses this email. Log in with your password, then connect Apple from your profile.` | The email belongs to a customer, but the provider didn't confirm it |
| `503` | `Google login is not configured.` | The backend doesn't have this provider's credentials yet |
| `404` | n/a | Unknown provider in the URL |
| `429` | n/a | Too many attempts |

Error bodies for `401`, `409` and `503` have the form `{ "message": "...", "data": null }`.

New social-only customers have no password they know. They can set one with **Forgot password** if they have an email.

### Connected accounts in "My Profile"

**List:** `GET /customer/social-accounts`

```json
{
  "data": [
    { "provider": "google",   "connected": true,  "email": "rahim@gmail.com", "connected_at": "2026-11-28T10:20:00+06:00" },
    { "provider": "facebook", "connected": false, "email": null, "connected_at": null },
    { "provider": "apple",    "connected": false, "email": null, "connected_at": null }
  ]
}
```

**Connect:** `POST /customer/social-accounts/{provider}` with body `{ "token": "<provider token>" }`, the same token as for login.

- `200`: `{ "message": "Google account connected", "data": null }`
- `409`: `This Google account is already connected to another customer.`
- `409`: `Disconnect your current Google account before connecting another one.`

**Disconnect:** `DELETE /customer/social-accounts/{provider}`

- `200`: `{ "message": "Google account disconnected", "data": null }`

### Frontend to-do

- [ ] Add **Continue with Google / Facebook / Apple** buttons to the login and register pages.
- [ ] Load each provider's JS SDK, get the token as shown in the table above, then call `POST /auth/social/{provider}`.
- [ ] For Apple, pass `name` from Apple's first-time `user` object.
- [ ] Show the `401`, `409` and `503` messages to the customer.
- [ ] In **My Profile**, add a **Connected accounts** section using the list, connect and disconnect endpoints.

---

## Not changed

- Registration (`POST /auth/register`) works as before.
- Checkout, cart, orders and payments are unchanged. Delivery to a recipient works through the saved address only.
