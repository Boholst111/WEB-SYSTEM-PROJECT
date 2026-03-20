import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, StarIcon, TruckIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';

const HomePage: React.FC = () => {
  return (
    <div className="space-y-16">
      {/* Hero Section */}
      <section className="relative bg-gradient-to-r from-primary-600 to-primary-800 text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
          <div className="text-center">
            <h1 className="text-4xl md:text-6xl font-bold font-display mb-6">
              Welcome to Diecast Empire
            </h1>
            <p className="text-xl md:text-2xl mb-8 text-primary-100 max-w-3xl mx-auto">
              Your premier destination for scale model collecting. Discover rare chase variants, 
              secure pre-orders, and earn loyalty credits with every purchase.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                to="/products"
                className="inline-flex items-center px-8 py-3 bg-white text-primary-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors"
              >
                Shop Now
                <ArrowRightIcon className="ml-2 h-5 w-5" />
              </Link>
              <Link
                to="/products?isPreorder=true"
                className="inline-flex items-center px-8 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-primary-600 transition-colors"
              >
                View Pre-Orders
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold font-display text-gray-900 mb-4">
            Why Choose Diecast Empire?
          </h2>
          <p className="text-lg text-gray-600 max-w-2xl mx-auto">
            We're not just another online store. We're collectors serving collectors 
            with specialized features designed for the diecast community.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div className="text-center p-6">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-primary-100 text-primary-600 rounded-full mb-4">
              <StarIcon className="h-8 w-8" />
            </div>
            <h3 className="text-xl font-semibold mb-2">Chase Variants & Exclusives</h3>
            <p className="text-gray-600">
              Access rare chase variants and exclusive releases. Get notified first 
              when limited editions become available.
            </p>
          </div>

          <div className="text-center p-6">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-primary-100 text-primary-600 rounded-full mb-4">
              <TruckIcon className="h-8 w-8" />
            </div>
            <h3 className="text-xl font-semibold mb-2">Pre-Order System</h3>
            <p className="text-gray-600">
              Secure upcoming releases with flexible deposit options. 
              Track arrival dates and complete payments when ready.
            </p>
          </div>

          <div className="text-center p-6">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-primary-100 text-primary-600 rounded-full mb-4">
              <ShieldCheckIcon className="h-8 w-8" />
            </div>
            <h3 className="text-xl font-semibold mb-2">Loyalty Credits</h3>
            <p className="text-gray-600">
              Earn credits with every purchase and redeem them for discounts. 
              Advance through tiers for exclusive benefits.
            </p>
          </div>
        </div>
      </section>

      {/* Featured Categories */}
      <section className="bg-gray-100 py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold font-display text-gray-900 mb-4">
              Shop by Scale
            </h2>
            <p className="text-lg text-gray-600">
              Find the perfect scale for your collection
            </p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            {['1:64', '1:43', '1:24', '1:18'].map((scale) => (
              <Link
                key={scale}
                to={`/products?scale=${scale}`}
                className="bg-white rounded-lg p-6 text-center hover:shadow-lg transition-shadow"
              >
                <div className="text-2xl font-bold text-primary-600 mb-2">{scale}</div>
                <div className="text-gray-600">Scale Models</div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="bg-primary-600 text-white py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold font-display mb-4">
            Ready to Start Collecting?
          </h2>
          <p className="text-xl text-primary-100 mb-8 max-w-2xl mx-auto">
            Join thousands of collectors who trust Diecast Empire for their scale model needs. 
            Sign up today and get exclusive access to new releases.
          </p>
          <Link
            to="/register"
            className="inline-flex items-center px-8 py-3 bg-white text-primary-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors"
          >
            Create Account
            <ArrowRightIcon className="ml-2 h-5 w-5" />
          </Link>
        </div>
      </section>
    </div>
  );
};

export default HomePage;