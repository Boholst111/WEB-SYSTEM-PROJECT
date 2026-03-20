const { InjectManifest } = require('workbox-webpack-plugin');
const webpack = require('webpack');

module.exports = {
  webpack: {
    configure: (webpackConfig, { env, paths }) => {
      // Production optimizations
      if (env === 'production') {
        // Enable code splitting for better caching
        webpackConfig.optimization = {
          ...webpackConfig.optimization,
          splitChunks: {
            chunks: 'all',
            cacheGroups: {
              // Vendor chunk for node_modules
              vendor: {
                test: /[\\/]node_modules[\\/]/,
                name: 'vendors',
                priority: 10,
                reuseExistingChunk: true,
              },
              // React and related libraries
              react: {
                test: /[\\/]node_modules[\\/](react|react-dom|react-router-dom)[\\/]/,
                name: 'react-vendor',
                priority: 20,
                reuseExistingChunk: true,
              },
              // UI libraries
              ui: {
                test: /[\\/]node_modules[\\/](@headlessui|@heroicons|framer-motion)[\\/]/,
                name: 'ui-vendor',
                priority: 15,
                reuseExistingChunk: true,
              },
              // Common code shared between routes
              common: {
                minChunks: 2,
                priority: 5,
                reuseExistingChunk: true,
                enforce: true,
              },
            },
          },
          runtimeChunk: 'single',
          moduleIds: 'deterministic',
        };

        // Add service worker plugin
        webpackConfig.plugins.push(
          new InjectManifest({
            swSrc: './src/service-worker.ts',
            swDest: 'service-worker.js',
            maximumFileSizeToCacheInBytes: 5 * 1024 * 1024, // 5MB
            exclude: [
              /\.map$/,
              /^manifest.*\.js$/,
              /\.DS_Store$/,
              /^.*\.br$/,
              /^.*\.gz$/,
            ],
          })
        );

        // Compression and minification
        webpackConfig.plugins.push(
          new webpack.optimize.ModuleConcatenationPlugin()
        );
      }

      // Development optimizations
      if (env === 'development') {
        // Faster rebuilds in development
        webpackConfig.optimization = {
          ...webpackConfig.optimization,
          removeAvailableModules: false,
          removeEmptyChunks: false,
          splitChunks: false,
        };
      }

      return webpackConfig;
    },
  },
  // Babel configuration for better tree shaking
  babel: {
    plugins: [
      // Remove console.log in production
      ...(process.env.NODE_ENV === 'production'
        ? [['transform-remove-console', { exclude: ['error', 'warn'] }]]
        : []),
    ],
  },
};
