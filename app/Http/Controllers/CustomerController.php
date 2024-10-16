<?php

namespace App\Http\Controllers;

use App\DataTables\CustomerDataTable;
use App\Models\Customer;
use App\Models\Invoice;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use FileUploadTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(CustomerDataTable $dataTable)
    {
        //return $dataTable->render('customers.index');
        $customers = Customer::all();
        return view('customers.index', compact('customers'));
    }

    public function getCustomer($id)
    {
        $customer = Customer::find($id);

        // Return customer data as JSON
        return response()->json($customer);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('customers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'name' => 'required|string|min:3',
            'email' => 'required|email',
            'phone' => 'required|numeric',
            'address' => 'required|string',
            'deposit_card' => 'required|file|mimes:jpeg,png,jpg,gif', // Validate image upload
        ]);

        // Handle file upload (if provided)
        $filePath = $this->uploadImage($request, 'deposit_card', null, '/uploads/customers');

        // Create a new customer
        $customer = new Customer();
        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->phone = $request->phone;
        $customer->address = $request->address;
        $customer->deposit_card = $filePath; // Save the file path if uploaded
        $customer->save();

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $customer = Customer::find($id);
        return view('customers.show', compact('customer'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, $id)
    {
        // Validate incoming request data
        $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'required|min:10|max:15',
            'address' => 'required|string',
            'deposit_card' => 'nullable|image|max:2048', // Optional image file upload
        ]);

        $customer = Customer::findOrFail($id);

        // Handle image file upload, replace old file if a new one is uploaded
        $filePath = $this->uploadImage($request, 'deposit_card', $customer->file, '/uploads/customers');

        // Update customer details
        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->phone = $request->phone;
        $customer->address = $request->address;
        $customer->deposit_card = $filePath ?? $customer->deposit_card; // Keep old file if no new one uploaded
        $customer->save();

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $customer = Customer::findOrFail($id);

            // Check if the customer has a deposit card before trying to remove the image
            if (!empty($customer->deposit_card)) {
                $this->removeImage($customer->deposit_card);
            }

            // Delete the customer
            $customer->delete();


            return response()->json(['status' => 'success', 'message' => 'Deleted Successfully!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong!']);
        }
    }

    public function rentalDetails(Request $request, $id)
    {
        // Find the customer by ID
        $customer = Customer::findOrFail($id);

        // Query invoices related to this customer
        $invoicesQuery = Invoice::where('customer_id', $id);

        // Apply date filtering if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $invoicesQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Get the filtered invoices
        $invoices = $invoicesQuery->with('items.product')->get();

        return view('customers.rental_details', compact('customer', 'invoices'));
    }
}
