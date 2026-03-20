/**
 * Image optimization utilities for responsive images and format detection
 */

/**
 * Generate srcset for responsive images
 */
export const generateSrcSet = (baseUrl: string, widths: number[]): string => {
  return widths.map((width) => `${baseUrl}?w=${width} ${width}w`).join(', ');
};

/**
 * Generate sizes attribute for responsive images
 */
export const generateSizes = (breakpoints: { maxWidth: string; size: string }[]): string => {
  return breakpoints.map((bp) => `(max-width: ${bp.maxWidth}) ${bp.size}`).join(', ');
};

/**
 * Check if browser supports WebP format
 */
export const supportsWebP = (): Promise<boolean> => {
  return new Promise((resolve) => {
    const webP = new Image();
    webP.onload = webP.onerror = () => {
      resolve(webP.height === 2);
    };
    webP.src =
      'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
  });
};

/**
 * Check if browser supports AVIF format
 */
export const supportsAVIF = (): Promise<boolean> => {
  return new Promise((resolve) => {
    const avif = new Image();
    avif.onload = avif.onerror = () => {
      resolve(avif.height === 2);
    };
    avif.src =
      'data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAADybWV0YQAAAAAAAAAoaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAGxpYmF2aWYAAAAADnBpdG0AAAAAAAEAAAAeaWxvYwAAAABEAAABAAEAAAABAAABGgAAAB0AAAAoaWluZgAAAAAAAQAAABppbmZlAgAAAAABAABhdjAxQ29sb3IAAAAAamlwcnAAAABLaXBjbwAAABRpc3BlAAAAAAAAAAIAAAACAAAAEHBpeGkAAAAAAwgICAAAAAxhdjFDgQ0MAAAAABNjb2xybmNseAACAAIAAYAAAAAXaXBtYQAAAAAAAAABAAEEAQKDBAAAACVtZGF0EgAKCBgANogQEAwgMg8f8D///8WfhwB8+ErK42A=';
  });
};

/**
 * Get optimal image format based on browser support
 */
export const getOptimalImageFormat = async (): Promise<'avif' | 'webp' | 'jpg'> => {
  if (await supportsAVIF()) {
    return 'avif';
  }
  if (await supportsWebP()) {
    return 'webp';
  }
  return 'jpg';
};

/**
 * Convert image URL to optimal format
 */
export const getOptimalImageUrl = async (url: string): Promise<string> => {
  const format = await getOptimalImageFormat();
  
  // If URL already has query params, append format
  const separator = url.includes('?') ? '&' : '?';
  return `${url}${separator}format=${format}`;
};

/**
 * Preload critical images
 */
export const preloadImage = (url: string): Promise<void> => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve();
    img.onerror = reject;
    img.src = url;
  });
};

/**
 * Preload multiple images
 */
export const preloadImages = (urls: string[]): Promise<void[]> => {
  return Promise.all(urls.map(preloadImage));
};

/**
 * Calculate image dimensions while maintaining aspect ratio
 */
export const calculateImageDimensions = (
  originalWidth: number,
  originalHeight: number,
  maxWidth: number,
  maxHeight: number
): { width: number; height: number } => {
  const aspectRatio = originalWidth / originalHeight;

  let width = originalWidth;
  let height = originalHeight;

  if (width > maxWidth) {
    width = maxWidth;
    height = width / aspectRatio;
  }

  if (height > maxHeight) {
    height = maxHeight;
    width = height * aspectRatio;
  }

  return {
    width: Math.round(width),
    height: Math.round(height),
  };
};

/**
 * Get image placeholder (blur hash or low quality placeholder)
 */
export const getImagePlaceholder = (url: string): string => {
  // Return a low-quality version of the image
  const separator = url.includes('?') ? '&' : '?';
  return `${url}${separator}w=20&q=10&blur=10`;
};

/**
 * Lazy load background image
 */
export const lazyLoadBackgroundImage = (element: HTMLElement, imageUrl: string): void => {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          element.style.backgroundImage = `url(${imageUrl})`;
          observer.unobserve(element);
        }
      });
    },
    {
      rootMargin: '50px',
    }
  );

  observer.observe(element);
};

/**
 * Image loading priority hints
 */
export type ImagePriority = 'high' | 'low' | 'auto';

/**
 * Get loading attribute based on priority
 */
export const getLoadingAttribute = (priority: ImagePriority): 'eager' | 'lazy' => {
  return priority === 'high' ? 'eager' : 'lazy';
};

/**
 * Get fetchpriority attribute based on priority
 */
export const getFetchPriority = (priority: ImagePriority): 'high' | 'low' | 'auto' => {
  return priority;
};

/**
 * Common responsive image breakpoints
 */
export const RESPONSIVE_BREAKPOINTS = {
  mobile: [320, 375, 414, 480],
  tablet: [640, 768, 1024],
  desktop: [1280, 1440, 1920, 2560],
  all: [320, 375, 414, 480, 640, 768, 1024, 1280, 1440, 1920, 2560],
};

/**
 * Common image sizes for different use cases
 */
export const IMAGE_SIZES = {
  thumbnail: { width: 150, height: 150 },
  small: { width: 300, height: 300 },
  medium: { width: 600, height: 600 },
  large: { width: 1200, height: 1200 },
  hero: { width: 1920, height: 1080 },
};
