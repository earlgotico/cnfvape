<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    public function index()
    {

        $carts = Cart::with('product')->where('user_id', Auth::id())->get();

        return view('user.cart.index', compact('carts'));
    }



    public function addToCart(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // Retrieve the product
        $product = Product::findOrFail($request->product_id);

        // Determine the price to use (sale price if on sale, otherwise regular price)
        $price = $product->on_sale ? $product->sale_price : $product->price;

        // Check if stock is available
        if ($product->stock > 0) {
            // Check if the product already exists in the cart
            $cartItem = Cart::where('user_id', Auth::id())
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                // If the product is already in the cart, increment the quantity
                $cartItem->increment('quantity');
            } else {
                // Directly insert a new entry into the carts table with the discounted price
                DB::table('carts')->insert([
                    'user_id' => Auth::id(), // Assuming user is authenticated
                    'product_id' => $product->id,
                    'quantity' => 1, // Default quantity is 1
                    'price' => $price, // Use sale price if on sale
                    'image' => $product->image,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Set a flash message
            session()->flash('message', 'Product added to cart successfully.');

            return redirect()->back(); // Redirect back to the previous page
        } else {
            return response()->json(['success' => false, 'message' => 'Product is out of stock.']);
        }
    }



    public function getCartCount()
    {
        $cartCount = Session::get('cart_count', 0);
        return response()->json(['count' => $cartCount]);
    }

    public function updateQuantity(Request $request)
    {
        $cart = Cart::findOrFail($request->product_id);
        $product = Product::findOrFail($cart->product_id);

        // Calculate stock change based on new quantity
        $oldQuantity = $cart->quantity;
        $newQuantity = $request->quantity;
        $quantityDifference = $newQuantity - $oldQuantity;

        if ($product->stock >= $quantityDifference) {
            // Update the cart quantity
            $cart->quantity = $newQuantity;
            $cart->save();

            // Adjust the product stock
            $product->decrement('stock', $quantityDifference);

            return response()->json(['success' => true, 'message' => 'Cart updated successfully.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Not enough stock available.']);
        }
    }

    public function removeItem(Request $request)
    {
        $cart = Cart::findOrFail($request->product_id);
        $product = Product::findOrFail($cart->product_id);

        // Restock the product
        $product->increment('stock', $cart->quantity);

        // Remove the cart item
        $cart->delete();

        return response()->json(['success' => true, 'message' => 'Item removed and stock updated.']);
    }

    public function checkout()
    {
        // Fetch the cart data for the current user
        $carts = Cart::where('user_id', Auth::id())->get();

        if ($carts->isEmpty()) {
            return redirect()->route('user.cart.index')->with('error', 'Your cart is empty.');
        }

        // Calculate the total price
        $totalPrice = $carts->sum(function ($cart) {
            return $cart->product->price * $cart->quantity;
        });

        // Fetch the authenticated user
        $user = Auth::user();

        // Flash message if address or phone number is empty
        if (empty($user->address) || empty($user->phone_number)) {
            session()->flash('warning', 'Please update your address and phone number in your profile.');
        }

        return view('user.cart.checkout', compact('carts', 'totalPrice', 'user'));
    }
}