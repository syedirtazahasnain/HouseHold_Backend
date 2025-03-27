<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return error_res(422, 'Validation errors', $validator->errors() , []);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        return success_res(200, 'User registered successfully', [
            'user' => $user,
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $request->email)->select('id','name','email','password','is_admin')->first();
        if (!$user) {
            return error_res(401, 'Email not found.',[]);
        }
        if(!Hash::check($request->password, $user->password)){
            return error_res(401, 'The provided credentials are incorrect.',[]);
        }
        $token = $user->createToken('authToken', [$user->role])->plainTextToken;
        return success_res(200, 'Login successful', ['user' => $user, 'role' => $user->role, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return success_res(200 , 'Logged out successfully' );
    }
}
