<?php

namespace App\Http\Controllers\API;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\Option;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\PasswordUpdateRequest;
use Illuminate\Validation\ValidationException;



class GeneralController extends Controller
{
    /**
     * This function is used to
     * get the use profile
     */
    public function userDetails()
    {
        $user_details = new UserResource(auth()->user());
        return success_res(200, 'User Details', $user_details);
    }

    public function passwordUpdate(PasswordUpdateRequest $request)
    {
        $user = auth()->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return error_res(422, 'The current password is incorrect', ['current_password' => ['The provided password does not match our records.']]);
        }
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);
        $user->tokens()->delete();

        return success_res(200, 'Password updated successfully', [
            'user' => new UserResource($user),
            'token' => $user->createAuthToken()
        ]);
    }

    public function usersUpdate(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name'      => 'nullable|string|max:255',
                'email'     => 'required|email|max:255',
                'password'  => 'nullable|string|min:6',
                'd_o_j'     => 'nullable|date',
                'location'  => 'nullable|string|max:255',
                'emp_id'    => 'nullable|string|max:100',
                'status'    => 'nullable|in:Permanent,Probation',
                'Contract',
                'Internship'
            ]);

            if (!empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }
            $user = User::updateOrCreate(['id' => $id], $validated);

            return success_res(200, 'User updated successfully', $user);
        } catch (ValidationException $e) {
            return error_res(403, 'Validation error', $e->errors());
        } catch (\Exception $e) {
            return error_res(403, 'Something went wrong', ['error' => $e->getMessage()]);
        }
    }

    /**
     * This function is used to
     * update the order date
     */
    public function orderDateUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date', function ($attribute, $value, $fail) {
                try {
                    $submittedDate = \Carbon\Carbon::parse($value);
                    $now = \Carbon\Carbon::now();
                    if ($submittedDate->lt($now->startOfDay())) {
                        return $fail('Date must not be in the past.');
                    }
                    if ($submittedDate->isToday() && $submittedDate->lt($now)) {
                        return $fail('Time for today must be in the future.');
                    }
                } catch (\Exception $e) {
                    return $fail('Invalid date format.');
                }
            }],
        ]);

        if ($validator->fails()) {
            return error_res(403, 'Validation Error', [
                'errors' => $validator->errors()->all()
            ]);
        }
        try {
            $date_input = $request->date;
            if (!preg_match('/\d{2}:\d{2}:\d{2}$/', $date_input)) {
                $date_input .= ' 23:59:59';
            }

            $formatted_date = \Carbon\Carbon::parse($date_input)->format('Y-m-d H:i:s');
            Option::where('key', 'last_order_date')->update([
                'value' => $formatted_date,
            ]);
            return success_res(200, 'Order Date Updated Successfully', ['date' => $formatted_date]);
        } catch (\Throwable $th) {
            return error_res(403, 'Failed to update the Order Date', [
                'error' => $th->getMessage()
            ]);
        }
    }

    /**
     * This function is used to to get the
     * summary of admin and user , w.r.t
     * api being called
     */
    public function summary()
    {
        try {
            $user = auth()->user();
            if ($user->role == 'user') {
                $start_date = $user->d_o_j ? Carbon::parse($user->d_o_j) : Carbon::parse($user->created_at);
                // dump('$user', $user, '$start_date', $start_date, 'created_at', $user->created_at);
                $current_date = Carbon::now();
                $total_months = $start_date->diffInMonths($current_date);
                // dd('$total_months', $total_months);
                $eligible_months = max($total_months - 3, 0);
                if ($eligible_months < 1) {
                    return success_res(200, 'Your are on Probabtion', [
                        'total_ration_count' => 0,
                        'total_cash_count' => 0,
                        'current_month_status' => 'Probation',
                         'last_two_months' => []
                    ]);
                }
                $total_orders_placed = $user->orders()->count();
                $total_ration_count = max($eligible_months - $total_orders_placed, 0);
                $current_month_start = Carbon::now()->startOfMonth();
                $current_month_end = Carbon::now()->endOfMonth();
                $has_current_month_order = $user->orders()
                    ->whereBetween('created_at', [$current_month_start, $current_month_end])
                    ->exists();
                $current_month_status = $has_current_month_order ? 'Ration' : 'Cash';
                $last_two_months = collect();
                for ($i = 1; $i <= 2; $i++) {
                    $month = Carbon::now()->subMonths($i);
                    $month_start = $month->copy()->startOfMonth();
                    $month_end = $month->copy()->endOfMonth();
                    $monthly_order = $user->orders()
                        ->whereBetween('created_at', [$month_start, $month_end])
                        ->latest('created_at')
                        ->first();
                    $last_two_months->push([
                        'sr_no' => $i,
                        'month' => $month->format('F Y'),
                        'type' => $monthly_order ? 'Ration' : 'Cash',
                        'amount' => $monthly_order ? $monthly_order->grand_total : 7000,
                    ]);
                }
                return success_res(200, 'User Summary', [
                    'total_ration_count' => $total_orders_placed,
                    'total_cash_count' => $total_ration_count,
                    'current_month_status' => $current_month_status,
                    'last_two_months' => $last_two_months
                ]);
            } else {
                $current_month_start = Carbon::now()->startOfMonth();
                $current_month_end = Carbon::now()->endOfMonth();
                $total_users = User::where('is_admin', 3)->count();
                $users_with_orders = Order::whereBetween('created_at', [$current_month_start, $current_month_end])
                    ->pluck('user_id')
                    ->unique();
                $total_ration_employees = $users_with_orders->count();
                $total_cash_employees = User::where('is_admin', 3)
                    ->whereNotIn('id', $users_with_orders)
                    ->count();
                $recent_orders = Order::with('user:id,name')
                    ->latest()
                    ->take(10)
                    ->get(['id', 'user_id', 'grand_total', 'discount'])
                    ->map(function ($order) {
                        return [
                            'order_no' => $order->id,
                            'user_name' => $order->user->name ?? 'N/A',
                            'grand_total' => $order->grand_total,
                            'discount' => $order->discount,
                        ];
                    });
                $top_users = Order::select('user_id', DB::raw('SUM(grand_total) as total_spent'))
                    ->groupBy('user_id')
                    ->orderByDesc('total_spent')
                    ->take(10)
                    ->with('user:id,name')
                    ->get()
                    ->map(function ($order) {
                        return [
                            'employee_name' => $order->user->name ?? 'N/A',
                            'grand_total' => $order->total_spent
                        ];
                    });
                $month_wise_rations = collect();
                for ($i = 0; $i < 10; $i++) {
                    $month = Carbon::now()->subMonths($i);
                    $month_start = $month->copy()->startOfMonth();
                    $month_end = $month->copy()->endOfMonth();
                    $highest_order = Order::whereBetween('created_at', [$month_start, $month_end])
                        ->orderByDesc('grand_total')
                        ->first();
                    $month_wise_rations->push([
                        'month' => $month->format('F Y'),
                        'grand_total' => $highest_order ? $highest_order->grand_total : 0,
                    ]);
                }
                return success_res(200, 'Admin Summary', [
                    'total_users' => $total_users,
                    'employee_ration_this_month' => $total_ration_employees,
                    'employee_cash_this_month' => $total_cash_employees,
                    'recent_orders' => $recent_orders,
                    'top_users' => $top_users,
                    'month_wise_ration' => $month_wise_rations
                ]);
            }
        } catch (\Exception $e) {
            return error_res(403, 'Error Occurred', []);
        }
    }
}
