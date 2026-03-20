# Frontend Performance Optimizations

This document outlines the performance optimizations implemented for the Diecast Empire frontend to achieve sub-2-second load times and handle 100-500 concurrent users during Drop Day events.

## Implemented Optimizations

### 1. Lazy Loading

#### Image Lazy Loading
- **LazyImage Component**: Custom component using Intersection Observer API
- **Benefits**: Images load only when they enter the viewport
- **Implementation**: `src/components/ui/LazyImage.tsx`
- **Usage**: Automatically applied to all product images via ProductCard

#### Route-Based Code Splitting
- **React.lazy()**: Dynamic imports for route components
- **Benefits**: Reduces initial bundle size by ~60%
- **Implementation**: `src/App.tsx`
- **Strategy**:
  - Eager load: HomePage, ProductsPage, ProductDetailPage (critical paths)
  - Lazy load: Cart, Checkout, Admin pages, Authentication pages

### 2. Bundle Optimization

#### Code Splitting Strategy
- **Vendor Chunks**: Separate chunks for node_modules
- **React Vendor**: Dedicated chunk for React libraries
- **UI Vendor**: Separate chunk for UI libraries (@headlessui, @heroicons)
- **Common Chunks**: Shared code between routes
- **Configuration**: `craco.config.js`

#### Build Optimizations
- **Tree Shaking**: Remove unused code
- **Minification**: Compress JavaScript and CSS
- **Module Concatenation**: Reduce module overhead
- **Console Removal**: Remove console.log in production (keeps error/warn)

#### Bundle Analysis
```bash
npm run build
npm run analyze
```

### 3. Service Worker & Offline Functionality

#### Caching Strategies
- **Precaching**: App shell and critical assets
- **CacheFirst**: Images (30-day expiration, max 100 entries)
- **NetworkFirst**: API responses (5-minute expiration, max 50 entries)
- **StaleWhileRevalidate**: CSS/JS files
- **CacheFirst**: Fonts (1-year expiration)

#### Implementation
- **Service Worker**: `src/service-worker.ts`
- **Registration**: `src/serviceWorkerRegistration.ts`
- **Auto-update**: Prompts user when new version available

#### Benefits
- Offline browsing of previously visited pages
- Faster repeat visits
- Reduced server load
- Better user experience on slow connections

### 4. Performance Monitoring

#### Core Web Vitals Tracking
- **LCP** (Largest Contentful Paint): Target < 2.5s
- **FID** (First Input Delay): Target < 100ms
- **CLS** (Cumulative Layout Shift): Target < 0.1
- **FCP** (First Contentful Paint): Target < 1.8s
- **TTFB** (Time to First Byte): Target < 600ms
- **INP** (Interaction to Next Paint): Target < 200ms

#### Custom Performance Tracking
- **Component Render Times**: Track slow components
- **API Call Performance**: Monitor endpoint response times
- **Navigation Timing**: Track route transitions
- **Resource Loading**: Monitor asset load times

#### Implementation
- **Web Vitals**: `src/reportWebVitals.ts`
- **Performance Monitor**: `src/utils/performanceMonitor.ts`
- **React Hook**: `src/hooks/useComponentPerformance.ts`

#### Analytics Endpoints
Performance data is sent to:
- `/api/analytics/web-vitals` - Core Web Vitals metrics
- `/api/analytics/performance` - Custom performance metrics

### 5. Additional Optimizations

#### React Query Configuration
- **Stale Time**: 5 minutes (reduces unnecessary refetches)
- **Retry**: 1 attempt (faster failure feedback)
- **Window Focus Refetch**: Disabled (reduces unnecessary requests)

#### Image Optimization
- **Lazy Loading**: Native + Intersection Observer
- **Placeholder**: Animated skeleton while loading
- **Error Handling**: Graceful fallback for failed images
- **Responsive Images**: Proper sizing for different viewports

#### Infinite Scroll
- **Intersection Observer**: Efficient scroll detection
- **Root Margin**: 100px preload buffer
- **Threshold**: 0.1 (10% visibility triggers load)

## Performance Targets

