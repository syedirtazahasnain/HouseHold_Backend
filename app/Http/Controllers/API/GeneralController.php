<?php

namespace App\Http\Controllers\API;

use App\Models\User;
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
}
