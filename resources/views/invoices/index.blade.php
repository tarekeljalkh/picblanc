@extends('layouts.master')

@section('title', 'Invoices')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bx bx-home"></i> Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Invoices</li>
        </ol>
    </nav>

    <div class="col-md">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0">Invoices ({{ $invoices->count() }})</h5>
                <a href="{{ route('invoices.create') }}" class="btn btn-primary">Create New Invoice</a>
            </div>
            <div class="card-body">
                <table id="invoicesTable" class="table table-striped table-bordered dt-responsive nowrap"
                    style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Paid</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>{{ $invoice->customer->name }}</td>
                                <td>${{ $invoice->total }}</td>
                                <td>{{ ucfirst($invoice->status) }}</td>
                                <td>
                                    @if($invoice->paid)
                                    <span class="badge bg-success">Paid</span>
                                @else
                                    <span class="badge bg-danger">Unpaid</span>
                                @endif

                                </td>
                                <td>
                                    <a href="{{ route('invoices.edit', $invoice->id) }}" class="btn btn-warning">Edit</a>
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-warning">show</a>
                                    <a href="{{ route('invoices.print', $invoice->id) }}" class="btn btn-warning">Print</a>
                                    @if (auth()->user()->role === 'admin')
                                        <form action="{{ route('invoices.destroy', $invoice->id) }}" method="POST"
                                            class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#invoicesTable').DataTable({
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                responsive: true
            });
        });
    </script>
@endpush
