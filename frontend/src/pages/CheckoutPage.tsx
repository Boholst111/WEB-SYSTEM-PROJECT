import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import CheckoutProgress from '../components/CheckoutProgress';
import AddressSelector from '../components/AddressSelector';
import AddressForm from '../components/AddressForm';
import ShippingSelector from '../components/ShippingSelector';
import CreditsRedemption from '../components/CreditsRedemption';
import PaymentMethodSelector from '../components/PaymentMethodSelector';
import { checkoutApi, UserAddress, CreateOrderRequest } from '../services/checkoutApi';
import { cartApi, ShippingOption } from '../services/cartApi';
import { useAppSelector } from '../store';

const CheckoutPage: React.FC = () => {
  const navigate = useNavigate();
  const { isAuthenticated } = useAppSelector(state => state.auth);

  // State
  const [currentStep, setCurrentStep] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Address state
  const [addresses, setAddresses] = useState<UserAddress[]>([]);
  const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);
  const [showAddressForm, setShowAddressForm] = useState(false);
  const [editingAddress, setEditingAddress] = useState<UserAddress | null>(null);

  // Cart and shipping state
  const [cartItems, setCartItems] = useState<any[]>([]);
  const [cartSummary, setCartSummary] = useState<any>(null);
  const [shippingOptions, setShippingOptions] = useState<ShippingOption[]>([]);
  const [selectedShippingOption, setSelectedShippingOption] = useState<string | null>(null);

  // Payment state
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState<string>('');
  const [creditsToUse, setCreditsToUse] = useState<number>(0);
  const [creditsRedemption, setCreditsRedemption] = useState<any>(null);

  // Totals
  const [totals, setTotals] = useState<any>(null);
  const [isCreatingOrder, setIsCreatingOrder] = useState(false);

  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
      return;
    }
    initializeCheckout();
  }, [isAuthenticated, navigate]);

  const initializeCheckout = async () => {
    try {
      setIsLoading(true);
      setError(null);

      // Load cart and addresses
      const [cartResponse, addressesResponse] = await Promise.all([
        cartApi.getCart(),
        checkoutApi.getAddresses(),
      ]);

      if (cartResponse.success) {
        const cartData = cartResponse.data;
        setCartItems(cartData.items);
        setCartSummary(cartData.summary);
        setShippingOptions(cartData.shipping_options || []);

        // Auto-select first shipping option
        if (cartData.shipping_options && cartData.shipping_options.length > 0) {
          setSelectedShippingOption(cartData.shipping_options[0].id);
        }
      }

      if (addressesResponse.success) {
        setAddresses(addressesResponse.data);
        // Auto-select default address
        const defaultAddress = addressesResponse.data.find((addr: UserAddress) => addr.is_default);
        if (defaultAddress) {
          setSelectedAddressId(defaultAddress.id);
        }
      }

      // Check if cart is empty
      if (!cartResponse.data.items || cartResponse.data.items.length === 0) {
        navigate('/cart');
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to initialize checkout');
    } finally {
      setIsLoading(false);
    }
  };

  const handleAddressSubmit = async (addressData: Partial<UserAddress>) => {
    try {
      if (editingAddress) {
        const response = await checkoutApi.updateAddress(editingAddress.id, addressData);
        if (response.success) {
          setAddresses(prev =>
            prev.map(addr => (addr.id === editingAddress.id ? response.data : addr))
          );
        }
      } else {
        const response = await checkoutApi.createAddress(addressData);
        if (response.success) {
          setAddresses(prev => [...prev, response.data]);
          setSelectedAddressId(response.data.id);
        }
      }
      setShowAddressForm(false);
      setEditingAddress(null);
    } catch (err: any) {
      throw err;
    }
  };

  const handleDeleteAddress = async (addressId: number) => {
    try {
      const response = await checkoutApi.deleteAddress(addressId);
      if (response.success) {
        setAddresses(prev => prev.filter(addr => addr.id !== addressId));
        if (selectedAddressId === addressId) {
          setSelectedAddressId(null);
        }
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to delete address');
    }
  };

  const handleCreditsRedemptionChange = (redemption: any) => {
    setCreditsRedemption(redemption);
    if (redemption) {
      setCreditsToUse(redemption.creditsUsed);
    } else {
      setCreditsToUse(0);
    }
  };

  const canProceedToPayment = () => {
    return selectedAddressId && selectedShippingOption;
  };

  const canProceedToReview = () => {
    return selectedPaymentMethod;
  };

  const handleNextStep = () => {
    if (currentStep === 1 && canProceedToPayment()) {
      setCurrentStep(2);
    } else if (currentStep === 2 && canProceedToReview()) {
      setCurrentStep(3);
    }
  };

  const handlePlaceOrder = async () => {
    if (!selectedAddressId || !selectedPaymentMethod || !selectedShippingOption) {
      alert('Please complete all required fields');
      return;
    }

    setIsCreatingOrder(true);
    try {
      const orderData: CreateOrderRequest = {
        shipping_address_id: selectedAddressId,
        payment_method: selectedPaymentMethod,
        shipping_option: selectedShippingOption,
        credits_to_use: creditsToUse,
      };

      const response = await checkoutApi.createOrder(orderData);
      if (response.success) {
        const { order, payment_url } = response.data;
        
        // Navigate to payment page or success page
        if (payment_url) {
          window.location.href = payment_url;
        } else {
          navigate(`/orders/${order.id}`);
        }
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to create order');
    } finally {
      setIsCreatingOrder(false);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const calculateTotal = () => {
    if (!cartSummary) return 0;
    
    const subtotal = cartSummary.subtotal || 0;
    const creditsDiscount = creditsRedemption?.discountAmount || 0;
    const shippingFee = shippingOptions.find(opt => opt.id === selectedShippingOption)?.cost || 0;
    
    return subtotal - creditsDiscount + shippingFee;
  };

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600">Loading checkout...</p>
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
            onClick={initializeCheckout}
            className="mt-2 text-red-600 hover:text-red-800 font-medium"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

      <CheckoutProgress currentStep={currentStep} />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Main Checkout Form */}
        <div className="lg:col-span-2 space-y-6">
          {/* Step 1: Shipping */}
          {currentStep === 1 && (
            <>
              <div className="bg-white rounded-lg shadow-md p-6">
                {showAddressForm ? (
                  <AddressForm
                    address={editingAddress}
                    onSubmit={handleAddressSubmit}
                    onCancel={() => {
                      setShowAddressForm(false);
                      setEditingAddress(null);
                    }}
                  />
                ) : (
                  <AddressSelector
                    addresses={addresses}
                    selectedAddressId={selectedAddressId}
                    onSelectAddress={setSelectedAddressId}
                    onAddNew={() => {
                      setEditingAddress(null);
                      setShowAddressForm(true);
                    }}
                    onEdit={(address) => {
                      setEditingAddress(address);
                      setShowAddressForm(true);
                    }}
                    onDelete={handleDeleteAddress}
                  />
                )}
              </div>

              {!showAddressForm && (
                <div className="bg-white rounded-lg shadow-md p-6">
                  <ShippingSelector
                    options={shippingOptions}
                    selectedOption={selectedShippingOption}
                    onSelectOption={setSelectedShippingOption}
                  />
                </div>
              )}

              {!showAddressForm && (
                <button
                  onClick={handleNextStep}
                  disabled={!canProceedToPayment()}
                  className="w-full bg-blue-600 text-white py-3 px-4 rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Continue to Payment
                </button>
              )}
            </>
          )}

          {/* Step 2: Payment */}
          {currentStep === 2 && (
            <>
              <div className="bg-white rounded-lg shadow-md p-6">
                <PaymentMethodSelector
                  selectedMethod={selectedPaymentMethod}
                  onMethodSelect={setSelectedPaymentMethod}
                  amount={calculateTotal()}
                  disabled={false}
                />
              </div>

              <div className="bg-white rounded-lg shadow-md p-6">
                <CreditsRedemption
                  orderTotal={cartSummary?.subtotal || 0}
                  onRedemptionChange={handleCreditsRedemptionChange}
                />
              </div>

              <div className="flex gap-3">
                <button
                  onClick={() => setCurrentStep(1)}
                  className="flex-1 bg-white text-gray-700 py-3 px-4 rounded-md font-medium border border-gray-300 hover:bg-gray-50"
                >
                  Back
                </button>
                <button
                  onClick={handleNextStep}
                  disabled={!canProceedToReview()}
                  className="flex-1 bg-blue-600 text-white py-3 px-4 rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Review Order
                </button>
              </div>
            </>
          )}

          {/* Step 3: Review */}
          {currentStep === 3 && (
            <>
              <div className="bg-white rounded-lg shadow-md p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Order Review</h3>
                
                {/* Shipping Address */}
                <div className="mb-6">
                  <h4 className="font-medium text-gray-900 mb-2">Shipping Address</h4>
                  {addresses.find(addr => addr.id === selectedAddressId) && (
                    <div className="text-sm text-gray-600">
                      {(() => {
                        const addr = addresses.find(a => a.id === selectedAddressId)!;
                        return (
                          <>
                            <p>{addr.first_name} {addr.last_name}</p>
                            <p>{addr.address_line_1}</p>
                            {addr.address_line_2 && <p>{addr.address_line_2}</p>}
                            <p>{addr.city}, {addr.province} {addr.postal_code}</p>
                            <p>{addr.phone}</p>
                          </>
                        );
                      })()}
                    </div>
                  )}
                </div>

                {/* Shipping Method */}
                <div className="mb-6">
                  <h4 className="font-medium text-gray-900 mb-2">Shipping Method</h4>
                  {shippingOptions.find(opt => opt.id === selectedShippingOption) && (
                    <div className="text-sm text-gray-600">
                      {(() => {
                        const opt = shippingOptions.find(o => o.id === selectedShippingOption)!;
                        return (
                          <>
                            <p>{opt.name} - {opt.formatted_cost}</p>
                            <p>{opt.estimated_days}</p>
                          </>
                        );
                      })()}
                    </div>
                  )}
                </div>

                {/* Payment Method */}
                <div>
                  <h4 className="font-medium text-gray-900 mb-2">Payment Method</h4>
                  <p className="text-sm text-gray-600 capitalize">
                    {selectedPaymentMethod.replace('_', ' ')}
                  </p>
                </div>
              </div>

              <div className="flex gap-3">
                <button
                  onClick={() => setCurrentStep(2)}
                  disabled={isCreatingOrder}
                  className="flex-1 bg-white text-gray-700 py-3 px-4 rounded-md font-medium border border-gray-300 hover:bg-gray-50 disabled:opacity-50"
                >
                  Back
                </button>
                <button
                  onClick={handlePlaceOrder}
                  disabled={isCreatingOrder}
                  className="flex-1 bg-blue-600 text-white py-3 px-4 rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isCreatingOrder ? 'Processing...' : 'Place Order'}
                </button>
              </div>
            </>
          )}
        </div>

        {/* Order Summary Sidebar */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-lg shadow-md p-6 sticky top-4">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>

            {/* Cart Items */}
            <div className="space-y-3 mb-4 max-h-64 overflow-y-auto">
              {cartItems.map((item) => (
                <div key={item.id} className="flex justify-between items-start">
                  <div className="flex-1">
                    <h3 className="text-sm font-medium text-gray-900">
                      {item.product.name}
                    </h3>
                    <p className="text-sm text-gray-500">Qty: {item.quantity}</p>
                  </div>
                  <span className="text-sm font-medium text-gray-900">
                    {item.formatted_total}
                  </span>
                </div>
              ))}
            </div>

            {/* Totals */}
            <div className="border-t border-gray-200 pt-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Subtotal</span>
                <span className="text-gray-900">
                  {cartSummary?.formatted_subtotal || '₱0.00'}
                </span>
              </div>

              {creditsRedemption && (
                <div className="flex justify-between text-sm">
                  <span className="text-green-600">Credits Discount</span>
                  <span className="text-green-600">
                    -{formatCurrency(creditsRedemption.discountAmount)}
                  </span>
                </div>
              )}

              {selectedShippingOption && (
                <div className="flex justify-between text-sm">
                  <span className="text-gray-600">Shipping</span>
                  <span className="text-gray-900">
                    {shippingOptions.find(opt => opt.id === selectedShippingOption)?.formatted_cost || '₱0.00'}
                  </span>
                </div>
              )}

              <div className="border-t border-gray-200 pt-2">
                <div className="flex justify-between">
                  <span className="text-base font-semibold text-gray-900">Total</span>
                  <span className="text-base font-semibold text-gray-900">
                    {formatCurrency(calculateTotal())}
                  </span>
                </div>
              </div>
            </div>

            {/* Security Notice */}
            <div className="mt-4 flex items-center justify-center text-xs text-gray-500">
              <svg className="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              Secure checkout powered by SSL encryption
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CheckoutPage;