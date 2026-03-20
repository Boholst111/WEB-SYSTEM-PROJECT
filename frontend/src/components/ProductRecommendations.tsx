import React, { useEffect, useState } from 'react';
import RecommendationCarousel from './RecommendationCarousel';
import { Product } from '../types';
import { recommendationApi } from '../services/api';

interface ProductRecommendationsProps {
  productId: number;
  className?: string;
}

const ProductRecommendations: React.FC<ProductRecommendationsProps> = ({
  productId,
  className = '',
}) => {
  const [similarProducts, setSimilarProducts] = useState<Product[]>([]);
  const [crossSellProducts, setCrossSellProducts] = useState<Product[]>([]);
  const [upsellProducts, setUpsellProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchRecommendations = async () => {
      setLoading(true);
      try {
        const [similar, crossSell, upsell] = await Promise.all([
          recommendationApi.getSimilarProducts(productId, 10),
          recommendationApi.getCrossSellProducts(productId, 6),
          recommendationApi.getUpsellProducts(productId, 6),
        ]);

        setSimilarProducts(similar.data || []);
        setCrossSellProducts(crossSell.data || []);
        setUpsellProducts(upsell.data || []);
      } catch (error) {
        console.error('Failed to fetch recommendations:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchRecommendations();
  }, [productId]);

  return (
    <div className={`space-y-12 ${className}`}>
      {crossSellProducts.length > 0 && (
        <RecommendationCarousel
          title="Frequently Bought Together"
          products={crossSellProducts}
          loading={loading}
        />
      )}

      {upsellProducts.length > 0 && (
        <RecommendationCarousel
          title="You Might Also Like"
          products={upsellProducts}
          loading={loading}
        />
      )}

      {similarProducts.length > 0 && (
        <RecommendationCarousel
          title="Similar Products"
          products={similarProducts}
          loading={loading}
        />
      )}
    </div>
  );
};

export default ProductRecommendations;
