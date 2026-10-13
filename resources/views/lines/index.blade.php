@extends('layouts.app')
@section('title', 'Master Line')

@section('content')
    <div class="page-head">
        <h1>Master Line</h1>
        @if(auth()->user()->isAdmin())
            <button class="btn primary" id="add-btn">+ Tambah Line</button>
        @endif
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead id="records-head"></thead>
            <tbody id="records"></tbody>
        </table>
    </div>
    @include('partials.form-dialog')
@endsection

@push('scripts')
<script>
window.MASTER_CONFIG = {
    entity: 'line',
    endpoint: '/api/lines',
    label: (r) => r.code,
    columns: [
        { label: 'Kode', render: (r) => `<strong>${App.esc(r.code)}</strong>` },
        { label: 'Nama', render: (r) => App.esc(r.name) },
        { label: 'Kapasitas/hari', num: true, render: (r) => r.daily_capacity ?? '—' },
        { label: 'Jumlah schedule', num: true, render: (r) => r.schedules_count },
        { label: 'Status', render: (r) => r.is_active ? '<span class="badge ok">Aktif</span>' : '<span class="badge">Nonaktif</span>' },
    ],
    fields: [
        { name: 'code', label: 'Kode line', type: 'text', required: true, maxlength: 20 },
        { name: 'name', label: 'Nama line', type: 'text', required: true, maxlength: 100 },
        { name: 'daily_capacity', label: 'Kapasitas output/hari (opsional)', type: 'number', min: 1 },
        { name: 'is_active', label: 'Line aktif', type: 'checkbox', default: true },
    ],
};
</script>
<script src="{{ asset('js/master.js') }}"></script>
@endpush