### Load Time Targets
- **Initial Load**: < 2 seconds (LCP)
- **Time to Interactive**: < 3 seconds
- **First Contentful Paint**: < 1.8 seconds

### Bundle Size Targets
- **Initial Bundle**: < 200KB (gzipped)
- **Vendor Bundle**: < 150KB (gzipped)
- **Route Chunks**: < 50KB each (gzipped)

### Runtime Performance
- **Component Render**: < 16ms (60fps)
- **API Response**: < 500ms average
- **Navigation**: < 200ms route transition

## Monitoring & Debugging

### Development Tools
```bash
# Start development server
npm start

# Build production bundle
npm run build

# Analyze bundle size
npm run analyze

# Run performance audit
npm run build && npx lighthouse http://localhost:3000
```

### Browser DevTools
1. **Performance Tab**: Record and analyze runtime performance
2. **Network Tab**: Monitor resource loading
3. **Lighthouse**: Run performance audits
4. **Coverage Tab**: Identify unused code

### Production Monitoring
- Core Web Vitals sent to `/api/analytics/web-vitals`
- Custom metrics sent to `/api/analytics/performance`
- Real User Monitoring (RUM) via web-vitals library

## Best Practices

### For Developers

1. **Use Lazy Loading**
   ```tsx
   import { lazy } from 'react';
   const Component = lazy(() => import('./Component'));
   ```

2. **Track Component Performance**
   ```tsx
   import { useComponentPerformance } from '@/hooks/useComponentPerformance';
   
   function MyComponent() {
     useComponentPerformance('MyComponent');
     // component code
   }
   ```

3. **Optimize Images**
   ```tsx
   import LazyImage from '@/components/ui/LazyImage';
   
   <LazyImage src={url} alt={alt} />
   ```

4. **Monitor API Calls**
   ```tsx
   import { performanceMonitor } from '@/utils/performanceMonitor';
   
   const start = performance.now();
   const response = await api.call();
   performanceMonitor.trackApiCall('/api/endpoint', performance.now() - start, response.status);
   ```

### For Testing

1. **Test on Slow Networks**
   - Chrome DevTools > Network > Throttling > Slow 3G

2. **Test on Low-End Devices**
   - Chrome DevTools > Performance > CPU throttling > 4x slowdown

3. **Test Offline Functionality**
   - Chrome DevTools > Application > Service Workers > Offline

4. **Run Lighthouse Audits**
   ```bash
   npx lighthouse http://localhost:3000 --view
   ```

## Troubleshooting

### Service Worker Issues
```bash
# Clear service worker cache
# Chrome DevTools > Application > Service Workers > Unregister
# Then hard refresh (Ctrl+Shift+R)
```

### Bundle Size Issues
```bash
# Analyze bundle
npm run analyze

# Check for duplicate dependencies
npm ls <package-name>
```

### Performance Regressions
1. Check bundle size changes
2. Review new dependencies
3. Profile component renders
4. Check API response times
5. Review Core Web Vitals metrics

## Future Optimizations

### Planned Improvements
- [ ] Image CDN integration with automatic format conversion (WebP/AVIF)
- [ ] HTTP/2 Server Push for critical resources
- [ ] Preload/Prefetch for likely navigation paths
- [ ] Virtual scrolling for large product lists
- [ ] Progressive image loading (blur-up technique)
- [ ] Resource hints (dns-prefetch, preconnect)
- [ ] Critical CSS extraction
- [ ] Font optimization (font-display: swap)

### Monitoring Enhancements
- [ ] Real-time performance dashboard
- [ ] Automated performance regression alerts
- [ ] A/B testing for performance optimizations
- [ ] User-centric performance metrics by device/network

## Resources

- [Web Vitals](https://web.dev/vitals/)
- [React Performance](https://react.dev/learn/render-and-commit)
- [Workbox Documentation](https://developers.google.com/web/tools/workbox)
- [Lighthouse](https://developers.google.com/web/tools/lighthouse)
- [Bundle Analysis](https://create-react-app.dev/docs/analyzing-the-bundle-size/)
