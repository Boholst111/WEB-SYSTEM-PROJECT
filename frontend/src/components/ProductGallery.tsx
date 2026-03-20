import React, { useState, useEffect } from 'react';
import { ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { MagnifyingGlassPlusIcon } from '@heroicons/react/24/outline';

interface ProductGalleryProps {
  images: string[];
  productName: string;
  className?: string;
}

const ProductGallery: React.FC<ProductGalleryProps> = ({
  images,
  productName,
  className = "",
}) => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalIndex, setModalIndex] = useState(0);
  const [imageLoadStates, setImageLoadStates] = useState<boolean[]>([]);

  useEffect(() => {
    setImageLoadStates(new Array(images.length).fill(false));
  }, [images.length]);

  const handleImageLoad = (index: number) => {
    setImageLoadStates(prev => {
      const newStates = [...prev];
      newStates[index] = true;
      return newStates;
    });
  };

  const goToPrevious = () => {
    setCurrentIndex(prev => (prev === 0 ? images.length - 1 : prev - 1));
  };

  const goToNext = () => {
    setCurrentIndex(prev => (prev === images.length - 1 ? 0 : prev + 1));
  };

  const openModal = (index: number) => {
    setModalIndex(index);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
  };

  const goToModalPrevious = () => {
    setModalIndex(prev => (prev === 0 ? images.length - 1 : prev - 1));
  };

  const goToModalNext = () => {
    setModalIndex(prev => (prev === images.length - 1 ? 0 : prev + 1));
  };

  const handleKeyDown = (e: KeyboardEvent) => {
    if (!isModalOpen) return;
    
    switch (e.key) {
      case 'ArrowLeft':
        goToModalPrevious();
        break;
      case 'ArrowRight':
        goToModalNext();
        break;
      case 'Escape':
        closeModal();
        break;
    }
  };

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isModalOpen]);

  // Prevent body scroll when modal is open
  useEffect(() => {
    if (isModalOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }
    
    return () => {
      document.body.style.overflow = 'unset';
    };
  }, [isModalOpen]);

  if (!images || images.length === 0) {
    return (
      <div className={`bg-gray-100 rounded-lg flex items-center justify-center ${className}`}>
        <div className="text-center text-gray-400">
          <div className="w-16 h-16 bg-gray-200 rounded-lg mx-auto mb-2"></div>
          <p className="text-sm">No images available</p>
        </div>
      </div>
    );
  }

  return (
    <>
      <div className={`space-y-4 ${className}`}>
        {/* Main Image */}
        <div className="relative aspect-square bg-gray-100 rounded-lg overflow-hidden group">
          <img
            src={images[currentIndex]}
            alt={`${productName} - Image ${currentIndex + 1}`}
            className={`w-full h-full object-cover transition-opacity duration-300 ${
              imageLoadStates[currentIndex] ? 'opacity-100' : 'opacity-0'
            }`}
            onLoad={() => handleImageLoad(currentIndex)}
          />
          
          {/* Loading placeholder */}
          {!imageLoadStates[currentIndex] && (
            <div className="absolute inset-0 bg-gray-200 animate-pulse flex items-center justify-center">
              <div className="w-16 h-16 bg-gray-300 rounded"></div>
            </div>
          )}

          {/* Navigation arrows */}
          {images.length > 1 && (
            <>
              <button
                onClick={goToPrevious}
                className="absolute left-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-80 hover:bg-opacity-100 rounded-full p-2 shadow-md transition-all duration-200 opacity-0 group-hover:opacity-100"
              >
                <ChevronLeftIcon className="w-5 h-5 text-gray-700" />
              </button>
              <button
                onClick={goToNext}
                className="absolute right-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-80 hover:bg-opacity-100 rounded-full p-2 shadow-md transition-all duration-200 opacity-0 group-hover:opacity-100"
              >
                <ChevronRightIcon className="w-5 h-5 text-gray-700" />
              </button>
            </>
          )}

          {/* Zoom button */}
          <button
            onClick={() => openModal(currentIndex)}
            className="absolute top-2 right-2 bg-white bg-opacity-80 hover:bg-opacity-100 rounded-full p-2 shadow-md transition-all duration-200 opacity-0 group-hover:opacity-100"
          >
            <MagnifyingGlassPlusIcon className="w-5 h-5 text-gray-700" />
          </button>

          {/* Image counter */}
          {images.length > 1 && (
            <div className="absolute bottom-2 right-2 bg-black bg-opacity-60 text-white px-2 py-1 rounded text-sm">
              {currentIndex + 1} / {images.length}
            </div>
          )}
        </div>

        {/* Thumbnail Navigation */}
        {images.length > 1 && (
          <div className="flex space-x-2 overflow-x-auto pb-2">
            {images.map((image, index) => (
              <button
                key={index}
                onClick={() => setCurrentIndex(index)}
                className={`flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-all duration-200 ${
                  index === currentIndex
                    ? 'border-blue-500 ring-2 ring-blue-200'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <img
                  src={image}
                  alt={`${productName} thumbnail ${index + 1}`}
                  className="w-full h-full object-cover"
                  loading="lazy"
                />
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center">
          <div className="relative max-w-7xl max-h-full p-4">
            {/* Close button */}
            <button
              onClick={closeModal}
              className="absolute top-4 right-4 z-10 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 text-white transition-all duration-200"
            >
              <XMarkIcon className="w-6 h-6" />
            </button>

            {/* Modal image */}
            <div className="relative">
              <img
                src={images[modalIndex]}
                alt={`${productName} - Image ${modalIndex + 1}`}
                className="max-w-full max-h-[90vh] object-contain"
              />

              {/* Modal navigation */}
              {images.length > 1 && (
                <>
                  <button
                    onClick={goToModalPrevious}
                    className="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-full p-3 text-white transition-all duration-200"
                  >
                    <ChevronLeftIcon className="w-6 h-6" />
                  </button>
                  <button
                    onClick={goToModalNext}
                    className="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-full p-3 text-white transition-all duration-200"
                  >
                    <ChevronRightIcon className="w-6 h-6" />
                  </button>
                </>
              )}

              {/* Modal counter */}
              <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-black bg-opacity-60 text-white px-3 py-1 rounded-full text-sm">
                {modalIndex + 1} / {images.length}
              </div>
            </div>

            {/* Modal thumbnails */}
            {images.length > 1 && (
              <div className="flex justify-center space-x-2 mt-4 overflow-x-auto">
                {images.map((image, index) => (
                  <button
                    key={index}
                    onClick={() => setModalIndex(index)}
                    className={`flex-shrink-0 w-12 h-12 rounded overflow-hidden border-2 transition-all duration-200 ${
                      index === modalIndex
                        ? 'border-white'
                        : 'border-gray-400 hover:border-gray-200'
                    }`}
                  >
                    <img
                      src={image}
                      alt={`Thumbnail ${index + 1}`}
                      className="w-full h-full object-cover"
                    />
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
};

export default ProductGallery;