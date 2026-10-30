# Removing invoicing and payments from the live site

The update zip only adds and overwrites files. It never deletes anything. Follow these steps in order.

## 1. Upload the update

1. In hPanel open **File Manager → public_html**.
2. Upload **etowah-update.zip** and extract it into **public_html**. Overwrite files when asked.
3. Open any page of the website once, for example the home page. This runs the database update (version 8). It deletes the tables `invoices`, `invoice_items`, `payments`, `gateway_events` and `invoice_reminders`, and every `invoice_…`, `stripe_…` and `authnet_…` setting.

**Warning:** all invoices and payment records are deleted permanently. Export them first if they are needed (see the note at the end).

## 2. Delete these files and folders by hand

Do this right after step 1. In File Manager, right-click each item → **Delete**. Paths start at `public_html`:

**Files**
1. `public_html/pay.php`
2. `public_html/app/crypto.php`
3. `public_html/app/invoices.php`
4. `public_html/app/invoice-mail.php`
5. `public_html/app/payments.php`
6. `public_html/admin/inc/views/invoices.php`
7. `public_html/admin/inc/views/invoice.php`
8. `public_html/admin/inc/views/invoice_edit.php`
9. `public_html/admin/inc/views/payments.php`
10. `public_html/partials/invoice-doc.php`
11. `public_html/assets/css/pay.css`
12. `public_html/assets/js/pay.js`

**Folders (delete the whole folder)**
13. `public_html/webhooks/` (contains `stripe.php` and `authorizenet.php`)
14. `public_html/cron/` (contains `invoice-reminders.php` and `.htaccess`)

To see `.htaccess` files in File Manager, turn on **Settings → Show hidden files**. Deleting the whole `cron` folder removes its `.htaccess` too.

Do **not** delete `public_html/.htaccess` (the main one). The update zip already replaced it with the version without the /pay rules.

## 3. If they were set up, also remove these

- **hPanel → Advanced → Cron Jobs:** delete the job that runs `cron/invoice-reminders.php`.
- **Stripe** (Developers → Webhooks), in test and live mode: delete the endpoint ending in `/webhooks/stripe.php`.
- **Authorize.net** (Account → Settings → Business Settings → Webhooks): delete the endpoint ending in `/webhooks/authorizenet.php`.
- **Private storage:** if the reminder job ever ran, delete `etowah-storage/logs/invoice-reminders.log`. That folder sits next to `public_html`, or it is `public_html/storage/logs/` if the site uses that location.

## 4. Check

- Visit `https://{your site}/pay`. It should show the "Page not found" page.
- Visit `https://{your site}/webhooks/stripe.php`. It should show "Page not found".
- In the dashboard, the sidebar has no **Invoices** item, the Overview has no invoice cards, and Site Settings has no "Payments & Invoices" tab.
- **Site Settings → System → Recent errors:** there should be no new errors.

Note: to keep a copy of the invoice data, export it before step 1: **hPanel → Databases → phpMyAdmin**, select the tables `invoices`, `invoice_items` and `payments`, then **Export**.
