<?php

namespace App\Exports;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersExport implements FromCollection, WithHeadings
{
    protected $start_date;
    protected $end_date;
    protected $active_products;

    public function __construct($start_date, $end_date)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;

        // Fetch active products (status = 1)
        $this->active_products = Product::where('status', 1)
            ->orderBy('id')
            ->pluck('name', 'id'); // [product_id => name]
    }

    public function headings(): array
    {
        return array_merge([
            'Emp ID',
            // 'Prefix',
            'Name',
            'Date Of Joining',
            'Status',
            'Probation Period Completed',
            'Office Location',
            'Total Purchase',
            'Employer Contribution',
            'Employee Contribution',
        ], $this->active_products->values()->toArray());
    }

    public function collection(): Collection
    {
        $users = User::whereHas('orders', function ($query) {
            $query->whereBetween('created_at', [$this->start_date, $this->end_date]);
        })->get();

        $data = [];

        foreach ($users as $user) {
            // Total purchase & contributions
            $order_summary = $user->orders()
                ->whereBetween('created_at', [$this->start_date, $this->end_date])
                ->select(
                    DB::raw('SUM(grand_total) as total_purchase'),
                    DB::raw('SUM(discount) as employer_contribution')
                )
                ->first();

            $total = $order_summary->total_purchase ?? 0;
            $discount = $order_summary->employer_contribution ?? 0;
            $employee_contribution = $total - $discount;

            // Product quantities
            $product_quantities = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.user_id', $user->id)
                ->whereBetween('orders.created_at', [$this->start_date, $this->end_date])
                ->whereIn('order_items.product_id', $this->active_products->keys())
                ->groupBy('order_items.product_id')
                ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as qty'))
                ->pluck('qty', 'product_id'); // [product_id => qty]

            // Build row
            $row = [
                $user->emp_id,
                // '', // Prefix (if available in future)
                $user->name,
                $user->d_o_j,
                $user->status,
                $user->status == 'Permanent' ? 'Yes' : 'No',
                $user->location,
                $total,
                $discount,
                $employee_contribution,
            ];

            // Append quantities for each product (0 if none)
            foreach ($this->active_products->keys() as $product_id) {
                $row[] = $product_quantities[$product_id] ?? 0;
            }

            $data[] = $row;
        }

        return collect($data);
    }
}
