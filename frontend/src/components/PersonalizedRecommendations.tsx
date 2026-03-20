import React, { useEffect, useState } from 'react';
import RecommendationCarousel from './RecommendationCarousel';
import { Product } from '../types';
import { recommendationApi } from '../services/api';

interface PersonalizedRecommendationsProps {
  className?: string;
}

const PersonalizedRecommendations: React.FC<PersonalizedRecommendationsProps> = ({
  className = '',
}) => {
  const [personalizedProducts, setPersonalizedProducts] = useState<Product[]>([]);
  const [trendingProducts, setTrendingProducts] = useState<Product[]>([]);
  const [newArrivals, setNewArrivals] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchRecommendations = async () => {
      setLoading(true);
      try {
        const [personalized, trending, arrivals] = await Promise.all([
          recommendationApi.getPersonalizedRecommendations(10),
          recommendationApi.getTrendingProducts(10),
          recommendationApi.getNewArrivals(10),
        ]);

        setPersonalizedProducts(personalized.data || []);
        setTrendingProducts(trending.data || []);
        setNewArrivals(arrivals.data || []);
      } catch (error) {
        console.error('Failed to fetch recommendations:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchRecommendations();
  }, []);

  return (
    <div className={`space-y-12 ${className}`}>
      {personalizedProducts.length > 0 && (
        <RecommendationCarousel
          title="Recommended For You"
          products={personalizedProducts}
          loading={loading}
        />
      )}

      {trendingProducts.length > 0 && (
        <RecommendationCarousel
          title="Trending Now"
          products={trendingProducts}
          loading={loading}
        />
      )}

      {newArrivals.length > 0 && (
        <RecommendationCarousel
          title="New Arrivals"
          products={newArrivals}
          loading={loading}
        />
      )}
    </div>
  );
};

export default PersonalizedRecommendations;
