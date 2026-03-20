/**
 * Performance monitoring utilities for tracking page load times,
 * component render times, and custom performance metrics
 */

interface PerformanceMark {
  name: string;
  startTime: number;
  duration?: number;
}

class PerformanceMonitor {
  private marks: Map<string, number> = new Map();
  private measures: PerformanceMark[] = [];

  /**
   * Start a performance measurement
   */
  mark(name: string): void {
    this.marks.set(name, performance.now());
    if (performance.mark) {
      performance.mark(name);
    }
  }

  /**
   * End a performance measurement and calculate duration
   */
  measure(name: string, startMark: string): number | null {
    const startTime = this.marks.get(startMark);
    if (!startTime) {
      console.warn(`Start mark "${startMark}" not found`);
      return null;
    }

    const endTime = performance.now();
    const duration = endTime - startTime;

    this.measures.push({
      name,
      startTime,
      duration,
    });

    if (performance.measure) {
      try {
        performance.measure(name, startMark);
      } catch (e) {
        console.warn('Performance measure failed:', e);
      }
    }

    // Log in development
    if (process.env.NODE_ENV === 'development') {
      console.log(`[Performance] ${name}: ${duration.toFixed(2)}ms`);
    }

    // Send to analytics
    this.sendToAnalytics({
      type: 'custom-metric',
      name,
      duration,
      timestamp: Date.now(),
    });

    return duration;
  }

  /**
   * Get all performance measures
   */
  getMeasures(): PerformanceMark[] {
    return [...this.measures];
  }

  /**
   * Clear all marks and measures
   */
  clear(): void {
    this.marks.clear();
    this.measures = [];
    if (performance.clearMarks) {
      performance.clearMarks();
    }
    if (performance.clearMeasures) {
      performance.clearMeasures();
    }
  }

  /**
   * Track component render time
   */
  trackComponentRender(componentName: string, renderTime: number): void {
    if (process.env.NODE_ENV === 'development') {
      console.log(`[Render] ${componentName}: ${renderTime.toFixed(2)}ms`);
    }

    this.sendToAnalytics({
      type: 'component-render',
      component: componentName,
      duration: renderTime,
      timestamp: Date.now(),
    });
  }

  /**
   * Track API call performance
   */
  trackApiCall(endpoint: string, duration: number, status: number): void {
    if (process.env.NODE_ENV === 'development') {
      console.log(`[API] ${endpoint}: ${duration.toFixed(2)}ms (${status})`);
    }

    this.sendToAnalytics({
      type: 'api-call',
      endpoint,
      duration,
      status,
      timestamp: Date.now(),
    });
  }

  /**
   * Track page navigation
   */
  trackNavigation(from: string, to: string, duration: number): void {
    if (process.env.NODE_ENV === 'development') {
      console.log(`[Navigation] ${from} → ${to}: ${duration.toFixed(2)}ms`);
    }

    this.sendToAnalytics({
      type: 'navigation',
      from,
      to,
      duration,
      timestamp: Date.now(),
    });
  }

  /**
   * Send performance data to analytics endpoint
   */
  private sendToAnalytics(data: any): void {
    const body = JSON.stringify(data);

    if (navigator.sendBeacon) {
      navigator.sendBeacon('/api/analytics/performance', body);
    } else {
      fetch('/api/analytics/performance', {
        body,
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
      }).catch(console.error);
    }
  }

  /**
   * Get navigation timing metrics
   */
  getNavigationTiming(): PerformanceNavigationTiming | null {
    if (!performance.getEntriesByType) {
      return null;
    }

    const navEntries = performance.getEntriesByType('navigation') as PerformanceNavigationTiming[];
    return navEntries.length > 0 ? navEntries[0] : null;
  }

  /**
   * Get resource timing metrics
   */
  getResourceTiming(): PerformanceResourceTiming[] {
    if (!performance.getEntriesByType) {
      return [];
    }

    return performance.getEntriesByType('resource') as PerformanceResourceTiming[];
  }

  /**
   * Log page load performance summary
   */
  logPageLoadSummary(): void {
    const navTiming = this.getNavigationTiming();
    if (!navTiming) {
      console.warn('Navigation timing not available');
      return;
    }

    const metrics = {
      'DNS Lookup': navTiming.domainLookupEnd - navTiming.domainLookupStart,
      'TCP Connection': navTiming.connectEnd - navTiming.connectStart,
      'Request Time': navTiming.responseStart - navTiming.requestStart,
      'Response Time': navTiming.responseEnd - navTiming.responseStart,
      'DOM Processing': navTiming.domComplete - navTiming.domInteractive,
      'Load Complete': navTiming.loadEventEnd - navTiming.loadEventStart,
      'Total Load Time': navTiming.loadEventEnd - navTiming.fetchStart,
    };

    console.table(metrics);

    // Send summary to analytics
    this.sendToAnalytics({
      type: 'page-load-summary',
      metrics,
      timestamp: Date.now(),
    });
  }
}

// Export singleton instance
export const performanceMonitor = new PerformanceMonitor();

// Export hook for React components
export const usePerformanceMonitor = () => performanceMonitor;
