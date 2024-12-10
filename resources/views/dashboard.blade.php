@extends('layouts.master')

@section('title', 'Dashboard')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Card Widgets -->
        <div class="card mb-6">
            <div class="card-widget-separator-wrapper">
                <div class="card-body card-widget-separator">
                    <div class="row gy-4 gy-sm-1">
                        <!-- Customers Card -->
                        <div class="col-sm-6 col-lg-3">
                            <div
                                class="d-flex justify-content-between align-items-center card-widget-1 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0">{{ $customersCount }}</h4> <!-- Dynamic Customers Count -->
                                    <p class="mb-0">Customers</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-secondary text-heading">
                                        <i class="bx bx-user bx-26px"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none me-6">
                        </div>

                        <!-- Invoices Card -->
                        <div class="col-sm-6 col-lg-3">
                            <div
                                class="d-flex justify-content-between align-items-center card-widget-2 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0">{{ $invoicesCount }}</h4> <!-- Dynamic Invoices Count -->
                                    <p class="mb-0">Invoices</p>
                                </div>
                                <div class="avatar me-lg-6">
                                    <span class="avatar-initial rounded bg-label-secondary text-heading">
                                        <i class="bx bx-file bx-26px"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none">
                        </div>

                        <!-- Paid Invoices Card -->
                        <div class="col-sm-6 col-lg-3">
                            <a href="{{ route('invoices.index') }}"
                                class="d-flex justify-content-between align-items-center card-widget-3 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0">{{ $totalPaid }}</h4>
                                    <p class="mb-0">Paid Invoices</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-secondary text-heading">
                                        <i class="bx bx-check-double bx-26px"></i>
                                    </span>
                                </div>
                            </a>
                        </div>

                        <!-- Unpaid Invoices Card -->
                        <div class="col-sm-6 col-lg-3">
                            <a href="{{ route('drafts.index') }}"
                                class="d-flex justify-content-between align-items-center card-widget-3 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0">{{ $totalUnpaid }}</h4>
                                    <p class="mb-0">Unpaid Invoices</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-secondary text-heading">
                                        <i class="bx bx-time bx-26px"></i>
                                    </span>
                                </div>
                            </a>
                        </div>

                        <!-- Active Invoices Card -->
                        <div class="col-sm-6 col-lg-3">
                            <a href="{{ route('invoices.index', ['status' => 'active']) }}"
                                class="d-flex justify-content-between align-items-center card-widget-4 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0">{{ $notReturnedCount }}</h4>
                                    <p class="mb-0">Not Returned Invoices</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-secondary text-heading">
                                        <i class="bx bx-undo bx-26px"></i>
                                    </span>
                                </div>
                            </a>
                        </div>


                        <!-- Returned Invoices Card -->
                        <div class="col-sm-6 col-lg-3">
                            <a href="{{ route('invoices.index', ['status' => 'returned']) }}"
                                class="d-flex justify-content-between align-items-center card-widget-5 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0">{{ $returnedCount }}</h4>
                                    <p class="mb-0">Returned Invoices</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-secondary text-heading">
                                        <i class="bx bx-error bx-26px"></i>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice List Table -->
        <div class="card">
            <div class="card-datatable table-responsive">
                <table class="invoice-list-table table border-top">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Total</th>
                            <th>Return Date</th>
                            <th>Payment Status</th>
                            <th>Invoice Status</th>
                            <th class="cell-fit">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->customer->name ?? 'N/A' }}</td>
                                <td>${{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                <td>{{ $invoice->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge {{ $invoice->paid ? 'bg-success' : 'bg-danger' }}">
                                        {{ $invoice->paid ? 'Paid' : 'Unpaid' }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $statusClasses = [
                                            'draft' => 'bg-warning',
                                            'active' => 'bg-success',
                                            'returned' => 'bg-info',
                                            'overdue' => 'bg-danger',
                                        ];
                                    @endphp

                                    <span class="badge {{ $statusClasses[$invoice->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-info">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-3">
                    <!-- Custom Pagination Styling -->
                    <nav>
                        <ul class="pagination justify-content-center">
                            {{ $invoices->onEachSide(1)->links('pagination::bootstrap-4') }}
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <!-- End Invoice List Table -->
    </div>
@endsection
