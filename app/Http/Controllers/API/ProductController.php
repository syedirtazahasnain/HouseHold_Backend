<?php

namespace App\Http\Controllers\API;

use Str;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\OrderUpdateService;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $search = $request->query('search');
        $is_admin_param = str_contains($request->path(), 'admin');
        $products = Product::select('id', 'name', 'detail', 'price', 'image', 'status', 'type', 'measure')
            ->when(!$is_admin_param, function ($query) {
                return $query->where('status', 1);
            })
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(50);

        return success_res(200, 'Products fetched successfully', $products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $admin = auth()->user()->role;
            if ($admin == "user") {
                return error_res(403, 'Unauthorize access', []);
            }
            $validated_data = $request->validate([
                'name' => 'required|string|max:255',
                'detail' => 'required|string',
                'price' => 'required|numeric|min:0',
                'measure' => 'required|string|max:100',
                'type' => 'required|string|in:' . implode(',', Product::TYPES),
                'brand' => 'nullable|string|max:255',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'status' => 'nullable',
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $image_name = \Str::random(20) . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('public/products', $image_name);
                $validated_data['image'] = 'products/' . $image_name;
            }
            $identifier = ['id' => $request->input('id')];
            $product = Product::updateOrCreate(
                $identifier,
                $validated_data
            );

            if (isset($request->order_update) && $request->order_update == 1) {
                $start_date = \Carbon\Carbon::now()->startOfMonth()->startOfDay();
                $end_date = \Carbon\Carbon::now()->endOfMonth()->endOfDay();
                $service = new OrderUpdateService();
                try {
                    $updated_orders = $service->bulkUpdateOrders($start_date, $end_date);
                    return success_res(200, 'Orders for current month updated successfully', $product);
                } catch (\Exception $e) {
                    return error_res(403, 'Validation failed', [
                        'message' => $e->getMessage(),
                        'error_details' => $e->getTraceAsString()
                    ]);
                }
            }

            $was_recently_created = $product->was_recently_created;
            return success_res(200, $was_recently_created ? 'Product created successfully' : 'Product updated successfully', $product);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return error_res(403, 'Validation failed', $e->errors());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::select('id', 'name', 'detail', 'price', 'image', 'type', 'brand', 'measure')
            ->findOrFail($id);
        return success_res(200, 'Product fetched successfully', $product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
