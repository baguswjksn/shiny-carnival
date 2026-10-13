/* Schedule management page: list, create/edit/delete, daily actual input with live balancing. */
(() => {
  'use strict';
  const { api, toast, confirmDialog, setBusy, esc, fmtDate, clearFieldErrors, showFieldErrors } = App;
  const $ = (sel, root = document) => root.querySelector(sel);
  const CAN = App.canManage; // false for read-only accounts

  const body = $('#schedule-body');
  const dlg = $('#schedule-dialog');
  const form = $('#schedule-form');
  const saveBtn = $('#save-btn');
  const filterForm = $('#filter-form');

  const STATUS = { planned: 'Direncanakan', running: 'Berjalan', done: 'Selesai' };
  const state = {
    schedules: [],
    orders: [],
    lines: [],
    expanded: new Set(),
    flash: new Set(),
    editing: null,
  };

  const byId = (id) => state.schedules.find((s) => s.id === id);
  const toInt = (v) => (v === '' ? null : Number(v));

  // ------------------------------------------------------------------ data

  async function loadMasters() {
    try {
      const [o, l] = await Promise.all([api('GET', '/api/orders'), api('GET', '/api/lines')]);
      state.orders = o.data;
      state.lines = l.data;
      const sel = filterForm.elements.line_id;
      const current = sel.value;
      sel.innerHTML = '<option value="">Semua</option>'
        + state.lines.map((x) => `<option value="${x.id}">${esc(x.code)}</option>`).join('');
      sel.value = current;
    } catch (e) {
      toast(`Gagal memuat master data: ${e.message}`, 'error');
    }
  }

  async function refreshOrders() {
    try { state.orders = (await api('GET', '/api/orders')).data; } catch (_) { /* non-critical */ }
  }

  async function load() {
    body.innerHTML = '<tr><td class="empty" colspan="9">Memuat schedule…</td></tr>';
    const params = new URLSearchParams();
    new FormData(filterForm).forEach((v, k) => { if (v) params.set(k, v); });
    try {
      state.schedules = (await api('GET', `/api/schedules?${params}`)).data;
      render();
    } catch (e) {
      body.innerHTML = '<tr><td class="empty" colspan="9">Gagal memuat schedule.</td></tr>';
      toast(e.message, 'error');
    }
  }

  function upsert(s) {
    const i = state.schedules.findIndex((x) => x.id === s.id);
    if (i === -1) state.schedules.push(s); else state.schedules[i] = s;
  }

  // ------------------------------------------------------------------ render

  function render() {
    const list = state.schedules.slice().sort((a, b) =>
      a.line.code.localeCompare(b.line.code) || a.start_date.localeCompare(b.start_date));

    if (!list.length) {
      body.innerHTML = '<tr><td class="empty" colspan="9">Belum ada schedule. Klik “Tambah Schedule”.</td></tr>';
      return;
    }

    let html = '';
    let lastLine = null;
    for (const s of list) {
      if (s.line.id !== lastLine) {
        lastLine = s.line.id;
        html += `<tr class="group"><td colspan="9">${esc(s.line.code)} — ${esc(s.line.name)}</td></tr>`;
      }
      html += rowHtml(s);
      if (state.expanded.has(s.id)) html += detailHtml(s);
    }
    body.innerHTML = html;

    state.flash.forEach((id) => body.querySelector(`tr.main[data-sid="${id}"]`)?.classList.add('flash'));
    state.flash.clear();
  }

  function rowHtml(s) {
    const open = state.expanded.has(s.id);
    const pct = s.target_qty ? Math.min(100, Math.round((s.actual_qty / s.target_qty) * 100)) : 0;
    const delay = s.delay_days > 0 ? ` <span class="badge warn" title="Mundur dari rencana awal">+${s.delay_days} hari</span>` : '';
    const perDay = s.last_day_target !== s.target_per_day
      ? `${s.target_per_day} <small>(hari terakhir ${s.last_day_target})</small>`
      : `${s.target_per_day}`;
    const lockedTitle = s.locked ? 'title="Sudah ada aktual, tidak bisa diubah"' : '';

    return `
      <tr class="main" data-sid="${s.id}">
        <td>
          <button class="link" data-action="toggle" aria-expanded="${open}" aria-label="Detail harian">${open ? '▾' : '▸'}</button>
          <strong>${esc(s.order.order_no)}</strong><br>
          <small>${esc(s.order.buyer)} · ${esc(s.order.style)}</small>
        </td>
        <td>${esc(s.line.code)}</td>
        <td>${fmtDate(s.start_date)}</td>
        <td>${fmtDate(s.finish_date)}${delay}</td>
        <td class="num">${s.target_qty}</td>
        <td class="num">${perDay}</td>
        <td class="num">${s.actual_qty}<div class="bar"><span style="width:${pct}%"></span></div></td>
        <td><span class="badge ${s.status}">${STATUS[s.status] ?? s.status}</span></td>
        <td class="actions">
          ${CAN ? `<button class="btn sm" data-action="edit" ${s.locked ? 'disabled' : ''} ${lockedTitle}>Edit</button>
          <button class="btn sm" data-action="delete" ${s.locked ? 'disabled' : ''} ${lockedTitle}>Hapus</button>` : ''}
        </td>
      </tr>`;
  }

  function detailHtml(s) {
    const rows = s.days.map((d) => {
      const v = d.variance;
      const vCls = v === null ? '' : (v < 0 ? 'neg' : 'pos');
      const carry = d.carry_in === 0 ? '—' : (d.carry_in > 0 ? `+${d.carry_in}` : `${d.carry_in}`);
      return `
        <tr data-date="${d.work_date}">
          <td>${fmtDate(d.work_date)}${d.is_extension ? ' <span class="badge warn">tambahan</span>' : ''}</td>
          <td class="num">${d.base_target}</td>
          <td class="num ${d.carry_in > 0 ? 'neg' : ''}">${carry}</td>
          <td class="num"><strong>${d.target_qty}</strong></td>
          <td class="num"><input type="number" min="0" class="actual-input" value="${d.actual_qty ?? ''}" placeholder="—" aria-label="Aktual ${d.work_date}" ${CAN ? '' : 'disabled'}></td>
          <td class="num ${vCls}">${v === null ? '—' : (v > 0 ? '+' : '') + v}</td>
          <td>${CAN ? '<button class="btn sm" data-action="save-actual">Simpan</button>' : ''}</td>
        </tr>`;
    }).join('');

    return `
      <tr class="detail" data-sid="${s.id}">
        <td colspan="9">
          <div class="days" data-sid="${s.id}">
            <table class="days-table">
              <thead><tr>
                <th>Tanggal</th><th class="num">Target awal</th><th class="num">Carry</th>
                <th class="num">Target hari ini</th><th class="num">Aktual</th><th class="num">Selisih</th><th></th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
        </td>
      </tr>`;
  }

  // ------------------------------------------------------------------ errors

  async function handleError(err, formEl = null) {
    if (err.status === 422 && formEl) { showFieldErrors(formEl, err.errors); return; }

    if (err.reason === 'stale') {
      if (dlg.open) dlg.close();
      toast(err.message, 'error');
      if (await confirmDialog('Data schedule sudah berubah. Muat ulang sekarang?', 'Muat ulang')) {
        await Promise.all([load(), refreshOrders()]);
      }
      return;
    }

    const first = err.status === 422 ? Object.values(err.errors || {})[0]?.[0] : null;
    toast(first || err.message, 'error');
  }

  // ------------------------------------------------------------------ actions

  function applyResult(data) {
    upsert(data.schedule);
    state.flash.add(data.schedule.id);
    (data.shifted || []).forEach((s) => { upsert(s); state.flash.add(s.id); });
    render();
  }

  async function saveActual(s, btn) {
    const tr = btn.closest('tr[data-date]');
    const input = tr.querySelector('.actual-input');
    const raw = input.value.trim();

    if (!/^\d+$/.test(raw)) {
      input.classList.add('invalid');
      input.focus();
      toast('Isi qty aktual dengan angka 0 atau lebih.', 'error');
      return;
    }
    input.classList.remove('invalid');

    setBusy(btn, true);
    input.disabled = true;
    tr.classList.add('row-busy');

    try {
      const res = await api('PUT', `/api/schedules/${s.id}/days/${tr.dataset.date}/actual`,
        { actual_qty: Number(raw), version: s.version });
      applyResult(res.data);
      toast(res.message, 'success');
      const next = [...body.querySelectorAll(`.days[data-sid="${s.id}"] .actual-input`)].find((i) => i.value === '');
      next?.focus();
    } catch (err) {
      setBusy(btn, false);
      input.disabled = false;
      tr.classList.remove('row-busy');
      await handleError(err);
    }
  }

  async function remove(s, btn) {
    if (!(await confirmDialog(`Hapus schedule ${s.order.order_no} di ${s.line.code}?`))) return;
    setBusy(btn, true);
    try {
      const res = await api('DELETE', `/api/schedules/${s.id}`);
      state.schedules = state.schedules.filter((x) => x.id !== s.id);
      state.expanded.delete(s.id);
      render();
      toast(res.message, 'success');
      refreshOrders();
    } catch (err) {
      setBusy(btn, false);
      await handleError(err);
    }
  }

  body.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn || btn.disabled) return;
    const s = byId(Number(btn.closest('[data-sid]').dataset.sid));
    if (!s) return;

    switch (btn.dataset.action) {
      case 'toggle':
        if (state.expanded.has(s.id)) state.expanded.delete(s.id); else state.expanded.add(s.id);
        render();
        break;
      case 'edit': openForm(s); break;
      case 'delete': await remove(s, btn); break;
      case 'save-actual': await saveActual(s, btn); break;
    }
  });

  body.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList.contains('actual-input')) {
      e.preventDefault();
      e.target.closest('tr').querySelector('[data-action="save-actual"]').click();
    }
  });

  // ------------------------------------------------------------------ form

  function availableQty(orderId) {
    const o = state.orders.find((x) => x.id === Number(orderId));
    if (!o) return null;
    const own = state.editing && state.editing.order.id === o.id ? state.editing.target_qty : 0;
    return o.remaining_qty + own;
  }

  function fillSelects(s) {
    const curOrder = s?.order.id;
    form.order_id.innerHTML = '<option value="">— pilih order —</option>'
      + state.orders
        .filter((o) => o.remaining_qty > 0 || o.id === curOrder)
        .map((o) => `<option value="${o.id}">${esc(o.order_no)} · ${esc(o.buyer)} (sisa ${o.remaining_qty + (o.id === curOrder ? s.target_qty : 0)})</option>`)
        .join('');

    form.line_id.innerHTML = '<option value="">— pilih line —</option>'
      + state.lines
        .filter((l) => l.is_active || l.id === s?.line.id)
        .map((l) => `<option value="${l.id}">${esc(l.code)} — ${esc(l.name)}</option>`)
        .join('');
  }

  function updateHint() {
    const a = availableQty(form.order_id.value);
    $('#order-hint').textContent = a === null ? '' : `Sisa qty yang bisa dijadwalkan: ${a}`;
  }

  function updatePreview() {
    const start = form.start_date.value;
    const finish = form.finish_date.value;
    const qty = Number(form.target_qty.value);
    const el = $('#preview');
    if (!start || !finish || !qty || finish < start) { el.textContent = '—'; return; }
    const days = Math.round((new Date(finish) - new Date(start)) / 86400000) + 1;
    const base = Math.floor(qty / days);
    const rest = qty % days;
    el.textContent = `${days} hari · ${base}/hari${rest ? ` · hari terakhir ${base + rest}` : ''}`;
  }

  function openForm(s) {
    state.editing = s;
    form.reset();
    clearFieldErrors(form);
    $('#form-title').textContent = s ? 'Edit Schedule' : 'Tambah Schedule';
    fillSelects(s);

    if (s) {
      form.order_id.value = s.order.id;
      form.line_id.value = s.line.id;
      form.start_date.value = s.start_date;
      form.finish_date.value = s.finish_date;
      form.target_qty.value = s.target_qty;
    } else {
      form.start_date.value = new Date().toLocaleDateString('en-CA');
    }

    const locked = Boolean(s && s.locked);
    $('#locked-note').hidden = !locked;
    [...form.elements].forEach((el) => { if (el.name) el.disabled = locked; });
    saveBtn.disabled = locked;

    updateHint();
    updatePreview();
    dlg.showModal();
  }

  form.order_id.addEventListener('change', () => {
    const a = availableQty(form.order_id.value);
    if (a !== null) form.target_qty.value = a;
    updateHint();
    updatePreview();
  });
  ['start_date', 'finish_date', 'target_qty'].forEach((n) => form.elements[n].addEventListener('input', updatePreview));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFieldErrors(form);

    const payload = {
      order_id: toInt(form.order_id.value),
      line_id: toInt(form.line_id.value),
      start_date: form.start_date.value,
      finish_date: form.finish_date.value,
      target_qty: toInt(form.target_qty.value),
    };
    if (state.editing) payload.version = state.editing.version;

    setBusy(saveBtn, true);
    try {
      const res = await api(
        state.editing ? 'PUT' : 'POST',
        state.editing ? `/api/schedules/${state.editing.id}` : '/api/schedules',
        payload
      );
      upsert(res.data.schedule);
      state.flash.add(res.data.schedule.id);
      dlg.close();
      render();
      toast(res.message, 'success');
      refreshOrders();
    } catch (err) {
      await handleError(err, form);
    } finally {
      setBusy(saveBtn, false);
    }
  });

  $('#add-btn')?.addEventListener('click', () => openForm(null));
  filterForm.addEventListener('submit', (e) => { e.preventDefault(); load(); });
  $('#reset-filter').addEventListener('click', () => { filterForm.reset(); load(); });

  loadMasters();
  load();
})();
