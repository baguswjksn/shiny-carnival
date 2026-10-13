/* Generic CRUD table + modal form for master data (lines, orders). Config: window.MASTER_CONFIG. */
(() => {
  'use strict';
  const { api, toast, confirmDialog, setBusy, esc, clearFieldErrors, showFieldErrors } = App;
  const cfg = window.MASTER_CONFIG;

  const head = document.getElementById('records-head');
  const body = document.getElementById('records');
  const dlg = document.getElementById('form-dialog');
  const form = document.getElementById('record-form');
  const fieldsBox = document.getElementById('form-fields');
  const saveBtn = document.getElementById('save-btn');
  const colCount = cfg.columns.length + 1;

  let records = [];
  let editing = null;

  head.innerHTML = '<tr>' + cfg.columns.map((c) => `<th class="${c.num ? 'num' : ''}">${esc(c.label)}</th>`).join('') + '<th></th></tr>';

  async function load() {
    body.innerHTML = `<tr><td class="empty" colspan="${colCount}">Memuat data…</td></tr>`;
    try {
      records = (await api('GET', cfg.endpoint)).data;
      render();
    } catch (e) {
      body.innerHTML = `<tr><td class="empty" colspan="${colCount}">Gagal memuat data.</td></tr>`;
      toast(e.message, 'error');
    }
  }

  function render() {
    if (!records.length) {
      body.innerHTML = `<tr><td class="empty" colspan="${colCount}">Belum ada data.</td></tr>`;
      return;
    }
    body.innerHTML = records.map((r) => `
      <tr data-id="${r.id}">
        ${cfg.columns.map((c) => `<td class="${c.num ? 'num' : ''}">${c.render(r)}</td>`).join('')}
        <td class="actions">
          ${App.canManage ? `<button class="btn sm" data-action="edit">Edit</button>
          <button class="btn sm" data-action="delete">Hapus</button>` : ''}
        </td>
      </tr>`).join('');
  }

  function fieldHtml(f, value) {
    const attrs = [
      f.min != null ? `min="${f.min}"` : '',
      f.maxlength ? `maxlength="${f.maxlength}"` : '',
    ].join(' ');
    const control = f.type === 'checkbox'
      ? `<label class="check"><input type="checkbox" name="${f.name}" ${value ? 'checked' : ''}> ${esc(f.label)}</label>`
      : `<label for="f_${f.name}">${esc(f.label)}${f.required ? ' *' : ''}</label>
         <input id="f_${f.name}" type="${f.type}" name="${f.name}" value="${esc(value ?? '')}" ${attrs}>`;
    return `<div class="field">${control}<small class="field-error" data-error-for="${f.name}"></small></div>`;
  }

  function openForm(rec) {
    editing = rec;
    clearFieldErrors(form);
    document.getElementById('form-title').textContent = `${rec ? 'Edit' : 'Tambah'} ${cfg.entity === 'line' ? 'Line' : 'Order'}`;
    fieldsBox.innerHTML = cfg.fields.map((f) => fieldHtml(f, rec ? rec[f.name] : f.default)).join('');
    dlg.showModal();
    fieldsBox.querySelector('input')?.focus();
  }

  function collect() {
    const data = {};
    cfg.fields.forEach((f) => {
      const el = form.elements[f.name];
      if (f.type === 'checkbox') data[f.name] = el.checked;
      else if (f.type === 'number') data[f.name] = el.value === '' ? null : Number(el.value);
      else data[f.name] = el.value === '' ? null : el.value.trim();
    });
    return data;
  }

  document.getElementById('add-btn')?.addEventListener('click', () => openForm(null));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFieldErrors(form);
    setBusy(saveBtn, true);
    try {
      const url = editing ? `${cfg.endpoint}/${editing.id}` : cfg.endpoint;
      const res = await api(editing ? 'PUT' : 'POST', url, collect());
      if (editing) records = records.map((r) => (r.id === editing.id ? res.data : r));
      else records.unshift(res.data);
      dlg.close();
      render();
      toast(res.message, 'success');
      if (cfg.entity === 'line') load(); // keep ordering by code
    } catch (err) {
      if (err.status === 422) showFieldErrors(form, err.errors);
      else toast(err.message, 'error');
    } finally {
      setBusy(saveBtn, false);
    }
  });

  body.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const rec = records.find((r) => r.id === Number(btn.closest('tr').dataset.id));
    if (!rec) return;

    if (btn.dataset.action === 'edit') return openForm(rec);

    if (!(await confirmDialog(`Hapus ${cfg.entity} "${cfg.label(rec)}"?`))) return;
    setBusy(btn, true);
    try {
      const res = await api('DELETE', `${cfg.endpoint}/${rec.id}`);
      records = records.filter((r) => r.id !== rec.id);
      render();
      toast(res.message, 'success');
    } catch (err) {
      setBusy(btn, false);
      toast(err.message, 'error');
    }
  });

  load();
})();
