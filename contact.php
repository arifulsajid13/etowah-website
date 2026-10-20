<?php
require __DIR__ . '/app/bootstrap.php';
require APP . '/csrf.php';

csrf_token(); // start the session before any output

layout_start('contact');
?>

  <main id="main" tabindex="-1">
    <section class="hero hero--short">
      <?= hero_image('contact') ?>
      <div class="container hero-content">
        <div class="hero-inner">
          <nav class="breadcrumb" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li aria-current="page">Contact</li></ol></nav>
          <h1>Discuss Your Project</h1>
          <p class="hero-sub">Tell us about your site, utility, pump station, or roadway project.</p>
        </div>
      </div>
    </section>

    <section class="section section--alt">
      <div class="container contact-grid">
        <div class="contact-card on-dark">
          <p class="eyebrow eyebrow--dark">GET IN TOUCH</p>
          <h2>Let’s talk about your project.</h2>
          <ul class="contact-list">
            <li><span class="icon-box"><?= icon('user') ?></span><span><?= e(setting('contact_name')) ?></span></li>
            <li><span class="icon-box"><?= icon('phone') ?></span><a href="<?= e(tel_href(setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
            <li><span class="icon-box"><?= icon('mail') ?></span><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
            <li><span class="icon-box"><?= icon('pin-card') ?></span><span><?= e(setting('city_state')) ?></span></li>
          </ul>
          <hr>
          <p class="small"><?= e(setting('license_text')) ?> · License No. <?= e(setting('license_number')) ?></p>
          <p class="small">Serving municipal, commercial, residential, and roadway clients.</p>
        </div>

        <div class="form-card" id="contact-form">
          <h3>Send us a message</h3>
<?php if (get_str('sent') === '1'): ?>
          <div class="form-success" role="status">Thank you — your message has been sent. <?= e(strtok(setting('contact_name'), ' ') ?: 'We') ?> will be in touch shortly.</div>
<?php else: ?>
          <form class="contact-form" action="/submit-contact.php" method="post" data-contact-form data-error-phone="<?= e(setting('phone')) ?>" data-error-email="<?= e(setting('email')) ?>" novalidate>
<?= form_guard_fields('contact') ?>
            <div class="form-grid">
              <div class="field">
                <label for="field-name">Full Name<span class="req" aria-hidden="true">*</span></label>
                <input id="field-name" name="name" maxlength="120" type="text" autocomplete="name" required aria-required="true" aria-describedby="field-name-error">
                <p class="field-error" id="field-name-error" hidden></p>
              </div>
              <div class="field">
                <label for="field-company">Company</label>
                <input id="field-company" name="company" maxlength="160" type="text" autocomplete="organization">
              </div>
              <div class="field">
                <label for="field-email">Email<span class="req" aria-hidden="true">*</span></label>
                <input id="field-email" name="email" maxlength="190" type="email" autocomplete="email" inputmode="email" required aria-required="true" aria-describedby="field-email-error">
                <p class="field-error" id="field-email-error" hidden></p>
              </div>
              <div class="field">
                <label for="field-phone">Phone</label>
                <input id="field-phone" name="phone" maxlength="40" type="tel" autocomplete="tel">
              </div>
              <div class="field">
                <label for="field-type">Project Type</label>
                <select id="field-type" name="project_type">
                  <option value="">Select a project type</option>
                  <option>Site Development</option>
                  <option>Underground Utilities</option>
                  <option>Pump Stations</option>
                  <option>Roadways &amp; Stormwater</option>
                  <option>Other</option>
                </select>
              </div>
              <div class="field">
                <label for="field-location">Project Location</label>
                <input id="field-location" name="location" maxlength="160" type="text" autocomplete="off">
              </div>
              <div class="field field--full">
                <label for="field-message">Message<span class="req" aria-hidden="true">*</span></label>
                <textarea id="field-message" name="message" rows="5" maxlength="5000" required aria-required="true" aria-describedby="field-message-error"></textarea>
                <p class="field-error" id="field-message-error" hidden></p>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Send Message <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg></button>
            </div>
<?php if (get_str('error') === '1'): ?>
            <div class="form-status is-error" role="status" aria-live="polite">Something went wrong. Please call <a href="<?= e(tel_href(setting('phone'))) ?>"><?= e(setting('phone')) ?></a> or email <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a> directly.</div>
<?php else: ?>
            <div class="form-status" role="status" aria-live="polite" hidden></div>
<?php endif; ?>
          </form>
<?php endif; ?>
        </div>
      </div>
    </section>
  </main>
<?php layout_end(['footer' => 'compact']); ?>
