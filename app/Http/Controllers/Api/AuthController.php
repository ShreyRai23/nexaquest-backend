<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ChildProfile;
use App\Models\ParentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:child,parent',
            'hero_name' => 'nullable|string|max:50',
            'age'       => 'nullable|integer|min:5|max:20',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $user = User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => $request->password,
            'role'         => $request->role,
            'avatar_emoji' => $request->avatar_emoji ?? '🦊',
        ]);

        if ($request->role === 'child') {
            ChildProfile::create([
                'user_id'   => $user->id,
                'hero_name' => $request->hero_name ?? $request->name,
                'age'       => $request->age,
            ]);
        } else {
            ParentProfile::create(['user_id' => $user->id]);
        }

        $token = JWTAuth::fromUser($user);
        return response()->json([
            'message' => 'Registration successful! Welcome to MindBloom! 🌟',
            'token'   => $token,
            'user'    => $this->userResponse($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $v = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        try {
            $credentials = ['email' => $request->email, 'password' => $request->password];
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['message' => 'Invalid email or password. Try again! 🔐'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['message' => 'Could not create token. Please try again.'], 500);
        }

        // Use JWTAuth::user() directly — more reliable than auth('api')->user() after attempt()
        $user = JWTAuth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication failed. Please try again.'], 500);
        }

        return response()->json([
            'message' => 'Welcome back, hero! 🚀',
            'token'   => $token,
            'user'    => $this->userResponse($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) return response()->json(['message' => 'User not found'], 404);
        return response()->json(['user' => $this->userResponse($user)]);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Logged out successfully!']);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Failed to logout'], 500);
        }
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json(['token' => $newToken]);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token refresh failed'], 401);
        }
    }

    private function userResponse(User $user): array
    {
        $profile = null;
        if ($user->role === 'child') {
            $profile = $user->childProfile;
        } elseif ($user->role === 'parent') {
            $profile = $user->parentProfile;
        }

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'role'              => $user->role,
            'avatar_emoji'      => $user->avatar_emoji,
            'profile_image_url' => $user->profile_image_url,
            'profile'           => $profile,
        ];
    }
}
