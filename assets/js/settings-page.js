/**
 * Embold WordPress Tweaks - Settings Page JavaScript
 */
document.addEventListener("DOMContentLoaded", function () {
  /**
   * Remove transient query params so notices don't persist on refresh.
   */
  (function cleanNoticeParams() {
    try {
      const url = new URL(window.location.href);
      const transientKeys = ["embold_msg", "embold_err", "settings-updated"];
      let changed = false;
      transientKeys.forEach((key) => {
        if (url.searchParams.has(key)) {
          url.searchParams.delete(key);
          changed = true;
        }
      });
      if (changed && window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, url.toString());
      }
    } catch (e) {
      // no-op
    }
  })();

  /**
   * Toggle visibility of the "Extra Suppression Strings" field
   * based on the "Suppress Debug Notices" checkbox state.
   */
  function setupSuppressNoticesToggle() {
    const checkbox = document.querySelector(
      "input[type='checkbox'].embold-suppress-toggle"
    );
    const fieldWrapper = document.querySelector(".embold-suppress-strings-row");

    if (!checkbox || !fieldWrapper) {
      return;
    }

    // The wrapper div is inside a TD, we need to hide the parent TR
    const row = fieldWrapper.closest("tr");

    function updateVisibility() {
      if (row) {
        row.style.display = checkbox.checked ? "" : "none";
      }
    }

    // Listen for changes
    checkbox.addEventListener("change", updateVisibility);

    // Set initial state on page load
    updateVisibility();
  }

  setupSuppressNoticesToggle();

  /**
   * Toggle visibility of SMTP fields based on selected/effective mail mode.
   * Hides rows unless mode is "smtp_override".
   */
  function setupSmtpFieldsToggle() {
    const select = document.querySelector(
      "select[name='embold_tweaks_options[mail_mode]']"
    );
    if (!select) return;

    const wrappers = document.querySelectorAll(".embold-smtp-field");
    if (!wrappers || wrappers.length === 0) return;

    function updateSmtpVisibility() {
      const effective = select.dataset && select.dataset.effectiveMode
        ? select.dataset.effectiveMode
        : select.value || "";
      const isSmtp = effective === "smtp_override";

      wrappers.forEach(function (wrap) {
        const row = wrap.closest("tr");
        if (row) {
          row.style.display = isSmtp ? "" : "none";
        }
      });
    }

    // Listen for changes to the select
    select.addEventListener("change", function () {
      // When user changes, prefer the live value
      select.dataset.effectiveMode = select.value;
      updateSmtpVisibility();
    });

    // Initial state
    updateSmtpVisibility();
  }

  setupSmtpFieldsToggle();

  /**
   * Deduplicate identical notices (sometimes added twice on reset).
   */
  (function dedupeNotices() {
    const notices = document.querySelectorAll(".notice");
    const seen = new Set();
    notices.forEach((n) => {
      const text = n.textContent.trim();
      const key = n.className + "::" + text;
      if (seen.has(key)) {
        n.parentNode && n.parentNode.removeChild(n);
      } else {
        seen.add(key);
      }
    });
  })();
});
