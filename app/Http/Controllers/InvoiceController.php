<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\AdditionalItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ReturnDetail;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\FileUploadTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    use FileUploadTrait;

    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     // Retrieve the selected category from the session, default to 'daily' if none is set
    //     $selectedCategory = session('category', 'daily');

    //     // Fetch invoices based on the selected category
    //     $invoices = Invoice::with('customer')
    //         ->whereHas('category', function ($query) use ($selectedCategory) {
    //             $query->where('name', $selectedCategory);
    //         })
    //         ->get();

    //     // Pass the selected category to the view
    //     return view('invoices.index', compact('invoices', 'selectedCategory'));
    // }

    public function index(Request $request)
    {
        // Retrieve the selected category from the session, default to 'daily' if none is set
        $selectedCategory = session('category', 'daily');

        // Get the status and payment filters from the request
        $status = $request->query('status');
        $paymentStatus = $request->query('payment_status');

        // Fetch invoices based on the selected category and optional status/payment filters
        $invoices = Invoice::with('customer')
            ->whereHas('category', function ($query) use ($selectedCategory) {
                $query->where('name', $selectedCategory);
            })
            ->when($status, function ($query, $status) {
                // Filter by invoice status
                $query->where('status', $status);
            })
            ->when($paymentStatus === 'paid', function ($query) {
                // Filter for fully paid invoices
                $query->whereColumn('paid_amount', '>=', 'total_amount');
            })
            ->when($paymentStatus === 'unpaid', function ($query) {
                // Filter for unpaid invoices
                $query->where('paid_amount', 0);
            })
            ->when($paymentStatus === 'partially_paid', function ($query) {
                // Filter for partially paid invoices
                $query->where('paid_amount', '>', 0)
                    ->whereColumn('paid_amount', '<', 'total_amount');
            })
            ->paginate(10); // Paginate results for better performance

        // Pass the selected category, status, and payment status to the view
        return view('invoices.index', compact('invoices', 'selectedCategory', 'status', 'paymentStatus'));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::all();
        $products = Product::all();
        return view('invoices.create', compact('customers', 'products'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Retrieve the selected category from the session
        $categoryName = session('category', 'daily');

        // Dynamic validation rules based on the category
        $rules = [
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255|required_without:customer_id',
            'customer_phone' => 'nullable|string|max:255|required_without:customer_id',
            'customer_address' => 'nullable|string|max:255|required_without:customer_id',
            'products' => 'required|array|min:1',
            'products.*' => 'exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'integer|min:1',
            'prices' => 'required|array|min:1',
            'prices.*' => 'numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0|max:100',
            'deposit' => 'nullable|numeric|min:0',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,credit_card',
            'note' => 'nullable',
        ];

        if ($categoryName === 'daily') {
            $rules['rental_start_date'] = 'required|date';
            $rules['rental_end_date'] = 'required|date|after_or_equal:rental_start_date';
            $rules['days'] = 'required|integer|min:1';
        }

        $request->validate($rules);

        try {
            DB::beginTransaction();

            // Handle Customer
            if ($request->filled('customer_id')) {
                $customer = Customer::findOrFail($request->customer_id);
            } else {
                $customer = Customer::create([
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'address' => $request->customer_address,
                ]);
            }

            // Retrieve the selected category
            $category = Category::where('name', $categoryName)->firstOrFail();

            // Calculate totals
            $subtotal = 0;
            $invoiceItems = [];

            foreach ($request->products as $index => $product_id) {
                $quantity = $request->quantities[$index];
                $price = $request->prices[$index];

                if ($categoryName === 'daily') {
                    $rentalDays = $request->days;

                    $totalPrice = $quantity * $price * $rentalDays;
                    $invoiceItems[] = new InvoiceItem([
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_price' => $totalPrice,
                        'rental_start_date' => $request->rental_start_date,
                        'rental_end_date' => $request->rental_end_date,
                        'days' => $rentalDays,
                        'returned_quantity' => 0,
                        'added_quantity' => 0,
                    ]);
                } else {
                    $totalPrice = $quantity * $price;
                    $invoiceItems[] = new InvoiceItem([
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_price' => $totalPrice,
                        'returned_quantity' => 0,
                        'added_quantity' => 0,
                    ]);
                }

                $subtotal += $totalPrice;
            }

            // Apply discount
            $totalDiscount = $request->total_discount ?? 0;
            $discountAmount = ($subtotal * $totalDiscount) / 100;

            // Calculate the total amount after applying the discount
            $totalAmount = $subtotal - $discountAmount;

            // Subtract deposit from total amount as it's pre-paid
            $deposit = $request->deposit ?? 0;
            $totalAmount -= $deposit;

            // Calculate paid amount (including deposit)
            $paidAmount = $deposit + ($request->payment_amount ?? 0);

            // Create the Invoice
            $invoiceData = [
                'customer_id' => $customer->id,
                'user_id' => auth()->user()->id,
                'category_id' => $category->id,
                'total_discount' => $totalDiscount,
                'deposit' => $deposit,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
            ];

            if ($categoryName === 'daily') {
                $invoiceData['rental_start_date'] = $request->rental_start_date;
                $invoiceData['rental_end_date'] = $request->rental_end_date;
                $invoiceData['days'] = $request->days;
            }

            $invoice = Invoice::create($invoiceData);

            // Attach items to the invoice
            $invoice->items()->saveMany($invoiceItems);

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)->with('success', 'Invoice created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store Invoice Exception:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return redirect()->back()->with('error', 'An error occurred while creating the invoice.');
        }
    }



    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $invoice = Invoice::with([
            'items.product',
            'additionalItems',
            'returnDetails.invoiceItem.product', // Load product for invoiceItem
            'returnDetails.additionalItem.product' // Load product for additionalItem
        ])->findOrFail($id);

        $totals = $invoice->calculateTotals();

        return view('invoices.show', compact('invoice', 'totals'));
    }



    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $invoice = Invoice::with('items')->findOrFail($id);
        $customers = Customer::all();
        $products = Product::all();

        // Determine if the mode is seasonal
        $isSeasonal = session('category') === 'season'; // Replace 'category' logic as per your app rules


        return view('invoices.edit', compact('invoice', 'customers', 'products', 'isSeasonal'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'products' => 'required|array|min:1',
            'products.*' => 'exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'integer|min:1',
            'prices' => 'required|array|min:1',
            'prices.*' => 'numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'paid' => 'required|in:0,1',
        ]);

        $invoice = Invoice::findOrFail($id);

        // Update customer if a new customer_id is provided
        if ($request->filled('customer_id') && $request->customer_id != $invoice->customer_id) {
            $invoice->customer_id = $request->customer_id;
        }

        // Calculate rental days for updated items
        $rentalStartDate = Carbon::parse($request->rental_start_date);
        $rentalEndDate = Carbon::parse($request->rental_end_date);
        $rentalDays = $rentalStartDate->diffInDays($rentalEndDate);

        // Update invoice details
        $invoice->total_discount = $request->total_discount ?? 0;
        $invoice->paid = (bool) $request->paid;
        $invoice->status = 'active';

        // Recalculate subtotal, discount, and total for updated items
        $subtotal = 0;
        $invoice->items()->delete();
        $invoiceItems = [];

        foreach ($request->products as $index => $product_id) {
            $quantity = $request->quantities[$index];
            $price = $request->prices[$index];
            $totalPrice = $quantity * $price * $rentalDays;
            $subtotal += $totalPrice;

            $invoiceItems[] = new InvoiceItem([
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $price,
                'total_price' => $totalPrice,
            ]);
        }

        // Calculate total and discount
        $discountAmount = ($subtotal * $invoice->total_discount) / 100;
        $invoice->total = $subtotal - $discountAmount;
        $invoice->save();

        // Attach updated items to the invoice
        $invoice->items()->saveMany($invoiceItems);

        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            $invoice->delete();

            return response()->json(['status' => 'success', 'message' => 'Deleted Successfully!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong!']);
        }
    }

    public function print($id)
    {
        $invoice = Invoice::with('items.product', 'additionalItems', 'returnDetails')->findOrFail($id);

        $totals = $invoice->calculateTotals();

        return view('invoices.print', compact('invoice', 'totals'));
    }


    /**
     * Download the invoice as PDF.
     */
    public function download($id)
    {
        $invoice = Invoice::with('items.product', 'additionalItems', 'returnDetails')->findOrFail($id);

        $totals = $invoice->calculateTotals();

        $pdf = Pdf::loadView('invoices.download', compact('invoice', 'totals'));

        return $pdf->download("invoice-{$invoice->id}.pdf");
    }

    public function processReturns(Request $request, $invoiceId)
    {
        $isSeasonal = session('category') === 'season';

        $messages = [
            'returns.required' => 'You must provide at least one return item.',
            'returns.*.*.quantity.required_if' => 'The quantity is required when an item is selected.',
            'returns.*.*.quantity.integer' => 'The quantity must be a whole number.',
            'returns.*.*.quantity.min' => 'The quantity must be at least 1.',
            'returns.*.*.days_of_use.required_if' => 'The days of use is required when an item is selected.',
            'returns.*.*.days_of_use.integer' => 'The days of use must be a whole number.',
            'returns.*.*.return_date.required_if' => 'The return date is required when an item is selected.',
            'returns.*.*.return_date.date' => 'The return date must be a valid date.',
        ];

        $attributes = [
            'returns.*.*.selected' => 'item selection',
            'returns.*.*.quantity' => 'returned quantity',
            'returns.*.*.days_of_use' => 'days of use',
            'returns.*.*.return_date' => 'return date',
        ];

        $rules = [
            'returns' => 'required|array',
            'returns.*.*.selected' => 'sometimes|required|boolean',
            'returns.*.*.quantity' => 'required_if:returns.*.*.selected,1|integer|min:1',
            'returns.*.*.days_of_use' => 'required_if:returns.*.*.selected,1|integer|min:1',
            'returns.*.*.return_date' => $isSeasonal ? 'nullable|date' : 'required_if:returns.*.*.selected,1|date',
        ];

        $validated = $request->validate($rules, $messages, $attributes);

        $invoice = Invoice::findOrFail($invoiceId);

        DB::beginTransaction();

        try {
            foreach ($validated['returns'] as $type => $items) {
                foreach ($items as $id => $item) {
                    if (!isset($item['selected']) || !$item['selected']) {
                        continue;
                    }

                    $model = ($type === 'original')
                        ? InvoiceItem::findOrFail($id)
                        : AdditionalItem::findOrFail($id);

                    $returnedQuantity = $item['quantity'];
                    $daysUsed = $item['days_of_use']; // Directly use days_of_use from the request

                    if ($returnedQuantity > $model->quantity - $model->returned_quantity) {
                        throw new \Exception('Returned quantity exceeds available quantity.');
                    }

                    $refundAmount = 0;
                    if ($daysUsed < $model->days) {
                        $unusedDays = $model->days - $daysUsed;
                        $refundAmount = $unusedDays * $model->price * $returnedQuantity;
                    }

                    ReturnDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_item_id' => $type === 'original' ? $model->id : null,
                        'additional_item_id' => $type === 'additional' ? $model->id : null,
                        'product_id' => $model->product_id,
                        'returned_quantity' => $returnedQuantity,
                        'days_used' => $daysUsed,
                        'cost' => $refundAmount,
                        'return_date' => Carbon::parse($item['return_date']),
                    ]);

                    $model->returned_quantity += $returnedQuantity;
                    if ($model->returned_quantity >= $model->quantity) {
                        $model->status = 'returned';
                    }
                    $model->save();
                }
            }

            // Check if all items and additional items are fully returned
            $allOriginalItemsReturned = $invoice->items()->whereColumn('returned_quantity', '<', 'quantity')->doesntExist();
            $allAdditionalItemsReturned = $invoice->additionalItems()->whereColumn('returned_quantity', '<', 'quantity')->doesntExist();

            if ($allOriginalItemsReturned && $allAdditionalItemsReturned) {
                $invoice->status = 'returned';
            } else {
                $invoice->status = 'active';
            }

            $invoice->save();

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Returns processed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to process returns: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to recalculate the total amount of the invoice.
     */
    private function calculateInvoiceTotal($invoice)
    {
        $isSeasonal = $invoice->category->name === 'season';

        // Recalculate original items
        $originalTotal = $invoice->items->sum(function ($item) use ($isSeasonal) {
            $remainingQuantity = $item->quantity - $item->returned_quantity;
            return $isSeasonal
                ? $item->price * $remainingQuantity
                : $item->price * $remainingQuantity * ($item->days ?? 1);
        });

        // Recalculate additional items
        $additionalTotal = $invoice->additionalItems->sum(function ($item) use ($isSeasonal) {
            $remainingQuantity = $item->quantity - $item->returned_quantity;
            return $isSeasonal
                ? $item->price * $remainingQuantity
                : $item->price * $remainingQuantity * ($item->days ?? 1);
        });

        return $originalTotal + $additionalTotal;
    }


    // public function processReturns(Request $request, $invoiceId)
    // {
    //     $isSeasonal = session('category') === 'season';

    //     $validated = $request->validate([
    //         'returns' => 'required|array',
    //         'returns.*.*.selected' => 'sometimes|required|boolean',
    //         'returns.*.*.quantity' => 'required_with:returns.*.*.selected|integer|min:1',
    //         'returns.*.*.return_date' => $isSeasonal
    //             ? 'nullable|date'
    //             : 'required_with:returns.*.*.selected|date',
    //     ]);

    //     $invoice = Invoice::findOrFail($invoiceId);

    //     DB::beginTransaction();

    //     try {
    //         foreach ($validated['returns'] as $type => $items) {
    //             foreach ($items as $id => $item) {
    //                 if (!isset($item['selected']) || !$item['selected']) {
    //                     continue;
    //                 }

    //                 $model = $type === 'original'
    //                     ? InvoiceItem::findOrFail($id)
    //                     : AdditionalItem::findOrFail($id);

    //                 $returnedQuantity = $item['quantity'];

    //                 $returnDate = $isSeasonal
    //                     ? Carbon::parse($model->rental_start_date)
    //                     : Carbon::parse($item['return_date']);

    //                 $rentalStartDate = Carbon::parse($model->rental_start_date);
    //                 $plannedEndDate = Carbon::parse($model->rental_end_date);

    //                 // Planned and actual days
    //                 $plannedDays = $rentalStartDate->diffInDays($plannedEndDate);
    //                 $daysUsed = $rentalStartDate->diffInDays($returnDate);

    //                 // Calculate unused days
    //                 $unusedDays = max(0, $plannedDays - $daysUsed);

    //                 // Daily rate
    //                 $dailyRate = $model->price;

    //                 // Calculate cost for days used
    //                 $cost = $daysUsed * $dailyRate * $returnedQuantity;

    //                 // Calculate refund for unused days
    //                 $refund = $unusedDays * $dailyRate * $returnedQuantity;

    //                 // Record return details
    //                 ReturnDetail::create([
    //                     'invoice_id' => $invoice->id,
    //                     'invoice_item_id' => $type === 'original' ? $model->id : null,
    //                     'additional_item_id' => $type === 'additional' ? $model->id : null,
    //                     'product_id' => $model->product_id,
    //                     'returned_quantity' => $returnedQuantity,
    //                     'days_used' => $daysUsed,
    //                     'cost' => $cost,   // Cost for days used
    //                     'refund' => $refund, // Refund for unused days
    //                     'return_date' => $returnDate,
    //                 ]);


    //                 // Update returned quantity
    //                 $model->returned_quantity += $returnedQuantity;
    //                 $model->save();
    //             }
    //         }

    //         DB::commit();

    //         return redirect()->route('invoices.show', $invoice->id)
    //             ->with('success', 'Returns processed successfully.');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return redirect()->back()->with('error', 'Failed to process returns: ' . $e->getMessage());
    //     }
    // }

    public function addItems(Request $request, $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.days' => 'nullable|integer|min:1',
            'products.*.rental_start_date' => 'nullable|date',
            'products.*.rental_end_date' => 'nullable|date',
            'amount_paid' => 'nullable|numeric|min:0', // Include validation for amount paid
        ]);

        DB::beginTransaction();

        try {
            foreach ($validated['products'] as $product) {
                AdditionalItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'returned_quantity' => 0,
                    'price' => $product['price'],
                    'days' => $product['days'] ?? null,
                    'total_price' => $product['price'] * $product['quantity'] * ($product['days'] ?? 1),
                    'rental_start_date' => $product['rental_start_date'] ?? null,
                    'rental_end_date' => $product['rental_end_date'] ?? null,
                    'status' => 'active',
                ]);
            }

            // Recalculate total_amount
            $invoice->total_amount = $this->calculateInvoiceTotal($invoice);

            // Update the paid amount if provided
            if (isset($validated['amount_paid'])) {
                $invoice->paid_amount += $validated['amount_paid'];
            }

            $invoice->save();

            DB::commit();

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Items added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to add items: ' . $e->getMessage());
        }
    }


    // public function addItems(Request $request, $invoiceId)
    // {
    //     $invoice = Invoice::findOrFail($invoiceId);

    //     $validated = $request->validate([
    //         'products' => 'required|array',
    //         'products.*.product_id' => 'required|exists:products,id',
    //         'products.*.quantity' => 'required|integer|min:1',
    //         'products.*.price' => 'required|numeric|min:0',
    //         'products.*.days' => 'nullable|integer|min:1',
    //         'products.*.rental_start_date' => 'nullable|date',
    //         'products.*.rental_end_date' => 'nullable|date|after_or_equal:products.*.rental_start_date',
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         foreach ($validated['products'] as $product) {
    //             AdditionalItem::create([
    //                 'invoice_id' => $invoice->id,
    //                 'product_id' => $product['product_id'],
    //                 'quantity' => $product['quantity'],
    //                 'returned_quantity' => 0,
    //                 'price' => $product['price'],
    //                 'days' => $product['days'] ?? null,
    //                 'total_price' => $product['price'] * $product['quantity'] * ($product['days'] ?? 1),
    //                 'rental_start_date' => $product['rental_start_date'] ?? null,
    //                 'rental_end_date' => $product['rental_end_date'] ?? null,
    //                 'status' => 'active',
    //             ]);
    //         }
    //         DB::commit();

    //         return redirect()->route('invoices.show', $invoice->id)
    //             ->with('success', 'Items added successfully.');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return redirect()->back()->with('error', 'Failed to add items: ' . $e->getMessage());
    //     }
    // }


    public function updatePaymentStatus(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'paid' => 'required|boolean',
        ]);

        // Update the paid status and dynamically set the status field
        $invoice->update([
            'paid' => $validated['paid'],
            'status' => $validated['paid'] ? 'active' : 'draft',
        ]);

        return redirect()->route('invoices.edit', $id)->with('success', 'Payment status updated successfully.');
    }

    public function updateInvoiceStatus(Request $request, $id)
    {
        // Fetch the invoice
        $invoice = Invoice::findOrFail($id);

        // Validate the request
        $validated = $request->validate([
            'status' => 'required|in:returned,overdue', // Ensure status is valid enum
        ]);

        // If status is 'returned', trigger processReturns
        if ($validated['status'] === 'returned') {
            // Gather all items and additional items for processing
            $returns = [
                'original' => $invoice->items->mapWithKeys(function ($item) {
                    return [$item->id => ['selected' => true, 'quantity' => $item->quantity]];
                })->toArray(),
                'additional' => $invoice->additionalItems->mapWithKeys(function ($item) {
                    return [$item->id => ['selected' => true, 'quantity' => $item->quantity]];
                })->toArray(),
            ];

            // Create a mock request with the returns data
            $mockRequest = new \Illuminate\Http\Request();
            $mockRequest->replace(['returns' => $returns]);

            // Call the processReturns method on the current controller instance
            $this->processReturns($mockRequest, $id);
        }

        // Update the invoice status
        $invoice->status = $validated['status'];
        $invoice->save();

        // Redirect back with success message
        return redirect()->route('invoices.edit', $id)->with('success', 'Invoice status updated and all items returned successfully.');
    }


    // public function updateInvoiceStatus(Request $request, $id)
    // {
    //     // Fetch the invoice
    //     $invoice = Invoice::findOrFail($id);

    //     // Validate the request
    //     $validated = $request->validate([
    //         'status' => 'required|in:returned,overdue', // Ensure status is valid enum
    //     ]);

    //     // Update the invoice status
    //     $invoice->status = $validated['status'];
    //     $invoice->save();

    //     // Update all related InvoiceItem statuses
    //     $invoice->items()->update(['status' => 'returned']);

    //     // Update all related AdditionalItem statuses
    //     $invoice->additionalItems()->update(['status' => 'returned']);

    //     // Redirect back with success message
    //     return redirect()->route('invoices.edit', $id)->with('success', 'Invoice, items, and additional items status updated successfully.');
    // }



    public function returned(Request $request)
    {
        // Retrieve the selected category from the session, default to 'daily'
        $selectedCategory = $request->query('category', session('category', 'daily'));

        // Store the selected category in the session for persistence
        session(['category' => $selectedCategory]);

        // Fetch invoices with 'returned' status and category filter
        $invoices = Invoice::with('customer')
            ->whereHas('category', function ($query) use ($selectedCategory) {
                $query->where('name', $selectedCategory);
            })
            ->where('status', 'returned') // Only returned invoices
            ->paginate(10);

        // Pass the selected category to the view
        return view('invoices.returned', compact('invoices', 'selectedCategory'));
    }


    public function overdue(Request $request)
    {
        // Retrieve the selected category from the session, default to 'daily'
        $selectedCategory = $request->query('category', session('category', 'daily'));

        // Store the selected category in the session for persistence
        session(['category' => $selectedCategory]);

        // Fetch overdue invoices with category filter
        $invoices = Invoice::with('customer')
            ->whereHas('category', function ($query) use ($selectedCategory) {
                $query->where('name', $selectedCategory);
            })
            ->where('rental_end_date', '<', now()) // Rental period has ended
            ->where('paid', false) // Unpaid invoices only
            ->paginate(10);

        // Pass the selected category to the view
        return view('invoices.overdue', compact('invoices', 'selectedCategory'));
    }

    public function addPayment(Request $request, $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $validated = $request->validate([
            'new_payment' => 'required|numeric|min:0|max:' . ($invoice->total_amount - $invoice->paid_amount),
        ]);

        // Update the paid amount
        $invoice->paid_amount += $validated['new_payment'];

        // Determine if the invoice is fully paid
        if ($invoice->paid_amount >= $invoice->total_amount) {
            $invoice->paid_amount = $invoice->total_amount; // Cap paid amount at total
        }

        $invoice->save();

        return redirect()->route('invoices.show', $invoice->id)
            ->with('success', 'Payment added successfully.');
    }

}
