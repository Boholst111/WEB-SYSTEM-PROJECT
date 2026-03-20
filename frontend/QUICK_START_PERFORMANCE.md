# Quick Start: Performance Optimizations

## For Developers

### Using Lazy Loading Images

```tsx
import LazyImage from '@/components/ui/LazyImage';

function ProductCard({ product }) {
  return (
    <LazyImage
      src={product.imageUrl}
      alt={product.name}
      className="w-full h-full object-cover"
      threshold={0.1}
      rootMargin="100px"
    />
  );
}
```

### Using Code Splitting

```tsx
import { lazy, Suspense } from 'react';

// Lazy load a component
const HeavyComponent = lazy(() => import('./HeavyComponent'));

function App() {
  return (
    <Suspense fallback={<div>Loading...</div>}>
      <HeavyComponent />
    </Suspense>
  );
}
```

### Tracking Component Performance

```tsx
import { useComponentPerformance } from '@/hooks/useComponentPerformance';

function MyComponent() {
  useComponentPerformance('MyComponent');
  
  // Your component code
  return <div>Content</div>;
}
```

### Tracking Async Operations

```tsx
import { useAsyncPerformance } from '@/hooks/useComponentPerformance';

function MyComponent() {
  const { trackAsync } = useAsyncPerformance();
  
  const fetchData = async () => {
    await trackAsync('fetch-products', async () => {
      const response = await api.getProducts();
      return response.data;
    });
  };
  
  return <button onClick={fetchData}>Load Data</button>;
}
```

### Manual Performance Tracking

```tsx
import { performanceMonitor } from '@/utils/performanceMonitor';

// Start tracking
performanceMonitor.mark('operation-start');

// Do some work
await heavyOperation();

// Measure duration
const duration = performanceMonitor.measure('heavy-operation', 'operation-start');
console.log(`Operation took ${duration}ms`);
```

### Image Optimization Utilities

```tsx
import { 
  getOptimalImageUrl, 
  preloadImages,
  generateSrcSet 
} from '@/utils/imageOptimization';

// Get optimal format
const optimizedUrl = await getOptimalImageUrl('/image.jpg');

// Preload critical images
await preloadImages(['/hero.jpg', '/logo.png']);

// Generate responsive srcset
const srcset = generateSrcSet('/image.jpg', [320, 640, 1024, 1920]);
```

## Commands

```bash
# Install dependencies
npm install

# Start development server
npm start

# Build for production
npm run build

# Analyze bundle size
npm run build && npm run analyze

# Run tests
npm test

# Run Lighthouse audit
npx lighthouse http://localhost:3000 --view
```

## Performance Checklist

### Before Committing
- [ ] Used LazyImage for all product images
- [ ] Lazy loaded non-critical routes
- [ ] Added performance tracking to new components
- [ ] Tested on slow 3G network
- [ ] Checked bundle size impact
- [ ] No console.log in production code

### Before Deploying
- [ ] Run `npm run build` successfully
- [ ] Run `npm run analyze` to check bundle sizes
- [ ] Run Lighthouse audit (score > 90)
- [ ] Test service worker registration
- [ ] Test offline functionality
- [ ] Verify Core Web Vitals tracking

## Performance Targets

| Metric | Target | Critical |
|--------|--------|----------|
| LCP | < 2.5s | < 4.0s |
| FID | < 100ms | < 300ms |
| CLS | < 0.1 | < 0.25 |
| FCP | < 1.8s | < 3.0s |
| TTFB | < 600ms | < 1.8s |

## Common Issues

### Service Worker Not Updating
```bash
# Clear service worker
# Chrome DevTools > Application > Service Workers > Unregister
# Then hard refresh (Ctrl+Shift+R)
```

### Large Bundle Size
```bash
# Analyze what's in the bundle
npm run analyze

# Check for duplicate dependencies
npm ls <package-name>
```

### Slow Component Renders
```tsx
// Add performance tracking
import { useComponentPerformance } from '@/hooks/useComponentPerformance';

function SlowComponent() {
  useComponentPerformance('SlowComponent');
  // Check console for render times
}
```

## Resources

- [Full Documentation](./PERFORMANCE.md)
- [Implementation Details](./PERFORMANCE_IMPLEMENTATION.md)
- [Web Vitals](https://web.dev/vitals/)
- [React Performance](https://react.dev/learn/render-and-commit)
