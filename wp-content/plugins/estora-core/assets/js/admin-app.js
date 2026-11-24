/* ESTORA application shell interactions. */
(() => {
  'use strict';

  const cfg = window.estoraAdmin;
  if (!cfg || !cfg.app) return;

  const shell = document.querySelector('[data-shell]');
  const sidebar = document.getElementById('estora-sidebar');
  const scrim = document.querySelector('[data-app-scrim]');
  const navToggle = document.querySelector('[data-app-nav-toggle]');
  const userToggle = document.querySelector('[data-user-menu-toggle]');
  const userPanel = document.querySelector('[data-user-menu-panel]');
  const mobileQuery = window.matchMedia('(max-width: 1180px)');

  const setMobile = (open) => {
    if (!shell) return;
    shell.classList.toggle('is-mobile-open', open);
    if (navToggle) navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (scrim) scrim.hidden = !open;
    document.body.style.overflow = open ? 'hidden' : '';
  };

  if (navToggle && shell) {
    const stored = localStorage.getItem('estora_sidebar_collapsed');
    if (stored === '1' && !mobileQuery.matches) shell.classList.add('is-collapsed');

    navToggle.addEventListener('click', () => {
      if (mobileQuery.matches) {
        setMobile(!shell.classList.contains('is-mobile-open'));
        return;
      }
      const collapsed = shell.classList.toggle('is-collapsed');
      localStorage.setItem('estora_sidebar_collapsed', collapsed ? '1' : '0');
      if (sidebar) sidebar.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
    });

    mobileQuery.addEventListener('change', () => {
      setMobile(false);
      if (!mobileQuery.matches) {
        const storedNow = localStorage.getItem('estora_sidebar_collapsed');
        shell.classList.toggle('is-collapsed', storedNow === '1');
      }
    });
  }

  scrim?.addEventListener('click', () => setMobile(false));

  const closeUserPanel = () => {
    if (!userPanel || !userToggle) return;
    userPanel.hidden = true;
    userToggle.setAttribute('aria-expanded', 'false');
  };

  if (userToggle && userPanel) {
    userToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const open = userPanel.hidden;
      userPanel.hidden = !open;
      userToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', (event) => {
      if (!userPanel.hidden && !userPanel.contains(event.target) && !userToggle.contains(event.target)) {
        closeUserPanel();
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    closeUserPanel();
    if (shell?.classList.contains('is-mobile-open')) setMobile(false);
  });

  // Close the mobile drawer after navigating.
  document.querySelectorAll('.estora-navlink').forEach((link) => {
    link.addEventListener('click', () => {
      if (mobileQuery.matches) setMobile(false);
    });
  });

  // Quick action: open the "new lead" dialog when arriving with #new.
  if (window.location.hash === '#new') {
    const attempt = (tries = 0) => {
      const ready = document.body.dataset.estoraLeadsReady === '1';
      const trigger = document.querySelector('[data-new-lead]');
      if (ready && trigger) {
        trigger.click();
        history.replaceState(null, '', window.location.pathname + window.location.search);
        return;
      }
      if (tries < 30) setTimeout(() => attempt(tries + 1), 150);
    };
    attempt();
  }

  // Notifications page (full list).
  const page = document.querySelector('[data-notifications-page]');
  if (page) {
    const list = page.querySelector('[data-notification-list]');
    const empty = page.querySelector('[data-notification-empty]');
    const markAll = document.querySelector('[data-notification-read-all-page]');

    const render = (items) => {
      if (!list) return;
      if (!items.length) {
        list.innerHTML = '';
        if (empty) empty.hidden = false;
        return;
      }
      if (empty) empty.hidden = true;
      list.innerHTML = items.map((item) => `
        <article class="estora-notification-row${item.is_read ? '' : ' is-unread'}" data-notification-row="${item.id}">
          <div>
            <h3>${escapeHtml(item.title)}</h3>
            <p>${escapeHtml(item.body || '')}</p>
            <time>${escapeHtml(formatDate(item.created_at))}</time>
          </div>
          <div>
            ${item.link ? `<a class="estora-rowbtn" href="${escapeHtml(item.link)}">Open</a>` : ''}
            ${item.is_read ? '' : `<button type="button" class="estora-rowbtn" data-mark-read="${item.id}">Mark read</button>`}
          </div>
        </article>`).join('');

      list.querySelectorAll('[data-mark-read]').forEach((button) => {
        button.addEventListener('click', async () => {
          try {
            await api(`/notifications/${button.dataset.markRead}/read`, 'POST', {});
            const row = button.closest('[data-notification-row]');
            if (row) row.classList.remove('is-unread');
            button.remove();
            window.estoraToast?.('Marked as read.');
          } catch (error) {
            window.estoraToast?.(error.message, 'error');
          }
        });
      });
    };

    const load = async () => {
      try {
        const payload = await api('/notifications', 'GET');
        render(payload.data || []);
      } catch (error) {
        if (list) list.innerHTML = `<p class="estora-muted">${escapeHtml(error.message)}</p>`;
      }
    };

    markAll?.addEventListener('click', async () => {
      try {
        await api('/notifications/read-all', 'POST', {});
        window.estoraToast?.('All notifications marked as read.');
        load();
      } catch (error) {
        window.estoraToast?.(error.message, 'error');
      }
    });

    load();
  }

  async function api(path, method, body) {
    const response = await fetch((cfg.api || '').replace(/\/$/, '') + path, {
      method,
      credentials: 'same-origin',
      headers: { 'content-type': 'application/json', 'x-wp-nonce': cfg.nonce },
      body: body === undefined ? undefined : JSON.stringify(body)
    });
    const payload = await response.json();
    if (!response.ok || payload.success === false) throw new Error(payload.message || 'Request failed.');
    return payload;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  }

  function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value || '');
    return date.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
})();
