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
   * Tabs: show the panels for one tab at a time and keep it in the URL hash.
   * Every panel stays inside the one form, so saving still submits every field.
   */
  (function setupTabs() {
    const links = document.querySelectorAll(".embold-tabs .nav-tab");
    const panels = document.querySelectorAll(".embold-tab-panel");
    if (!links.length || !panels.length) return;

    const ids = Array.from(links, (link) => link.dataset.tab);
    const referer = document.querySelector(
      'form[action="options.php"] input[name="_wp_http_referer"]'
    );

    function activate(id) {
      if (!ids.includes(id)) id = ids[0];
      links.forEach((link) => {
        const active = link.dataset.tab === id;
        link.classList.toggle("nav-tab-active", active);
        link.setAttribute("aria-current", active ? "page" : "false");
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tab !== id;
      });
      // options.php redirects to this field, so carry the tab through a save.
      if (referer) {
        referer.value = referer.value.split("#")[0] + "#" + id;
      }
    }

    links.forEach((link) => {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        activate(link.dataset.tab);
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, "", "#" + link.dataset.tab);
        }
      });
    });

    window.addEventListener("hashchange", () => activate(location.hash.slice(1)));
    activate(location.hash.slice(1));
  })();

  /**
   * Toggle visibility of the "Extra Suppression Strings" field
   * based on the "Suppress Debug Notices" checkbox state.
   */
  function setupSuppressNoticesToggle() {
    const checkbox = document.querySelector(
      "tr.embold-suppress-toggle input[type='checkbox']"
    );
    const row = document.querySelector("tr.embold-suppress-strings-row");

    if (!checkbox || !row) {
      return;
    }

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

    const rows = document.querySelectorAll("tr.embold-smtp-field");
    if (!rows.length) return;

    function updateSmtpVisibility() {
      const effective =
        select.dataset && select.dataset.effectiveMode
          ? select.dataset.effectiveMode
          : select.value || "";
      const isSmtp = effective === "smtp_override";

      rows.forEach(function (row) {
        row.style.display = isSmtp ? "" : "none";
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
   * Track unsaved changes and warn before sending test email
   */
  (function setupTestEmailWarning() {
    const mainForm = document.querySelector('form[action="options.php"]');
    const testForm = document.querySelector('form[action*="admin-post.php"]');
    
    if (!mainForm || !testForm) return;
    
    let hasUnsavedChanges = false;
    
    // Track changes in main settings form
    mainForm.addEventListener('change', function() {
      hasUnsavedChanges = true;
    });
    
    // Reset flag when main form is saved
    mainForm.addEventListener('submit', function() {
      hasUnsavedChanges = false;
    });
    
    // Check before sending test email
    testForm.addEventListener('submit', function(e) {
      if (hasUnsavedChanges) {
        const msg = 'You have unsaved changes. The test email will use the currently saved settings, not your changes.\n\nSave settings first, or click OK to send test email with current saved settings.';
        if (!confirm(msg)) {
          e.preventDefault();
        }
      }
    });
  })();

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
