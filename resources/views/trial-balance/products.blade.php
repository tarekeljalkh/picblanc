@extends('layouts.master')

@section('title', 'Trial Balance by Products')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bx bx-home"></i> Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Trial Balance by Products</li>
        </ol>
    </nav>

    <div class="col-md">
        <div class="card">
            <div class="card-header">
                <h5 class="m-0">Trial Balance by Products for {{ \Carbon\Carbon::parse($fromDate)->format('F j, Y') }} to {{ \Carbon\Carbon::parse($toDate)->format('F j, Y') }}</h5>
            </div>
            <div class="card-body">

                <!-- Date Range Filter Form -->
                <form method="GET" action="{{ route('trialbalance.products') }}" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="from_date" class="form-label">From Date</label>
                            <input type="date" id="from_date" name="from_date" class="form-control"
                                   value="{{ request('from_date', $fromDate ?? \Carbon\Carbon::today()->toDateString()) }}">
                        </div>
                        <div class="col-md-4">
                            <label for="to_date" class="form-label">To Date</label>
                            <input type="date" id="to_date" name="to_date" class="form-control"
                                   value="{{ request('to_date', $toDate ?? \Carbon\Carbon::today()->toDateString()) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </div>
                </form>

                <!-- Trial Balance Table -->
                @if (count($productBalances) > 0)
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity Sold</th>
                                <th>Price per Unit (USD)</th>
                                <th>Total Income (USD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productBalances as $balance)
                                <tr>
                                    <td>{{ $balance['product'] }}</td>
                                    <td>{{ $balance['quantity'] }}</td>
                                    <td>${{ number_format($balance['price_per_unit'], 2) }}</td>
                                    <td>${{ number_format($balance['total'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="3"><strong>Total Income</strong></td>
                                <td><strong>${{ number_format($totalIncome, 2) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <p>No product sales found for the selected date range.</p>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        flatpickr('#from_date', {
            dateFormat: "Y-m-d"
        });
        flatpickr('#to_date', {
            dateFormat: "Y-m-d"
        });
    </script>
@endpush