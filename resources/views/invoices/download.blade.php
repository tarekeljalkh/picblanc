<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice #{{ $invoice->id }}</title>

    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 20px;
            max-width: 800px;
            margin: auto;
        }

        .d-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        table th,
        table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 12px;
        }

        table th {
            background-color: #f9f9f9;
        }

        .text-end {
            text-align: right;
        }

        .fw-bold {
            font-weight: bold;
        }

        .text-danger {
            color: red;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Invoice Header -->
        <div class="d-flex">
            <div>
                <img src="{{ public_path('logo_croped.png') }}" alt="Logo" width="150">
                <p>Mayrouba Rental Shop</p>
                <p>Tel: 03 71 57 57</p>
            </div>
            <div>
                <h5>Rental Agreement #{{ $invoice->id }}</h5>
                <p><strong>Date Issued:</strong> {{ $invoice->created_at->format('M d, Y') }}</p>
                <p><strong>Rental Start:</strong> {{ $invoice->rental_start_date->format('d/m/Y') }}</p>
                <p><strong>Rental End:</strong> {{ $invoice->rental_end_date->format('d/m/Y') }}</p>
                <p><strong>Rental Days:</strong> {{ $invoice->days }} day(s)</p>
            </div>
        </div>

        <hr />

        <!-- Customer Details -->
        <div>
            <h6>Invoice To:</h6>
            <p>{{ $invoice->customer->name }}</p>
            <p>{{ $invoice->customer->address }}</p>
            <p>{{ $invoice->customer->phone }}</p>
            <p>{{ $invoice->customer->email }}</p>
        </div>

        <hr />

        <!-- Invoice Items -->
        <h6>Invoice Items</h6>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Unit Price</th>
                    <th>Qty</th>
                    <th>Total Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td>${{ number_format($item->price, 2) }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->price * $item->quantity * $item->days, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Additional Items -->
        @if ($invoice->additionalItems->isNotEmpty())
            <h6>Additional Items</h6>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit Price</th>
                        <th>Qty</th>
                        <th>Total Price</th>
                        <th>Added Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->additionalItems as $addedItem)
                        <tr>
                            <td>{{ $addedItem->product->name }}</td>
                            <td>${{ number_format($addedItem->price, 2) }}</td>
                            <td>{{ $addedItem->quantity }}</td>
                            <td>${{ number_format($addedItem->total_price, 2) }}</td>
                            <td>{{ optional($addedItem->added_date)->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <!-- Invoice Summary -->
        <h6>Invoice Summary</h6>
        <table>
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td class="text-end">${{ number_format($totals['subtotal'], 2) }}</td>
            </tr>
            @if ($totals['additionalCost'] > 0)
                <tr>
                    <td><strong>Additional Costs:</strong></td>
                    <td class="text-end">${{ number_format($totals['additionalCost'], 2) }}</td>
                </tr>
            @endif
            @if ($totals['returnedCost'] > 0)
                <tr>
                    <td><strong>Returned Costs:</strong></td>
                    <td class="text-end text-danger">- ${{ number_format($totals['returnedCost'], 2) }}</td>
                </tr>
            @endif
            <tr>
                <td><strong>Discount:</strong></td>
                <td class="text-end">- ${{ number_format($totals['discountAmount'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total:</strong></td>
                <td class="text-end">${{ number_format($totals['total'], 2) }}</td>
            </tr>
        </table>

        <!-- Salesperson -->
        <p><strong>Salesperson:</strong> {{ $invoice->user->name ?? 'N/A' }}</p>
        Thanks for your business!<br>
        <span>
            {{ $invoice->paid ? 'Payment: Paid' : 'Payment: Not Paid' }}
        </span>

        <hr />

        <!-- Conditions -->
        <p><strong>CONDITION:</strong> I declare having received the merchandise mentioned above in good condition and agree to return it on time. I will reimburse the value of any missing, damaged, or broken article.</p>
    </div>
</body>

</html>
