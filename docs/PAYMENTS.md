# Online payments: setup guide

This guide explains how to connect the website to **Stripe** and/or **Authorize.net**, so customers can pay invoices online. You can use one or both. If both are on, the customer chooses.

Customers always pay on the payment company's own secure page. Card and bank details never reach our website.

Everything below is done in the dashboard at **Site Settings → Payments & Invoices**, plus the Stripe or Authorize.net website.

---

## Before you start

- Upload the new website files (`etowah-update.zip`) first. The database updates itself on the first page load.
- The website must be on its real address with HTTPS (for example `https://etowahconstruction.com`). Check **Site Settings → Site URL**. The webhook addresses below use it.
- Do the whole setup in **test mode** first. Switch to **live** only after a test payment works (see "Going live").

---

## Part 1: Stripe

### 1. Create the Stripe account (in the company's name)

1. Go to **stripe.com** and select **Sign up**. Use a company email address (for example ECG@etowahconstruction.com), not a personal one.
2. When Stripe asks for business details, enter:
   - **Legal business name:** Etowah Construction Group LLC
   - **Business type:** LLC
   - **EIN** (the company's federal tax ID)
   - The company address and phone
   - The owner's details (Stripe requires a representative)
   - The **company bank account** where Stripe should send the money
3. Finish the identity check Stripe asks for. Until it is done, only test mode works.

### 2. Turn on bank (ACH) payments

1. In Stripe, open **Settings → Payment methods**.
2. Make sure **Cards** is on.
3. Turn on **ACH Direct Debit** (shown as "US bank account").

### 3. Copy the secret key

1. In Stripe, turn on **Test mode** (switch at the top right).
2. Open **Developers → API keys**.
3. Next to **Secret key**, select **Reveal**, then copy it. It starts with `sk_test_`.
4. In the dashboard, paste it into **Stripe → Secret key (test)**.

Never send this key by email or text. Paste it only into the dashboard.

### 4. Add the webhook

The webhook is how Stripe tells the website that a payment went through. **Without it, invoices are never marked as paid.**

1. In Stripe (still in test mode), open **Developers → Webhooks**, then **Add endpoint** (it may be called "Add destination").
2. **Endpoint URL:** copy it from the dashboard (Stripe section, "Webhook"). It looks like:
   `https://etowahconstruction.com/webhooks/stripe.php`
3. Under **Select events**, choose exactly these 4 events:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.async_payment_failed`
   - `charge.refunded`
4. Save. On the endpoint's page, find **Signing secret**, select **Reveal**, and copy it. It starts with `whsec_`.
5. In the dashboard, paste it into **Stripe → Webhook signing secret (test)**.

### 5. Switch Stripe on

1. In the dashboard, tick **Accept payments with Stripe** and choose **Test** mode.
2. Under **Payment methods**, tick **Card** and/or **Bank account (ACH)**.
3. Select **Save payment settings**.
4. Select **Test Stripe (test)** at the bottom. You should see "Connected to Stripe (test mode)."

The Stripe box shows **Active** once it is on and both keys are saved.

---

## Part 2: Authorize.net

### 1. Get the account

- **For testing:** create a free sandbox account at **developer.authorize.net** (select "Sign up for a sandbox account"). Sandbox accounts never move real money.
- **For real payments:** the company needs a live Authorize.net merchant account in the company's name (Etowah Construction Group LLC), usually set up through the company's bank or a reseller.

The sandbox and the live account have **different** keys. Repeat the steps below for each one.

### 2. Copy the API Login ID and Transaction Key

1. Sign in to the Authorize.net Merchant Interface (sandbox: **sandbox.authorize.net**, live: **account.authorize.net**).
2. Open **Account → Settings → Security Settings → API Credentials & Keys**.
3. Copy the **API Login ID**. Paste it into the dashboard: **Authorize.net → API Login ID**.
4. On the same page choose **New Transaction Key**, then **Submit**. Copy the key (16 characters) and paste it into **Transaction Key**.
   (Creating a new key stops the old one from working after a while. That is fine if nothing else uses it.)

### 3. Create the Signature Key

1. On the same **API Credentials & Keys** page, choose **New Signature Key**, then **Submit**.
2. Copy the key (128 characters). Paste it into **Signature Key**.

### 4. Turn on the Transaction Details API

The website checks every payment with Authorize.net before marking an invoice paid. This needs one setting:

1. Open **Account → Settings → Security Settings → Transaction Details API**.
2. Turn it **on** (enable).

### 5. Add the webhook

1. Open **Account → Settings → Business Settings → Webhooks**, then **Add Endpoint**.
2. **Endpoint URL:** copy it from the dashboard (Authorize.net section). It looks like:
   `https://etowahconstruction.com/webhooks/authorizenet.php`
3. Choose exactly these 3 events:
   - `net.authorize.payment.authcapture.created`
   - `net.authorize.payment.refund.created`
   - `net.authorize.payment.void.created`
4. Set the status to **Active** and save.

### 6. Switch Authorize.net on

1. In the dashboard, tick **Accept payments with Authorize.net** and choose **Sandbox**.
2. Select **Save payment settings**.
3. Select **Test Authorize.net (sandbox)**. You should see "Connected to Authorize.net (sandbox)."

---

## Part 3: Test a payment

1. **Invoices → New invoice.** Use your own email address as the customer email. Add one line, for example "Test" × 1 at $10.00. Save.
2. Select **Send invoice**. Open the email and select **View and pay invoice**.
3. Pay with a test card or test bank account (below).
4. You return to a "Payment received" or "Payment is processing" page. Within a minute the invoice shows **Paid** in the dashboard, and you get the receipt and the payment notification emails.
5. Void the test invoice afterwards (or leave it; test payments are not real money).

If the invoice stays unpaid after a card payment, the webhook is not set up correctly. Check the URL, the events and the signing secret, then look at **Site Settings → System → Recent errors**.

### Stripe test cards

Use any future expiry date (for example 12/34), any 3-digit CVC and any ZIP code.

| Card number | Result |
|---|---|
| 4242 4242 4242 4242 | Payment succeeds |
| 4000 0025 0000 3155 | Asks for a bank confirmation (3D Secure), then succeeds |
| 4000 0000 0000 9995 | Declined (insufficient funds) |

### Stripe test bank accounts (ACH)

On the Stripe page choose to enter the bank details by hand (or pick the "Test Institution"), then use:

| Routing number | Account number | Result |
|---|---|---|
| 110000000 | 000123456789 | Payment succeeds (after a short delay in test mode) |
| 110000000 | 000111111113 | Payment fails (account closed): you get an "ACH payment failed" email |
| 110000000 | 000222222227 | Payment fails (insufficient funds) |

If Stripe asks for micro-deposit verification in test mode, use the code **SM11AA**.

A bank payment first shows **Processing**. It becomes **Paid** when it clears (a few business days in live mode, much faster in test mode).

### Authorize.net sandbox test cards

Any future expiry date and any 3-digit CVV.

| Card number | Type |
|---|---|
| 4111 1111 1111 1111 | Visa |
| 5424 0000 0000 0015 | Mastercard |
| 3700 000000 00002 | American Express (4-digit CVV) |

Test bank account (eCheck): routing number **121042882**, any account number such as **123456789**.

---

## Going live

Do this only after a test payment worked.

**Stripe**
1. In Stripe, turn **Test mode off**.
2. Copy the live **Secret key** (`sk_live_…`) from **Developers → API keys** into **Secret key (live)**.
3. Add the webhook again **in live mode** (same URL, same 4 events). Copy its live **Signing secret** into **Webhook signing secret (live)**.
4. In the dashboard choose **Live** mode, save, and select **Test Stripe (live)**.

**Authorize.net**
1. Enter the **live** account's API Login ID, Transaction Key and Signature Key (from account.authorize.net).
2. In the live account, turn on the Transaction Details API and add the webhook (same URL, same 3 events).
3. In the dashboard choose **Live** mode, save, and select **Test Authorize.net (live)**.

Then make one small real payment (for example $1.00) and refund it in Stripe or Authorize.net. The invoice should show the refund within a minute.

To go back to testing, choose Test / Sandbox again. Both sets of Stripe keys stay saved.

---

## Day-to-day

- **Refunds:** make them in Stripe or Authorize.net. The invoice updates by itself (Partially paid or Refunded).
- **Checks, cash and wires:** open the invoice and use **Record a payment**.
- **An invoice sent by mistake:** **Void invoice**. Its link stops accepting payments. An invoice that already has payments cannot be voided until they are refunded.
- **Automatic reminders (optional):** tick **Email reminders automatically**, choose the days, and add the daily Cron Job shown on the settings page in **hPanel → Advanced → Cron Jobs**. The sent reminders are listed in `invoice-reminders.log` in the private storage folder's `logs` folder.
- **Keys:** the dashboard only shows the last 4 characters of a saved key. Leave a key field empty to keep the saved key. Keys are stored encrypted. If `app/config.php` is ever replaced with a new one (new app secret), enter the keys again.
