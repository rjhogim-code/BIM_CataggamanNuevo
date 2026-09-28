(() => {
  'use strict';

  const menu = document.querySelector('#menu');
  if (menu) menu.onclick = () => document.querySelector('#sidebar').classList.toggle('open');

  const clock = document.querySelector('#clock');
  if (clock) {
    const update = () => {
      clock.textContent = new Intl.DateTimeFormat(undefined, {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
      }).format(new Date());
    };
    update();
    setInterval(update, 30000);
  }

  document.querySelectorAll('dialog[data-autoopen]').forEach(dialog => {
    dialog.showModal();
    dialog.addEventListener('close', () => history.replaceState(null, '', location.pathname), { once: true });
  });

  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', event => {
      if (!confirm(el.dataset.confirm)) event.preventDefault();
    });
  });
})();
