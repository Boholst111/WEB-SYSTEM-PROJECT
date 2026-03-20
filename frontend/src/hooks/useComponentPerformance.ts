import { useEffect, useRef } from 'react';
import { performanceMonitor } from '../utils/performanceMonitor';

/**
 * Hook to track component mount and render performance
 */
export const useComponentPerformance = (componentName: string) => {
  const mountTimeRef = useRef<number>(0);
  const renderCountRef = useRef<number>(0);

  useEffect(() => {
    // Track component mount time
    mountTimeRef.current = performance.now();
    performanceMonitor.mark(`${componentName}-mount-start`);

    return () => {
      // Track component unmount
      const mountDuration = performance.now() - mountTimeRef.current;
      performanceMonitor.measure(
        `${componentName}-mount-duration`,
        `${componentName}-mount-start`
      );

      if (process.env.NODE_ENV === 'development') {
        console.log(
          `[Component Lifecycle] ${componentName} was mounted for ${mountDuration.toFixed(2)}ms`
        );
      }
    };
  }, [componentName]);

  useEffect(() => {
    // Track each render
    renderCountRef.current += 1;
    const renderTime = performance.now();

    if (process.env.NODE_ENV === 'development' && renderCountRef.current > 1) {
      console.log(`[Component Render] ${componentName} rendered ${renderCountRef.current} times`);
    }
  });

  return {
    renderCount: renderCountRef.current,
  };
};

/**
 * Hook to track async operations performance
 */
export const useAsyncPerformance = () => {
  const trackAsync = async <T,>(
    operationName: string,
    asyncFn: () => Promise<T>
  ): Promise<T> => {
    const startTime = performance.now();
    performanceMonitor.mark(`${operationName}-start`);

    try {
      const result = await asyncFn();
      const duration = performance.now() - startTime;
      
      performanceMonitor.measure(operationName, `${operationName}-start`);
      
      if (process.env.NODE_ENV === 'development') {
        console.log(`[Async Operation] ${operationName}: ${duration.toFixed(2)}ms`);
      }

      return result;
    } catch (error) {
      const duration = performance.now() - startTime;
      console.error(`[Async Operation Failed] ${operationName}: ${duration.toFixed(2)}ms`, error);
      throw error;
    }
  };

  return { trackAsync };
};
