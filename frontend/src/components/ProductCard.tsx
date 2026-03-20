import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { HeartIcon, ShoppingCartIcon } from '@heroicons/react/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/react/24/solid';
import { Product } from '../types';
import { useAppDispatch, useAppSelector } from '../store';
import LazyImage from './ui/LazyImage';

interface ProductCardProps {
  product: Product;
  onAddToCart?: (product: Product) => void;
  onToggleWishlist?: (product: Product) => void;
}

const ProductCard: React.FC<ProductCardProps> = ({ 
  product, 
  onAddToCart, 
  onToggleWishlist 
}) => {
  const [imageLoaded, setImageLoaded] = useState(false);
  const [imageError, setImageError] = useState(false);
  const [isWishlisted, setIsWishlisted] = useState(false); // TODO: Get from wishlist state

  const handleAddToCart = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (onAddToCart) {
      onAddToCart(product);
    }
  };

  const handleToggleWishlist = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsWishlisted(!isWishlisted);
    if (onToggleWishlist) {
      onToggleWishlist(product);
    }
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
    }).format(price);
  };

  const getStockStatus = () => {
    if (product.isPreorder) {
      return { text: 'Pre-order', className: 'bg-blue-100 text-blue-800' };
    }
    if (product.stockQuantity === 0) {
      return { text: 'Out of Stock', className: 'bg-red-100 text-red-800' };
    }
    if (product.stockQuantity <= 5) {
      return { text: 'Low Stock', className: 'bg-yellow-100 text-yellow-800' };
    }
    return { text: 'In Stock', className: 'bg-green-100 text-green-800' };
  };

  const stockStatus = getStockStatus();
  const primaryImage = product.images?.[0] || '/placeholder-product.jpg';

  return (
    <div className="group relative bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">
      <Link to={`/products/${product.id}`} className="block">
        {/* Image Container */}
        <div className="relative aspect-square overflow-hidden bg-gray-100">
          <LazyImage
            src={primaryImage}
            alt={product.name}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            placeholderClassName="w-full h-full"
            onLoad={() => setImageLoaded(true)}
            onError={() => setImageError(true)}
            threshold={0.1}
            rootMargin="100px"
          />

          {/* Chase Variant Badge */}
          {product.isChaseVariant && (
            <div className="absolute top-2 left-2 bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-2 py-1 rounded-full text-xs font-semibold">
              Chase
            </div>
          )}

          {/* Stock Status Badge */}
          <div className={`absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-medium ${stockStatus.className}`}>
            {stockStatus.text}
          </div>

          {/* Wishlist Button */}
          <button
            onClick={handleToggleWishlist}
            className="absolute bottom-2 right-2 p-2 bg-white rounded-full shadow-md hover:shadow-lg transition-shadow duration-200 opacity-0 group-hover:opacity-100"
          >
            {isWishlisted ? (
              <HeartSolidIcon className="w-5 h-5 text-red-500" />
            ) : (
              <HeartIcon className="w-5 h-5 text-gray-600 hover:text-red-500" />
            )}
          </button>
        </div>

        {/* Product Info */}
        <div className="p-4">
          {/* Brand and Scale */}
          <div className="flex items-center justify-between text-sm text-gray-500 mb-1">
            <span>{product.brand?.name}</span>
            <span>{product.scale}</span>
          </div>

          {/* Product Name */}
          <h3 className="font-medium text-gray-900 mb-2 line-clamp-2 group-hover:text-blue-600 transition-colors">
            {product.name}
          </h3>

          {/* Features */}
          {product.features && product.features.length > 0 && (
            <div className="flex flex-wrap gap-1 mb-2">
              {product.features.slice(0, 2).map((feature, index) => (
                <span
                  key={index}
                  className="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded"
                >
                  {feature}
                </span>
              ))}
              {product.features.length > 2 && (
                <span className="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                  +{product.features.length - 2} more
                </span>
              )}
            </div>
          )}

          {/* Price */}
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              {product.currentPrice !== product.basePrice && (
                <span className="text-sm text-gray-500 line-through">
                  {formatPrice(product.basePrice)}
                </span>
              )}
              <span className="text-lg font-semibold text-gray-900">
                {formatPrice(product.currentPrice)}
              </span>
            </div>

            {/* Add to Cart Button */}
            {product.stockQuantity > 0 && !product.isPreorder && (
              <button
                onClick={handleAddToCart}
                className="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
              >
                <ShoppingCartIcon className="w-5 h-5" />
              </button>
            )}

            {product.isPreorder && (
              <button
                onClick={handleAddToCart}
                className="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-lg hover:bg-blue-200 transition-colors duration-200"
              >
                Pre-order
              </button>
            )}
          </div>
        </div>
      </Link>
    </div>
  );
};

export default ProductCard;