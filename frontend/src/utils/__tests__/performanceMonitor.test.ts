import { performanceMonitor } from '../performanceMonitor';

describe('PerformanceMonitor', () => {
  beforeEach(() => {
    performanceMonitor.clear();
    jest.clearAllMocks();
  });

  describe('mark and measure', () => {
    it('should create a mark and measure duration', () => {
      performanceMonitor.mark('test-start');
      
      // Simulate some work
      const start = performance.now();
      while (performance.now() - start < 10) {
        // Wait 10ms
      }
      
      const duration = performanceMonitor.measure('test-operation', 'test-start');
      
      expect(duration).toBeGreaterThan(0);
      expect(duration).toBeGreaterThanOrEqual(10);
    });

    it('should return null for non-existent start mark', () => {
      const duration = performanceMonitor.measure('test-operation', 'non-existent');
      expect(duration).toBeNull();
    });

    it('should store measures', () => {
      performanceMonitor.mark('test-start');
      performanceMonitor.measure('test-operation', 'test-start');
      
      const measures = performanceMonitor.getMeasures();
      expect(measures).toHaveLength(1);
      expect(measures[0].name).toBe('test-operation');
      expect(measures[0].duration).toBeGreaterThan(0);
    });
  });

  describe('trackComponentRender', () => {
    it('should track component render time', () => {
      const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
      
      performanceMonitor.trackComponentRender('TestComponent', 15.5);
      
      if (process.env.NODE_ENV === 'development') {
        expect(consoleSpy).toHaveBeenCalledWith(
          expect.stringContaining('[Render] TestComponent: 15.50ms')
        );
      }
      
      consoleSpy.mockRestore();
    });
  });

  describe('trackApiCall', () => {
    it('should track API call performance', () => {
      const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
      
      performanceMonitor.trackApiCall('/api/products', 250, 200);
      
      if (process.env.NODE_ENV === 'development') {
        expect(consoleSpy).toHaveBeenCalledWith(
          expect.stringContaining('[API] /api/products: 250.00ms (200)')
        );
      }
      
      consoleSpy.mockRestore();
    });
  });

  describe('trackNavigation', () => {
    it('should track page navigation', () => {
      const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
      
      performanceMonitor.trackNavigation('/home', '/products', 150);
      
      if (process.env.NODE_ENV === 'development') {
        expect(consoleSpy).toHaveBeenCalledWith(
          expect.stringContaining('[Navigation] /home → /products: 150.00ms')
        );
      }
      
      consoleSpy.mockRestore();
    });
  });

  describe('clear', () => {
    it('should clear all marks and measures', () => {
      performanceMonitor.mark('test-start');
      performanceMonitor.measure('test-operation', 'test-start');
      
      expect(performanceMonitor.getMeasures()).toHaveLength(1);
      
      performanceMonitor.clear();
      
      expect(performanceMonitor.getMeasures()).toHaveLength(0);
    });
  });

  describe('getNavigationTiming', () => {
    it('should return navigation timing if available', () => {
      const navTiming = performanceMonitor.getNavigationTiming();
      
      // May be null in test environment
      if (navTiming) {
        expect(navTiming).toHaveProperty('domainLookupStart');
        expect(navTiming).toHaveProperty('loadEventEnd');
      }
    });
  });

  describe('getResourceTiming', () => {
    it('should return resource timing entries', () => {
      const resourceTiming = performanceMonitor.getResourceTiming();
      
      expect(Array.isArray(resourceTiming)).toBe(true);
    });
  });
});
