# Putting the Etowah website on Hostinger

This guide is for the website owner. You do not need to know any code. Plan on about 30–45 minutes. Keep this page open while you work.

You will need:
- your Hostinger login;
- the file **etowah-hostinger.zip** (your developer sends you this).

---

## 1. Prepare the website in hPanel

1. Log in to Hostinger and open **hPanel**.
2. Go to **Websites → Add website**, and add your domain.
   - A temporary Hostinger domain is fine for now. You can connect the real domain later (step 8).
3. Open the website's dashboard. Go to **Advanced → PHP Configuration**:
   - **PHP version:** choose **8.2** or newer and save.
   - **PHP Extensions:** make sure these are ticked: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `openssl`. Save.
   - **PHP Options:** set `upload_max_filesize` to **12M** and `post_max_size` to **14M**. Save.

## 2. Create the database

1. In hPanel go to **Databases → MySQL Databases**.
2. Create a new database and a new user. Pick a long password; hPanel can generate one.
3. Write down these **4 values**. The installer asks for them:

   | What | Example |
   |---|---|
   | Database name | `u123456789_etowah` |
   | Database user | `u123456789_admin` |
   | Password | (the password you chose) |
   | Host | `localhost` |

## 3. Create the "no-reply" mailbox

The website uses this mailbox to email you when someone sends the contact form or applies for a job.

1. In hPanel go to **Emails** and create the mailbox **no-reply@yourdomain.com**.
2. Open **Connect apps & devices** (or **Configuration settings**) for that mailbox.
3. Write down the **SMTP** details shown there: server, port, encryption, and the mailbox password.
   - On Hostinger these are usually `smtp.hostinger.com`, port `465`, SSL.

## 4. Upload the website files

1. In hPanel go to **Files → File Manager**, and open the **public_html** folder.
2. If the folder holds a default Hostinger page (for example `default.php` or `index.html`), delete it.
3. Click **Upload**, and choose **etowah-hostinger.zip**.
4. Right-click the uploaded zip and choose **Extract**. Extract it into `public_html` itself (not into a new sub-folder).
5. Delete **etowah-hostinger.zip** after it is extracted.

When you are done, `public_html` should contain folders such as `admin`, `app`, `assets`, and `install`, and files such as `index.php`.

## 5. Run the installer

1. In your browser, open **https://your-domain/install/** (use your real or temporary domain).
2. **Owner check:** the installer asks for an 8-character code.
   - In File Manager, open **public_html → storage → install-code.txt**.
   - Copy the code and paste it into the installer.
3. **Requirements:** every line should be green. If a line is red, the installer tells you exactly what to change in hPanel. Fix it and press **Check again**.
4. **Database:** enter the 4 values from step 2.
5. **Email:**
   - Enter the SMTP details from step 3.
   - Type your own email address under **Send a test to**, and press **Send test email**. Check your inbox and spam folder.
   - When the test works, press **Save and continue**.
   - If you get stuck, press **Skip for now (use PHP mail)**. Mail still works this way, but it lands in spam more often.
6. **Website and your login:**
   - Check the website address (it should start with `https://`).
   - Enter your name and email, and choose a password of at least 12 characters.
   - Press **Install the website**.
7. You will see **Installation complete**.

## 6. Delete the install folder

In File Manager, delete the **install** folder inside `public_html`.

The installer has already switched itself off, but the folder should not stay on the server.

## 7. Test everything

Sign in at **https://your-domain/admin/** and check each item:

- [ ] Every page opens: Home, the 4 service pages, Markets, Projects, About, Careers, Contact.
- [ ] Send a message on the **Contact** page:
  - you receive an email;
  - the message appears in the dashboard under **Inquiries**.
- [ ] In the dashboard, add a job under **Careers → Jobs** and set it to **Open**. It appears on the **Careers** page.
- [ ] Apply for that job on the website with a PDF resume. The application shows under **Careers → Applications**, and **Download resume** works.
- [ ] Under **Projects**, open a project, upload a real photo, and press **Save changes**. The new photo shows on the website.
- [ ] When you're done, set the test job to **Closed** (or delete it), and delete the test inquiry and application.

## 8. Turn on SSL and connect your real domain

1. In hPanel go to **Security → SSL**, and install the free SSL certificate for the domain. The site sends all visitors to `https://` automatically.
2. To connect your real domain, follow Hostinger's own guide. Go to **https://support.hostinger.com** and search for **"point domain to Hostinger"**.
   - Use exactly the values hPanel shows you for your account.
3. If the website address changes (for example, from the temporary domain to your real domain), tell your developer.
   - The address is stored in `app/config.php` (the line that starts with `'site_url'`), and it must match the new domain.
4. The site uses the address **without "www"** by default and sends www visitors there.

## 9. Backups (once a week)

1. **Database:** hPanel → **Databases → phpMyAdmin** → choose your database → **Export** → **Go**. Save the file somewhere safe.
2. **Photos you uploaded:** in File Manager, right-click **public_html/uploads** → **Compress**, then download the zip.
3. **Resumes and logs:**
   - Download the private storage folder. This is the folder **etowah-storage** next to `public_html`.
   - If that folder doesn't exist, download **public_html/storage** instead.
   - The installer showed which one it used.

Hostinger also makes automatic backups (hPanel → **Files → Backups**). The weekly copy above is your own extra safety net.

## 10. Updating the website later

When your developer sends you updated files:

1. Upload only the changed files, replacing the old ones.
2. Never overwrite or delete:
   - `app/config.php` — your passwords and settings;
   - `app/install.lock`;
   - the `uploads` folder — your photos;
   - the `storage` / `etowah-storage` folder — resumes and logs.
3. Never upload an `install` folder again unless your developer asks you to.

Need help? Tell your developer what you see on the screen. The dashboard's **Site Settings → System** box shows the server's health and the most recent errors.
