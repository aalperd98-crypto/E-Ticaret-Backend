<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\UserResource;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'cart' => $this->claimCart($request->input('cart_token'), $user),
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Verdiğiniz bilgiler kayıtlarımızla eşleşmiyor.'],
            ]);
        }

        $user = Auth::getLastAttempted();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'cart' => $this->claimCart($request->input('cart_token'), $user),
        ]);
    }

    /**
     * Ziyaretçiyken oluşturulan sepeti hesaba bağlar. Sepet kimliği
     * gönderilmediyse ve kullanıcının sepeti de yoksa null döner.
     */
    private function claimCart(?string $cartToken, User $user): ?CartResource
    {
        $cart = Cart::claim($cartToken, $user);

        return $cart ? new CartResource($cart->load('items.product')) : null;
    }
}
