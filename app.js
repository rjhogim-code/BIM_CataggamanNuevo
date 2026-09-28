(() => {
  'use strict';

  /* ------------------------------------------------------------------ *
   * Off-canvas navigation (phone / small tablet)
   * ------------------------------------------------------------------ */

  const sidebar = document.querySelector('#sidebar');
  const menuBtn = document.querySelector('#menu');
  const backdrop = document.querySelector('#navBackdrop');

  if (sidebar && menuBtn && backdrop) {
    const setNav = open => {
      sidebar.classList.toggle('open', open);
      backdrop.classList.toggle('show', open);
      menuBtn.setAttribute('aria-expanded', String(open));
      // Stop the page behind the drawer from scrolling under it.
      document.body.style.overflow = open ? 'hidden' : '';
      if (open) {
        const firstLink = sidebar.querySelector('.nav');
        if (firstLink) firstLink.focus();
      }
    };

    menuBtn.addEventListener('click', () => setNav(!sidebar.classList.contains('open')));
    backdrop.addEventListener('click', () => { setNav(false); menuBtn.focus(); });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && sidebar.classList.contains('open')) {
        setNav(false);
        menuBtn.focus();
      }
    });

    // If the viewport grows back to desktop while the drawer is open, reset it
    // so the body scroll lock doesn't linger on a layout that has no drawer.
    matchMedia('(min-width: 761px)').addEventListener('change', event => {
      if (event.matches) setNav(false);
    });
  }

  /* ------------------------------------------------------------------ *
   * Clock
   * ------------------------------------------------------------------ */

  const clock = document.querySelector('#clock');
  if (clock) {
    const update = () => {
      clock.textContent = new Intl.DateTimeFormat(undefined, {
        weekday: 'short', month: 'short', day: 'numeric',
        year: 'numeric', hour: 'numeric', minute: '2-digit'
      }).format(new Date());
    };
    update();
    setInterval(update, 30000);
  }

  /* ------------------------------------------------------------------ *
   * Flash messages
   * ------------------------------------------------------------------ */

  document.querySelectorAll('.flash').forEach(flash => {
    const close = flash.querySelector('.flash-close');
    if (close) close.addEventListener('click', () => flash.remove());

    // Success is transient; an error stays until it is read and dismissed.
    if (flash.classList.contains('success')) {
      setTimeout(() => flash.remove(), 6000);
    }
  });

  /* ------------------------------------------------------------------ *
   * Client-side validation
   *
   * These rules deliberately mirror includes/validation.php one for one. This
   * layer exists only so staff see the problem while typing instead of after a
   * round trip — the server check is the one that actually protects the data,
   * and it runs regardless of what happens here.
   * ------------------------------------------------------------------ */

  const rules = {
    name(value) {
      if (value.length < 2) return 'Name must be at least 2 characters.';
      if (!/^[\p{L}][\p{L}\p{M}\s.'-]*$/u.test(value)) {
        return 'Name may only contain letters, spaces, hyphens, apostrophes, and periods.';
      }
      return '';
    },

    email(value) {
      if (!value.includes('@')) {
        return 'Email address must contain an "@" sign — for example juan.delacruz@gmail.com.';
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
        return 'Enter a complete email address, for example juan.delacruz@gmail.com.';
      }
      return '';
    },

    contact(value) {
      let digits = value.replace(/[\s\-().]/g, '');
      if (digits.startsWith('+63')) digits = '0' + digits.slice(3);
      else if (digits.startsWith('63') && digits.length === 12) digits = '0' + digits.slice(2);

      if (!/^\d+$/.test(digits)) {
        return 'Contact number may only contain numbers — for example 09171234567.';
      }
      if (digits.startsWith('09')) {
        return digits.length === 11 ? '' : 'A mobile number must be 11 digits — for example 09171234567.';
      }
      if (digits.length < 7 || digits.length > 10) {
        return 'Enter an 11-digit mobile number (09171234567) or a landline with its area code (0788441234).';
      }
      return '';
    },

    age(value) {
      if (!/^\d+$/.test(value)) return 'Age must be a whole number — no letters or symbols.';
      const age = Number(value);
      if (age < 0 || age > 125) return 'Age must be between 0 and 125.';
      return '';
    },

    username(value) {
      if (value.length < 4 || value.length > 50) return 'Username must be between 4 and 50 characters.';
      if (!/^[A-Za-z0-9._-]+$/.test(value)) {
        return 'Username may only contain letters, numbers, dots, underscores, and hyphens — no spaces.';
      }
      return '';
    },

    password(value) {
      if (value.length < 8) return 'Password must be at least 8 characters.';
      if (!/[A-Za-z]/.test(value) || !/\d/.test(value)) {
        return 'Password must include at least one letter and one number.';
      }
      return '';
    }
  };

  /** Show or clear the inline message under one field. */
  const report = (input, message) => {
    const field = input.closest('.field') || input.closest('label');
    if (!field) return;

    let slot = field.querySelector('.field-error');
    if (message) {
      if (!slot) {
        slot = document.createElement('p');
        slot.className = 'field-error';
        slot.id = (input.name || input.id || 'field') + '-error';
        field.appendChild(slot);
      }
      slot.textContent = message;
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', slot.id);
    } else if (slot) {
      slot.remove();
      input.removeAttribute('aria-invalid');
      input.removeAttribute('aria-describedby');
    }
  };

  /** Run the field's rule. Empty optional fields are always fine. */
  const check = input => {
    const rule = rules[input.dataset.validate];
    const value = input.value.trim();

    if (!rule) return true;
    if (value === '') {
      // `required` is left to the browser and the server; blank is not a
      // format error, so don't shout about it while the person is still typing.
      report(input, '');
      return !input.required;
    }

    const message = rule(value);
    report(input, message);
    return message === '';
  };

  const validated = document.querySelectorAll('[data-validate]');

  validated.forEach(input => {
    // Validate on blur, then keep re-checking as they fix it — but never start
    // complaining mid-word on a field they haven't finished with yet.
    input.addEventListener('blur', () => check(input));
    input.addEventListener('input', () => {
      if (input.hasAttribute('aria-invalid')) check(input);
    });
  });

  // Block submission and focus the first bad field, so the browser doesn't
  // have to round-trip just to tell them the phone number has letters in it.
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
      const fields = form.querySelectorAll('[data-validate]');
      let firstBad = null;

      fields.forEach(input => {
        if (!check(input) && !firstBad) firstBad = input;
      });

      if (firstBad) {
        event.preventDefault();
        firstBad.focus();
      }
    });
  });

  /* ------------------------------------------------------------------ *
   * Dialogs
   * ------------------------------------------------------------------ */

  document.querySelectorAll('dialog[data-autoopen]').forEach(dialog => {
    dialog.showModal();

    // Closing with Escape (rather than Cancel/Close, which navigate) would
    // otherwise leave ?new=1 / ?edit=ID in the URL, so a refresh silently
    // reopened the dialog. Strip the query string on close.
    dialog.addEventListener('close', () => {
      history.replaceState(null, '', location.pathname);
    }, { once: true });
  });

  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', event => {
      if (!confirm(el.dataset.confirm)) event.preventDefault();
    });
  });

  /* ------------------------------------------------------------------ *
   * Search box: debounce the auto-submit so a lookup isn't fired on every
   * keystroke, and keep the caret where it was after the page reloads.
   * ------------------------------------------------------------------ */

  document.querySelectorAll('[data-autosubmit]').forEach(input => {
    let timer;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => input.form.requestSubmit(), 350);
    });

    // Put the caret at the end of the restored search term rather than the start.
    if (input.value) {
      const end = input.value.length;
      input.setSelectionRange(end, end);
    }
  });
})();
