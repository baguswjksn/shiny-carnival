/* Shared helpers: fetch wrapper, toast, confirm dialog, busy state, field errors. */
(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  class ApiError extends Error {
    constructor(message, { status = 0, errors = {}, reason = null } = {}) {
      super(message);
      this.status = status;
      this.errors = errors;
      this.reason = reason;
    }
  }

  async function api(method, url, body, { timeout = 20000 } = {}) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeout);
    try {
      const res = await fetch(url, {
        method,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: ctrl.signal,
      });

      let json = null;
      try { json = await res.json(); } catch (_) { /* empty body */ }

      if (res.status === 401 || res.status === 419) {
        window.location.assign('/login'); // session expired or logged out
        throw new ApiError('Sesi berakhir. Silakan login lagi.', { status: res.status });
      }

      if (!res.ok) {
        throw new ApiError(json?.message || `Terjadi kesalahan (${res.status}).`, {
          status: res.status,
          errors: json?.errors,
          reason: json?.reason,
        });
      }
      return json;
    } catch (e) {
      if (e instanceof ApiError) throw e;
      if (e.name === 'AbortError') throw new ApiError('Permintaan terlalu lama, coba lagi.');
      throw new ApiError('Tidak bisa terhubung ke server.');
    } finally {
      clearTimeout(timer);
    }
  }

  function toast(message, type = 'info', ms = 4500) {
    const box = document.getElementById('toasts');
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    box.appendChild(el);
    setTimeout(() => {
      el.classList.add('hide');
      setTimeout(() => el.remove(), 300);
    }, ms);
  }

  function confirmDialog(message, okLabel = 'Ya, lanjutkan') {
    return new Promise((resolve) => {
      const dlg = document.getElementById('confirm-dialog');
      dlg.querySelector('[data-confirm-msg]').textContent = message;
      dlg.querySelector('[value="ok"]').textContent = okLabel;
      dlg.returnValue = '';
      const onClose = () => {
        dlg.removeEventListener('close', onClose);
        resolve(dlg.returnValue === 'ok');
      };
      dlg.addEventListener('close', onClose);
      dlg.showModal();
    });
  }

  function setBusy(btn, busy) {
    if (!btn) return;
    btn.disabled = busy;
    btn.classList.toggle('is-loading', busy);
    btn.setAttribute('aria-busy', String(busy));
  }

  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d} ${MONTHS[Number(m) - 1]} ${y}`;
  }

  function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; });
    form.querySelectorAll('.invalid').forEach((el) => el.classList.remove('invalid'));
  }

  function showFieldErrors(form, errors) {
    Object.entries(errors || {}).forEach(([name, msgs]) => {
      const slot = form.querySelector(`[data-error-for="${name}"]`);
      if (slot) slot.textContent = msgs[0];
      else toast(msgs[0], 'error');
      form.querySelector(`[name="${name}"]`)?.classList.add('invalid');
    });
  }

  document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-close]');
    if (close) close.closest('dialog')?.close();
  });

  window.App = { api, ApiError, toast, confirmDialog, setBusy, esc, fmtDate, clearFieldErrors, showFieldErrors };
})();
