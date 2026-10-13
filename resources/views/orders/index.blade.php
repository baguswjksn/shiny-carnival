@extends('layouts.app')
@section('title', 'Master Order')

@section('content')
    <div class="page-head">
        <h1>Master Order</h1>
        @if(auth()->user()->isAdmin())
            <button class="btn primary" id="add-btn">+ Tambah Order</button>
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
const ORDER_STATUS = { open: 'Open', scheduled: 'Terjadwal', done: 'Selesai' };
window.MASTER_CONFIG = {
    entity: 'order',
    endpoint: '/api/orders',
    label: (r) => r.order_no,
    columns: [
        { label: 'No. Order', render: (r) => `<strong>${App.esc(r.order_no)}</strong>` },
        { label: 'Buyer', render: (r) => App.esc(r.buyer) },
        { label: 'Style', render: (r) => App.esc(r.style) },
        { label: 'Qty order', num: true, render: (r) => r.total_qty },
        { label: 'Terjadwal', num: true, render: (r) => r.scheduled_qty },
        { label: 'Sisa', num: true, render: (r) => r.remaining_qty },
        { label: 'Due date', render: (r) => App.fmtDate(r.due_date) },
        { label: 'Status', render: (r) => `<span class="badge ${r.status}">${ORDER_STATUS[r.status] ?? r.status}</span>` },
    ],
    fields: [
        { name: 'order_no', label: 'No. order', type: 'text', required: true, maxlength: 50 },
        { name: 'buyer', label: 'Buyer', type: 'text', required: true, maxlength: 100 },
        { name: 'style', label: 'Style', type: 'text', required: true, maxlength: 100 },
        { name: 'total_qty', label: 'Qty order', type: 'number', required: true, min: 1 },
        { name: 'due_date', label: 'Due date (opsional)', type: 'date' },
    ],
};
</script>
<script src="{{ asset('js/master.js') }}"></script>
@endpush
