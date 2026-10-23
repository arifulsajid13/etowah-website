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
4. Go to **Security → SSL** and check that SSL is **Active** for the domain. Hostinger usually turns it on by itself within a few minutes. The website always uses `https://`, so wait until it shows Active.

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

## Demo on a temporary domain

You can show the site to the client before the real domain is connected. Hostinger gives every new website a temporary address like `something.hostingersite.com`.

1. Install the site on the temporary address (steps 1–6). The installer fills in that address automatically.
2. While you demo, the site stays **hidden from search engines**. This is on by default after installing, so Google won't list the temporary address.
   - The dashboard top bar shows a yellow **Hidden from search engines** label while this is on.
3. Share the temporary address with the client. Everything works there: forms, emails, the dashboard, and photos.

## 8. Go live on the real domain

1. **Connect the domain.** Follow Hostinger's own guide: go to **https://support.hostinger.com** and search for **"point domain to Hostinger"**.
   - Use exactly the values hPanel shows you for your account.
2. **SSL:** in hPanel go to **Security → SSL**, and make sure the free SSL certificate is **Active** for the real domain. The site sends all visitors to `https://` automatically, on any domain.
3. **Change the Site URL:** in the dashboard go to **Site Settings → Website address and search engines**. Set **Site URL** to the real address, for example `https://etowahconstruction.com`, and save.
   - This updates the links in emails, Google listings, the sitemap, and sharing previews. No files need editing.
4. **Turn the search-engine block off:** on the same page, untick **Hide site from search engines**, and save. The yellow label in the top bar disappears.
5. **Tell Google:** in **Google Search Console** (https://search.google.com/search-console):
   - add the site;
   - verify it, either by pasting the HTML-tag code into **Site Settings → Google Search Console** or by using the DNS method;
   - under **Sitemaps**, submit `https://your-domain/sitemap.xml`.
6. The site uses the address **without "www"** by default and sends www visitors there.

## 9. Backups (once a week)

1. **Database:** hPanel → **Databases → phpMyAdmin** → choose your database → **Export** → **Go**. Save the file somewhere safe.
2. **Photos you uploaded:** in File Manager, right-click **public_html/uploads** → **Compress**, then download the zip.
3. **Resumes and logs:**
   - Download the private storage folder. This is the folder **etowah-storage** next to `public_html`.
   - If that folder doesn't exist, download **public_html/storage** instead.
   - The installer showed which one it used.

Hostinger also makes automatic backups (hPanel → **Files → Backups**). The weekly copy above is your own extra safety net.

## 10. Updating the website later

When your developer sends you **etowah-update.zip**:

1. Back up first (step 9).
2. In File Manager, open `public_html`, upload **etowah-update.zip**, right-click it and choose **Extract**. Extract into `public_html` itself, and say yes to replacing existing files.
3. Delete **etowah-update.zip** after it is extracted.
4. Open any page of the website once. Any database changes in the update run by themselves on that first visit.

The update zip contains only code (plus the small `uploads/.htaccess` protection file). It never includes the items below, so extracting it leaves them untouched.

If your developer sends single files instead, upload only those files, replacing the old ones.

Never overwrite or delete:
   - `app/config.php` — your passwords and settings;
   - `app/install.lock`;
   - the `uploads` folder — your photos;
   - the `storage` / `etowah-storage` folder — resumes and logs.

Never upload an `install` folder again unless your developer asks you to.

## 11. Importing projects, photos, and videos

When your developer sends you **etowah-projects-import.zip** (projects with their photos and videos, and photos for the website pages):

1. Install the matching **etowah-update.zip** first (step 10), if you received one.
2. In the dashboard, open **Projects → Import projects**. The page shows the exact folder to use. It is the `import` folder inside your private storage folder, normally `etowah-storage/import` next to `public_html` (or `public_html/storage/import` on sites that keep storage inside `public_html`).
3. In File Manager, open that folder and upload **etowah-projects-import.zip** into it. You don’t need to extract it.
4. Reload **Import projects**. Check the preview (new projects, updated projects, number of photos and videos), then press **Import**.
5. The package files are deleted from the folder automatically after a successful import.

The import never overwrites text you edited in the dashboard, a real main photo or page photo you uploaded, or a homepage choice you changed. Running it twice adds nothing twice. A newer package (for example with touched-up photos) swaps the photos it imported before, in place.

Need help? Tell your developer what you see on the screen. The dashboard's **Site Settings → System** box shows the server's health and the most recent errors.
