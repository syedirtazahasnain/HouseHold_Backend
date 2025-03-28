<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $products = Product::select('id', 'name', 'detail', 'price', 'image')
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
        $admin = auth()->user()->role;
        if($admin == "user"){
            return error_res(403, 'Unauthorize access',[]);
        }
        // dd('$admin',$admin);
        $validated_data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'required|string',
            'price' => 'required|numeric|min:0',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image_name = \Str::random(20) . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('public/products', $image_name);
            $validated_data['image'] = 'products/' . $image_name;
        }
        $identifier = ['id' => $request->input('id')]; // or ['name' => $validated_data['name']]
        $product = Product::updateOrCreate(
            $identifier,
            $validated_data
        );
        $was_recently_created = $product->was_recently_created;
        return success_res(200, $was_recently_created ? 'Product created successfully' : 'Product updated successfully',$product);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
