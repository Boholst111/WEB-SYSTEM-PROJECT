import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { cartApi, CartSummary } from '../services/cartApi';
import { useAppSelector } from '../store';

const CartPage: React.FC = () => {
  const navigate = useNavigate();
  const { isAuthenticated } = useAppSelector(state => state.auth);
  const [cartData, setCartData] = useState<CartSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updatingItems, setUpdatingItems] = useState<Set<number>>(new Set());

  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
      return;
    }
    loadCart();
  }, [isAuthenticated, navigate]);

  const loadCart = async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await cartApi.getCart();
      if (response.success) {
        setCartData(response.data);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to load cart');
    } finally {
      setIsLoading(false);
    }
  };

  const handleUpdateQuantity = async (itemId: number, newQuantity: number) => {
    if (newQuantity < 1) return;

    setUpdatingItems(prev => new Set(prev).add(itemId));
    try {
      const response = await cartApi.updateItem(itemId, newQuantity);
      if (response.success) {
        await loadCart();
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to update quantity');
    } finally {
      setUpdatingItems(prev => {
        const next = new Set(prev);
        next.delete(itemId);
        return next;
      });
    }
  };

  const handleRemoveItem = async (itemId: number) => {
    if (!confirm('Remove this item from cart?')) return;

    setUpdatingItems(prev => new Set(prev).add(itemId));
    try {
      const response = await cartApi.removeItem(itemId);
      if (response.success) {
        await loadCart();
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to remove item');
    } finally {
      setUpdatingItems(prev => {
        const next = new Set(prev);
        next.delete(itemId);
        return next;
      });
    }
  };

  const handleClearCart = async () => {
    if (!confirm('Clear all items from cart?')) return;

    try {
      setIsLoading(true);
      const response = await cartApi.clearCart();
      if (response.success) {
        await loadCart();
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to clear cart');
    } finally {
      setIsLoading(false);
    }
  };

  const handleCheckout = () => {
    navigate('/checkout');
  };

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600">Loading cart...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <p className="text-red-800">{error}</p>
          <button
            onClick={loadCart}
            className="mt-2 text-red-600 hover:text-red-800 font-medium"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!cartData || cartData.summary.items_count === 0) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="text-center">
          <svg
            className="mx-auto h-24 w-24 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
            />
          </svg>
          <h2 className="mt-4 text-2xl font-bold text-gray-900">Your cart is empty</h2>
          <p className="mt-2 text-gray-600">Start shopping to add items to your cart</p>
          <button
            onClick={() => navigate('/products')}
            className="mt-6 bg-blue-600 text-white px-6 py-3 rounded-md font-medium hover:bg-blue-700"
          >
            Browse Products
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Shopping Cart</h1>
        {cartData.summary.items_count > 0 && (
          <button
            onClick={handleClearCart}
            className="text-red-600 hover:text-red-800 font-medium"
          >
            Clear Cart
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Cart Items */}
        <div className="lg:col-span-2 space-y-4">
          {cartData.items.map((item) => (
            <div
              key={item.id}
              className="bg-white rounded-lg shadow-md p-4 flex gap-4"
            >
              {/* Product Image */}
              <div className="flex-shrink-0">
                <img
                  src={item.product.main_image || '/placeholder.png'}
                  alt={item.product.name}
                  className="w-24 h-24 object-cover rounded-md"
                />
              </div>

              {/* Product Details */}
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-gray-900">
                  {item.product.name}
                </h3>
                <p className="text-sm text-gray-600">
                  SKU: {item.product.sku}
                </p>
                {item.product.brand && (
                  <p className="text-sm text-gray-600">
                    Brand: {item.product.brand}
                  </p>
                )}
                <p className="mt-2 text-lg font-bold text-gray-900">
                  ₱{item.price.toFixed(2)}
                </p>

                {/* Stock Status */}
                {!item.product.is_available && (
                  <p className="mt-1 text-sm text-red-600">Out of stock</p>
                )}
                {item.product.is_available && item.product.stock_quantity < 5 && (
                  <p className="mt-1 text-sm text-orange-600">
                    Only {item.product.stock_quantity} left in stock
                  </p>
                )}
              </div>

              {/* Quantity Controls */}
              <div className="flex flex-col items-end justify-between">
                <button
                  onClick={() => handleRemoveItem(item.id)}
                  disabled={updatingItems.has(item.id)}
                  className="text-red-600 hover:text-red-800 disabled:opacity-50"
                >
                  <svg
                    className="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                </button>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handleUpdateQuantity(item.id, item.quantity - 1)}
                    disabled={item.quantity <= 1 || updatingItems.has(item.id)}
                    className="w-8 h-8 rounded-md border border-gray-300 flex items-center justify-center hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    -
                  </button>
                  <span className="w-12 text-center font-medium">
                    {item.quantity}
                  </span>
                  <button
                    onClick={() => handleUpdateQuantity(item.id, item.quantity + 1)}
                    disabled={
                      updatingItems.has(item.id) ||
                      item.quantity >= item.product.stock_quantity
                    }
                    className="w-8 h-8 rounded-md border border-gray-300 flex items-center justify-center hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    +
                  </button>
                </div>

                <p className="text-lg font-bold text-gray-900">
                  {item.formatted_total}
                </p>
              </div>
            </div>
          ))}
        </div>

        {/* Order Summary */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-md p-6 sticky top-4">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">
              Order Summary
            </h2>

            <div className="space-y-3 mb-4">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">
                  Items ({cartData.summary.total_quantity})
                </span>
                <span className="text-gray-900">
                  {cartData.summary.formatted_subtotal}
                </span>
              </div>
            </div>

            {/* Loyalty Credits Info */}
            {cartData.loyalty.available_credits > 0 && (
              <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                <h4 className="text-sm font-medium text-blue-900 mb-2">
                  Loyalty Credits Available
                </h4>
                <div className="text-xs text-blue-700 space-y-1">
                  <div className="flex justify-between">
                    <span>Available:</span>
                    <span>{cartData.loyalty.formatted_available}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Max Usable:</span>
                    <span>{cartData.loyalty.formatted_max_usable}</span>
                  </div>
                </div>
                <p className="mt-2 text-xs text-blue-600">
                  Apply credits during checkout
                </p>
              </div>
            )}

            <button
              onClick={handleCheckout}
              className="w-full mt-6 bg-blue-600 text-white py-3 px-4 rounded-md font-medium hover:bg-blue-700 transition-colors"
            >
              Proceed to Checkout
            </button>

            <button
              onClick={() => navigate('/products')}
              className="w-full mt-3 bg-white text-blue-600 py-3 px-4 rounded-md font-medium border border-blue-600 hover:bg-blue-50 transition-colors"
            >
              Continue Shopping
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CartPage;