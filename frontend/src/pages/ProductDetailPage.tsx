import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  HeartIcon, 
  ShoppingCartIcon, 
  StarIcon,
  ChevronLeftIcon,
  ShareIcon,
  TruckIcon,
  ShieldCheckIcon,
  CurrencyDollarIcon
} from '@heroicons/react/24/outline';
import { HeartIcon as HeartSolidIcon, StarIcon as StarSolidIcon } from '@heroicons/react/24/solid';
import { useAppDispatch, useAppSelector } from '../store';
import { setCurrentProduct, setLoading, setError } from '../store/slices/productSlice';
import { productApi } from '../services/api';
import { Product } from '../types';
import ProductGallery from '../components/ProductGallery';

const ProductDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const { currentProduct, isLoading, error } = useAppSelector(state => state.products);
  
  const [quantity, setQuantity] = useState(1);
  const [selectedTab, setSelectedTab] = useState<'description' | 'specifications' | 'reviews'>('description');
  const [isWishlisted, setIsWishlisted] = useState(false);

  useEffect(() => {
    if (id) {
      loadProduct(parseInt(id));
    }
  }, [id]);

  const loadProduct = async (productId: number) => {
    try {
      dispatch(setLoading(true));
      dispatch(setError(null));
      const response = await productApi.getProduct(productId);
      dispatch(setCurrentProduct(response.data));
    } catch (err) {
      dispatch(setError('Failed to load product details'));
      console.error('Failed to load product:', err);
    } finally {
      dispatch(setLoading(false));
    }
  };

  const handleAddToCart = () => {
    if (currentProduct) {
      // TODO: Implement add to cart functionality
      console.log('Add to cart:', currentProduct, 'quantity:', quantity);
    }
  };

  const handleToggleWishlist = () => {
    setIsWishlisted(!isWishlisted);
    // TODO: Implement wishlist functionality
  };

  const handleShare = () => {
    if (navigator.share && currentProduct) {
      navigator.share({
        title: currentProduct.name,
        text: `Check out this ${currentProduct.scale} ${currentProduct.brand?.name} model`,
        url: window.location.href,
      });
    } else {
      // Fallback: copy to clipboard
      navigator.clipboard.writeText(window.location.href);
    }
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
    }).format(price);
  };

  const getStockStatus = () => {
    if (!currentProduct) return { text: '', className: '' };
    
    if (currentProduct.isPreorder) {
      return { text: 'Available for Pre-order', className: 'text-blue-600' };
    }
    if (currentProduct.stockQuantity === 0) {
      return { text: 'Out of Stock', className: 'text-red-600' };
    }
    if (currentProduct.stockQuantity <= 5) {
      return { text: `Only ${currentProduct.stockQuantity} left in stock`, className: 'text-yellow-600' };
    }
    return { text: 'In Stock', className: 'text-green-600' };
  };

  if (isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="animate-pulse">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="aspect-square bg-gray-200 rounded-lg"></div>
            <div className="space-y-4">
              <div className="h-8 bg-gray-200 rounded w-3/4"></div>
              <div className="h-6 bg-gray-200 rounded w-1/2"></div>
              <div className="h-10 bg-gray-200 rounded w-1/3"></div>
              <div className="space-y-2">
                <div className="h-4 bg-gray-200 rounded"></div>
                <div className="h-4 bg-gray-200 rounded w-5/6"></div>
                <div className="h-4 bg-gray-200 rounded w-4/6"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (error || !currentProduct) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="text-center">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Product not found</h3>
          <p className="text-gray-600 mb-4">{error || 'The product you are looking for does not exist.'}</p>
          <button
            onClick={() => navigate('/products')}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Browse Products
          </button>
        </div>
      </div>
    );
  }

  const stockStatus = getStockStatus();

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Breadcrumb */}
      <div className="flex items-center space-x-2 text-sm text-gray-500 mb-6">
        <button
          onClick={() => navigate('/products')}
          className="flex items-center space-x-1 hover:text-gray-700"
        >
          <ChevronLeftIcon className="w-4 h-4" />
          <span>Back to Products</span>
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
        {/* Product Images */}
        <div>
          <ProductGallery
            images={currentProduct.images || []}
            productName={currentProduct.name}
            className="sticky top-8"
          />
        </div>

        {/* Product Info */}
        <div className="space-y-6">
          {/* Header */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center space-x-2 text-sm text-gray-500">
                <span>{currentProduct.brand?.name}</span>
                <span>•</span>
                <span>{currentProduct.scale}</span>
                <span>•</span>
                <span>SKU: {currentProduct.sku}</span>
              </div>
              <button
                onClick={handleShare}
                className="p-2 text-gray-400 hover:text-gray-600"
              >
                <ShareIcon className="w-5 h-5" />
              </button>
            </div>
            
            <h1 className="text-3xl font-bold text-gray-900 mb-2">{currentProduct.name}</h1>
            
            {currentProduct.isChaseVariant && (
              <div className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gradient-to-r from-yellow-400 to-orange-500 text-white mb-4">
                ⭐ Chase Variant
              </div>
            )}
          </div>

          {/* Price */}
          <div className="space-y-2">
            <div className="flex items-center space-x-3">
              {currentProduct.currentPrice !== currentProduct.basePrice && (
                <span className="text-xl text-gray-500 line-through">
                  {formatPrice(currentProduct.basePrice)}
                </span>
              )}
              <span className="text-3xl font-bold text-gray-900">
                {formatPrice(currentProduct.currentPrice)}
              </span>
              {currentProduct.currentPrice < currentProduct.basePrice && (
                <span className="bg-red-100 text-red-800 text-sm px-2 py-1 rounded">
                  Save {formatPrice(currentProduct.basePrice - currentProduct.currentPrice)}
                </span>
              )}
            </div>
            <p className={`text-sm font-medium ${stockStatus.className}`}>
              {stockStatus.text}
            </p>
          </div>

          {/* Features */}
          {currentProduct.features && currentProduct.features.length > 0 && (
            <div>
              <h3 className="text-sm font-medium text-gray-900 mb-2">Features</h3>
              <div className="flex flex-wrap gap-2">
                {currentProduct.features.map((feature, index) => (
                  <span
                    key={index}
                    className="inline-block bg-gray-100 text-gray-700 text-sm px-3 py-1 rounded-full"
                  >
                    {feature}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Quantity and Add to Cart */}
          {currentProduct.stockQuantity > 0 && (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Quantity
                </label>
                <div className="flex items-center space-x-3">
                  <button
                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                    className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                  >
                    -
                  </button>
                  <span className="px-4 py-2 border border-gray-300 rounded-lg min-w-[60px] text-center">
                    {quantity}
                  </span>
                  <button
                    onClick={() => setQuantity(Math.min(currentProduct.stockQuantity, quantity + 1))}
                    className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                  >
                    +
                  </button>
                </div>
              </div>

              <div className="flex space-x-4">
                <button
                  onClick={handleAddToCart}
                  className="flex-1 flex items-center justify-center space-x-2 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <ShoppingCartIcon className="w-5 h-5" />
                  <span>{currentProduct.isPreorder ? 'Pre-order Now' : 'Add to Cart'}</span>
                </button>
                
                <button
                  onClick={handleToggleWishlist}
                  className="p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  {isWishlisted ? (
                    <HeartSolidIcon className="w-6 h-6 text-red-500" />
                  ) : (
                    <HeartIcon className="w-6 h-6 text-gray-600" />
                  )}
                </button>
              </div>
            </div>
          )}

          {/* Trust Badges */}
          <div className="grid grid-cols-3 gap-4 pt-6 border-t">
            <div className="text-center">
              <TruckIcon className="w-8 h-8 text-blue-600 mx-auto mb-2" />
              <p className="text-xs text-gray-600">Free Shipping</p>
            </div>
            <div className="text-center">
              <ShieldCheckIcon className="w-8 h-8 text-green-600 mx-auto mb-2" />
              <p className="text-xs text-gray-600">Authentic Products</p>
            </div>
            <div className="text-center">
              <CurrencyDollarIcon className="w-8 h-8 text-purple-600 mx-auto mb-2" />
              <p className="text-xs text-gray-600">Earn Credits</p>
            </div>
          </div>
        </div>
      </div>

      {/* Product Details Tabs */}
      <div className="bg-white rounded-lg shadow-sm">
        <div className="border-b border-gray-200">
          <nav className="flex space-x-8 px-6">
            {[
              { id: 'description', label: 'Description' },
              { id: 'specifications', label: 'Specifications' },
              { id: 'reviews', label: 'Reviews' },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => setSelectedTab(tab.id as any)}
                className={`py-4 px-1 border-b-2 font-medium text-sm ${
                  selectedTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </nav>
        </div>

        <div className="p-6">
          {selectedTab === 'description' && (
            <div className="prose max-w-none">
              <p className="text-gray-700 leading-relaxed">
                {currentProduct.description || 'No description available for this product.'}
              </p>
            </div>
          )}

          {selectedTab === 'specifications' && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {currentProduct.specifications && Object.keys(currentProduct.specifications).length > 0 ? (
                Object.entries(currentProduct.specifications).map(([key, value]) => (
                  <div key={key} className="flex justify-between py-2 border-b border-gray-100">
                    <span className="font-medium text-gray-900 capitalize">
                      {key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())}
                    </span>
                    <span className="text-gray-600">{String(value)}</span>
                  </div>
                ))
              ) : (
                <p className="text-gray-500 col-span-2">No specifications available for this product.</p>
              )}
            </div>
          )}

          {selectedTab === 'reviews' && (
            <div className="text-center py-8">
              <p className="text-gray-500">Reviews feature coming soon...</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ProductDetailPage;