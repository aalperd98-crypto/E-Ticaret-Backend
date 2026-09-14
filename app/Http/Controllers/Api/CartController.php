<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sepet uçları hem oturum açmış kullanıcıya hem ziyaretçiye açıktır.
 *
 * Ziyaretçi ilk eklemede bir sepet kimliği (token) alır ve sonraki isteklerde
 * bunu `X-Cart-Token` başlığıyla ya da `cart_token` alanıyla geri gönderir.
 */
class CartController extends Controller
{
    public function show(Request $request)
    {
        $cart = $this->resolveCart($request);

        return $cart ? $this->respondWithCart($cart) : $this->respondWithEmptyCart();
    }

    public function store(StoreCartItemRequest $request)
    {
        $product = Product::findOrFail($request->integer('product_id'));

        $this->assertProductIsPurchasable($product);

        $cart = $this->resolveCart($request, create: true);
        $item = $cart->items()->where('product_id', $product->getKey())->first();
        $quantity = ($item?->quantity ?? 0) + $request->integer('quantity', 1);

        $this->assertQuantityIsAvailable($product, $quantity);

        $cart->items()->updateOrCreate(
            ['product_id' => $product->getKey()],
            ['quantity' => $quantity],
        );

        $cart->touch();

        return $this->respondWithCart($cart, 201);
    }

    public function update(UpdateCartItemRequest $request, int $item)
    {
        $cart = $this->resolveCart($request);
        $cartItem = $this->findItem($cart, $item);
        $quantity = $request->integer('quantity');

        $this->assertProductIsPurchasable($cartItem->product);
        $this->assertQuantityIsAvailable($cartItem->product, $quantity);

        $cartItem->update(['quantity' => $quantity]);
        $cart->touch();

        return $this->respondWithCart($cart);
    }

    public function destroy(Request $request, int $item)
    {
        $cart = $this->resolveCart($request);

        $this->findItem($cart, $item)->delete();
        $cart->touch();

        return $this->respondWithCart($cart);
    }

    public function clear(Request $request)
    {
        $cart = $this->resolveCart($request);

        if (! $cart) {
            return $this->respondWithEmptyCart();
        }

        $cart->items()->delete();
        $cart->touch();

        return $this->respondWithCart($cart);
    }

    /**
     * Oturum açmış kullanıcının sepeti hesabına, ziyaretçininki gönderdiği
     * kimliğe göre bulunur. Geçersiz bir kimlikle ekleme yapılırsa yeni
     * sepet açılır ve yanıtla birlikte yeni kimlik döner.
     */
    private function resolveCart(Request $request, bool $create = false): ?Cart
    {
        if ($user = $request->user('sanctum')) {
            return $create
                ? Cart::firstOrCreate(['user_id' => $user->getKey()])
                : Cart::query()->where('user_id', $user->getKey())->first();
        }

        $cart = Cart::findByToken($this->cartToken($request));

        return $cart ?? ($create ? Cart::create() : null);
    }

    private function cartToken(Request $request): ?string
    {
        return $request->header('X-Cart-Token') ?: $request->input('cart_token');
    }

    private function findItem(?Cart $cart, int $itemId): CartItem
    {
        $item = $cart?->items()->with('product')->find($itemId);

        if (! $item) {
            throw new NotFoundHttpException('Sepet satırı bulunamadı.');
        }

        return $item;
    }

    private function assertProductIsPurchasable(Product $product): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['Bu ürün şu anda satışta değil.'],
            ]);
        }
    }

    private function assertQuantityIsAvailable(Product $product, int $quantity): void
    {
        if ($quantity > Cart::MAX_QUANTITY_PER_ITEM) {
            throw ValidationException::withMessages([
                'quantity' => ['Bir üründen sepette en fazla '.Cart::MAX_QUANTITY_PER_ITEM.' adet bulunabilir.'],
            ]);
        }

        if ($product->stock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => [$product->stock === 0
                    ? 'Bu ürün stokta yok.'
                    : 'Bu üründen stokta yalnızca '.$product->stock.' adet var.'],
            ]);
        }
    }

    private function respondWithCart(Cart $cart, int $status = 200): JsonResponse
    {
        return (new CartResource($cart->load('items.product')))
            ->response()
            ->setStatusCode($status);
    }

    private function respondWithEmptyCart(): JsonResponse
    {
        return response()->json(['data' => CartResource::empty()]);
    }
}
