import React, { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';

import Layout from './components/Layout';
import { ProtectedRoute, AuthRedirect } from './components/auth';

// Eager load critical pages
import HomePage from './pages/HomePage';
import ProductsPage from './pages/ProductsPage';
import ProductDetailPage from './pages/ProductDetailPage';

// Lazy load non-critical pages
const CartPage = lazy(() => import('./pages/CartPage'));
const CheckoutPage = lazy(() => import('./pages/CheckoutPage'));
const AccountPage = lazy(() => import('./pages/AccountPage'));
const LoginPage = lazy(() => import('./pages/LoginPage'));
const RegisterPage = lazy(() => import('./pages/RegisterPage'));
const ForgotPasswordPage = lazy(() => import('./pages/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('./pages/ResetPasswordPage'));
const EmailVerificationPage = lazy(() => import('./pages/EmailVerificationPage'));
const PreOrderPage = lazy(() => import('./pages/PreOrderPage'));
const PreOrderDetailPage = lazy(() => import('./pages/PreOrderDetailPage'));
const LoyaltyPage = lazy(() => import('./pages/LoyaltyPage'));
const PaymentPage = lazy(() => import('./pages/PaymentPage'));
const PaymentSuccessPage = lazy(() => import('./pages/PaymentSuccessPage'));
const PaymentFailedPage = lazy(() => import('./pages/PaymentFailedPage'));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'));

// Admin Pages - lazy loaded
const AdminDashboard = lazy(() => import('./pages/admin/AdminDashboard'));
const OrderManagementPage = lazy(() => import('./pages/admin/OrderManagementPage'));
const UserManagementPage = lazy(() => import('./pages/admin/UserManagementPage'));
const InventoryPage = lazy(() => import('./pages/admin/InventoryPage'));

// Loading fallback component
const PageLoader = () => (
  <div className="min-h-screen flex items-center justify-center">
    <div className="text-center">
      <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <p className="mt-4 text-gray-600">Loading...</p>
    </div>
  </div>
);

function App() {
  return (
    <div className="App">
      <Suspense fallback={<PageLoader />}>
        <Routes>
        <Route path="/" element={<Layout />}>
          <Route index element={<HomePage />} />
          <Route path="products" element={<ProductsPage />} />
          <Route path="products/:id" element={<ProductDetailPage />} />
          <Route path="cart" element={<CartPage />} />
          <Route path="checkout" element={
            <ProtectedRoute>
              <CheckoutPage />
            </ProtectedRoute>
          } />
          <Route path="account" element={
            <ProtectedRoute>
              <AccountPage />
            </ProtectedRoute>
          } />
          <Route path="loyalty" element={
            <ProtectedRoute>
              <LoyaltyPage />
            </ProtectedRoute>
          } />
          <Route path="preorders" element={
            <ProtectedRoute>
              <PreOrderPage />
            </ProtectedRoute>
          } />
          <Route path="preorders/:id" element={
            <ProtectedRoute>
              <PreOrderDetailPage />
            </ProtectedRoute>
          } />
          <Route path="payment/:type" element={
            <ProtectedRoute>
              <PaymentPage />
            </ProtectedRoute>
          } />
          <Route path="payment/success" element={<PaymentSuccessPage />} />
          <Route path="payment/failed" element={<PaymentFailedPage />} />
        </Route>
        
        {/* Authentication Routes */}
        <Route path="/login" element={
          <AuthRedirect>
            <LoginPage />
          </AuthRedirect>
        } />
        <Route path="/register" element={
          <AuthRedirect>
            <RegisterPage />
          </AuthRedirect>
        } />
        <Route path="/forgot-password" element={
          <AuthRedirect>
            <ForgotPasswordPage />
          </AuthRedirect>
        } />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/verify-email" element={<EmailVerificationPage />} />
        
        {/* Admin Routes */}
        <Route path="/admin" element={
          <ProtectedRoute>
            <AdminDashboard />
          </ProtectedRoute>
        } />
        <Route path="/admin/dashboard" element={
          <ProtectedRoute>
            <AdminDashboard />
          </ProtectedRoute>
        } />
        <Route path="/admin/orders" element={
          <ProtectedRoute>
            <OrderManagementPage />
          </ProtectedRoute>
        } />
        <Route path="/admin/users" element={
          <ProtectedRoute>
            <UserManagementPage />
          </ProtectedRoute>
        } />
        <Route path="/admin/inventory" element={
          <ProtectedRoute>
            <InventoryPage />
          </ProtectedRoute>
        } />
        
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
      </Suspense>
    </div>
  );
}

export default App;