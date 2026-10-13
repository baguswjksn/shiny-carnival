@extends('layouts.app')
@section('title', 'Schedule Produksi')

@section('content')
    <div class="page-head">
        <h1>Schedule Produksi</h1>
        @if(auth()->user()->isAdmin())
            <button class="btn primary" id="add-btn">+ Tambah Schedule</button>
        @endif
    </div>

    <form id="filter-form" class="filters">
        <label>Dari <input type="date" name="from"></label>
        <label>Sampai <input type="date" name="to"></label>
        <label>Line
            <select name="line_id"><option value="">Semua</option></select>
        </label>
        <label>Status
            <select name="status">
                <option value="">Semua</option>
                <option value="planned">Direncanakan</option>
                <option value="running">Berjalan</option>
                <option value="done">Selesai</option>
            </select>
        </label>
        <button type="submit" class="btn">Terapkan</button>
        <button type="button" class="btn ghost" id="reset-filter">Reset</button>
    </form>

    <div class="table-wrap">
        <table class="table schedule-table">
            <thead>
            <tr>
                <th>Order</th>
                <th>Line</th>
                <th>Start Sewing</th>
                <th>Finish Sewing</th>
                <th class="num">Qty Total Target</th>
                <th class="num">Target Output / Hari</th>
                <th class="num">Qty Aktual Output</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="schedule-body"></tbody>
        </table>
    </div>

    <dialog id="schedule-dialog" class="dialog">
        <form id="schedule-form" novalidate>
            <h2 id="form-title"></h2>
            <p class="note" id="locked-note" hidden>Schedule ini sudah punya aktual output, jadi tidak bisa diubah.</p>

            <div class="field">
                <label for="f_order">Order *</label>
                <select id="f_order" name="order_id"></select>
                <small class="hint" id="order-hint"></small>
                <small class="field-error" data-error-for="order_id"></small>
            </div>
            <div class="field">
                <label for="f_line">Line *</label>
                <select id="f_line" name="line_id"></select>
                <small class="field-error" data-error-for="line_id"></small>
            </div>
            <div class="row2">
                <div class="field">
                    <label for="f_start">Start sewing *</label>
                    <input id="f_start" type="date" name="start_date">
                    <small class="field-error" data-error-for="start_date"></small>
                </div>
                <div class="field">
                    <label for="f_finish">Finish sewing *</label>
                    <input id="f_finish" type="date" name="finish_date">
                    <small class="field-error" data-error-for="finish_date"></small>
                </div>
            </div>
            <div class="field">
                <label for="f_qty">Qty total target *</label>
                <input id="f_qty" type="number" min="1" name="target_qty">
                <small class="hint">Target output/hari: <strong id="preview">—</strong></small>
                <small class="field-error" data-error-for="target_qty"></small>
            </div>

            <div class="dialog-actions">
                <button type="button" class="btn" data-close>Batal</button>
                <button type="submit" class="btn primary" id="save-btn"><span class="spinner"></span>Simpan</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script src="{{ asset('js/schedule.js') }}"></script>
@endpush
