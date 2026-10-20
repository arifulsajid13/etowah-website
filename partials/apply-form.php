<?php
/** Job application form. Expects $applyJobId (0 = general application) and $applyTitle. */
$applied = get_str('applied') === '1';
$applyError = get_str('error') === '1';
?>
          <div class="form-card" id="apply">
            <h3><?= e($applyTitle) ?></h3>
<?php if ($applied): ?>
            <div class="form-success" role="status">Thank you — your application has been received.</div>
<?php else: ?>
            <form class="contact-form" action="/submit-application.php" method="post" enctype="multipart/form-data" data-apply-form data-error-phone="<?= e(setting('phone')) ?>" data-error-email="<?= e(setting('email')) ?>" novalidate>
<?= form_guard_fields('apply', '              ') ?>
              <input type="hidden" name="job_id" value="<?= (int) $applyJobId ?>">
              <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
              <div class="form-grid">
                <div class="field">
                  <label for="apply-name">Full Name<span class="req" aria-hidden="true">*</span></label>
                  <input id="apply-name" name="name" maxlength="120" type="text" autocomplete="name" required aria-required="true" aria-describedby="apply-name-error">
                  <p class="field-error" id="apply-name-error" hidden></p>
                </div>
                <div class="field">
                  <label for="apply-email">Email<span class="req" aria-hidden="true">*</span></label>
                  <input id="apply-email" name="email" maxlength="190" type="email" autocomplete="email" inputmode="email" required aria-required="true" aria-describedby="apply-email-error">
                  <p class="field-error" id="apply-email-error" hidden></p>
                </div>
                <div class="field">
                  <label for="apply-phone">Phone</label>
                  <input id="apply-phone" name="phone" maxlength="40" type="tel" autocomplete="tel" aria-describedby="apply-phone-error">
                  <p class="field-error" id="apply-phone-error" hidden></p>
                </div>
                <div class="field">
                  <label for="apply-resume">Resume<span class="req" aria-hidden="true">*</span></label>
                  <input id="apply-resume" name="resume" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required aria-required="true" aria-describedby="apply-resume-hint apply-resume-error">
                  <p class="field-hint" id="apply-resume-hint">PDF, DOC, or DOCX, up to 5 MB.</p>
                  <p class="field-error" id="apply-resume-error" hidden></p>
                </div>
                <div class="field field--full">
                  <label for="apply-message">Message</label>
                  <textarea id="apply-message" name="message" rows="5" maxlength="5000" aria-describedby="apply-message-error"></textarea>
                  <p class="field-error" id="apply-message-error" hidden></p>
                </div>
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" type="submit">Submit Application <?= icon('chevron-right') ?></button>
              </div>
<?php if ($applyError): ?>
              <div class="form-status is-error" role="status" aria-live="polite">Something went wrong. Please call <a href="<?= e(tel_href(setting('phone'))) ?>"><?= e(setting('phone')) ?></a> or email <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a> directly.</div>
<?php else: ?>
              <div class="form-status" role="status" aria-live="polite" hidden></div>
<?php endif; ?>
            </form>
<?php endif; ?>
          </div>
