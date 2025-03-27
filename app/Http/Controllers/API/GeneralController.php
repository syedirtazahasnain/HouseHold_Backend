<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\PasswordUpdateRequest;



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
}
