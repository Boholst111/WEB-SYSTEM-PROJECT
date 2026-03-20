import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Select } from '../ui/Select';
import { AlertTriangle, Package, TrendingDown, Clock, Search, Filter } from 'lucide-react';
import { inventoryApi } from '../../services/inventoryApi';
import { Product, InventorySummary } from '../../types/inventory';
import StockUpdateModal from './StockUpdateModal';
import LowStockAlerts from './LowStockAlerts';
import PreOrderArrivals from './PreOrderArrivals';
import ChaseVariantManager from './ChaseVariantManager';

interface InventoryDashboardProps {
  className?: string;
}

const InventoryDashboard: React.FC<InventoryDashboardProps> = ({ className }) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [summary, setSummary] = useState<InventorySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [showStockModal, setShowStockModal] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'low-stock' | 'preorders' | 'chase-variants'>('overview');
  
  // Filters
  const [filters, setFilters] = useState({
    search: '',
    stock_status: '',
    category_id: '',
    brand_id: '',
    chase_variants: false,
    sort_by: 'name',
    sort_order: 'asc'
  });

  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  useEffect(() => {
    loadInventoryData();
  }, [filters, currentPage]);

  const loadInventoryData = async () => {
    try {
      setLoading(true);
      const response = await inventoryApi.getInventory({
        ...filters,
        page: currentPage,
        per_page: 20
      });
      
      if (response.success) {
        setProducts(response.data.data);
        setSummary(response.data.summary);
        setTotalPages(response.data.last_page);
      } else {
        setError('Failed to load inventory data');
      }
    } catch (err) {
      setError('Error loading inventory data');
      console.error('Inventory loading error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key: string, value: any) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setCurrentPage(1);
  };

  const handleStockUpdate = (product: Product) => {
    setSelectedProduct(product);
    setShowStockModal(true);
  };

  const handleStockUpdateSuccess = () => {
    setShowStockModal(false);
    setSelectedProduct(null);
    loadInventoryData();
  };

  const getStockStatusBadge = (product: Product) => {
    if (product.is_preorder) {
      return <Badge variant="info">Pre-order</Badge>;
    }
    
    if (product.stock_quantity === 0) {
      return <Badge variant="destructive">Out of Stock</Badge>;
    }
    
    if (product.stock_quantity <= 5) {
      return <Badge variant="warning">Low Stock</Badge>;
    }
    
    return <Badge variant="success">In Stock</Badge>;
  };

  const renderOverviewTab = () => (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Products</p>
                <p className="text-2xl font-bold">{summary?.total_products || 0}</p>
              </div>
              <Package className="h-8 w-8 text-blue-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">In Stock</p>
                <p className="text-2xl font-bold text-green-600">{summary?.in_stock || 0}</p>
              </div>
              <Package className="h-8 w-8 text-green-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Low Stock</p>
                <p className="text-2xl font-bold text-yellow-600">{summary?.low_stock || 0}</p>
              </div>
              <AlertTriangle className="h-8 w-8 text-yellow-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Out of Stock</p>
                <p className="text-2xl font-bold text-red-600">{summary?.out_of_stock || 0}</p>
              </div>
              <TrendingDown className="h-8 w-8 text-red-500" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Filter className="h-5 w-5" />
            Filters
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Search products..."
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                className="pl-10"
              />
            </div>

            <Select
              value={filters.stock_status}
              onValueChange={(value) => handleFilterChange('stock_status', value)}
            >
              <option value="">All Stock Status</option>
              <option value="in_stock">In Stock</option>
              <option value="low_stock">Low Stock</option>
              <option value="out_of_stock">Out of Stock</option>
              <option value="preorder">Pre-order</option>
            </Select>

            <Select
              value={filters.sort_by}
              onValueChange={(value) => handleFilterChange('sort_by', value)}
            >
              <option value="name">Sort by Name</option>
              <option value="sku">Sort by SKU</option>
              <option value="stock_quantity">Sort by Stock</option>
              <option value="current_price">Sort by Price</option>
              <option value="created_at">Sort by Date</option>
            </Select>

            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="chase_variants"
                checked={filters.chase_variants}
                onChange={(e) => handleFilterChange('chase_variants', e.target.checked)}
                className="rounded border-gray-300"
              />
              <label htmlFor="chase_variants" className="text-sm">Chase Variants Only</label>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Products Table */}
      <Card>
        <CardHeader>
          <CardTitle>Products</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex justify-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
          ) : error ? (
            <div className="text-center py-8 text-red-600">{error}</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left p-2">Product</th>
                    <th className="text-left p-2">SKU</th>
                    <th className="text-left p-2">Stock</th>
                    <th className="text-left p-2">Status</th>
                    <th className="text-left p-2">Price</th>
                    <th className="text-left p-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {products.map((product) => (
                    <tr key={product.id} className="border-b hover:bg-gray-50">
                      <td className="p-2">
                        <div className="flex items-center space-x-3">
                          {product.main_image && (
                            <img
                              src={product.main_image}
                              alt={product.name}
                              className="w-10 h-10 object-cover rounded"
                            />
                          )}
                          <div>
                            <p className="font-medium">{product.name}</p>
                            <p className="text-sm text-gray-500">{product.brand?.name}</p>
                            {product.is_chase_variant && (
                              <Badge variant="secondary" className="text-xs">Chase</Badge>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="p-2 font-mono text-sm">{product.sku}</td>
                      <td className="p-2">
                        <span className={`font-medium ${
                          product.stock_quantity === 0 ? 'text-red-600' :
                          product.stock_quantity <= 5 ? 'text-yellow-600' :
                          'text-green-600'
                        }`}>
                          {product.is_preorder ? 'Pre-order' : product.stock_quantity}
                        </span>
                      </td>
                      <td className="p-2">{getStockStatusBadge(product)}</td>
                      <td className="p-2 font-medium">₱{product.current_price.toFixed(2)}</td>
                      <td className="p-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleStockUpdate(product)}
                          disabled={product.is_preorder}
                        >
                          Update Stock
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex justify-center mt-4 space-x-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                disabled={currentPage === 1}
              >
                Previous
              </Button>
              <span className="flex items-center px-3 text-sm">
                Page {currentPage} of {totalPages}
              </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                disabled={currentPage === totalPages}
              >
                Next
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Tab Navigation */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {[
            { key: 'overview', label: 'Overview', icon: Package },
            { key: 'low-stock', label: 'Low Stock Alerts', icon: AlertTriangle },
            { key: 'preorders', label: 'Pre-order Arrivals', icon: Clock },
            { key: 'chase-variants', label: 'Chase Variants', icon: TrendingDown },
          ].map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              onClick={() => setActiveTab(key as any)}
              className={`flex items-center gap-2 py-2 px-1 border-b-2 font-medium text-sm ${
                activeTab === key
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <Icon className="h-4 w-4" />
              {label}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      {activeTab === 'overview' && renderOverviewTab()}
      {activeTab === 'low-stock' && <LowStockAlerts />}
      {activeTab === 'preorders' && <PreOrderArrivals />}
      {activeTab === 'chase-variants' && <ChaseVariantManager />}

      {/* Stock Update Modal */}
      {showStockModal && selectedProduct && (
        <StockUpdateModal
          product={selectedProduct}
          onClose={() => setShowStockModal(false)}
          onSuccess={handleStockUpdateSuccess}
        />
      )}
    </div>
  );
};

export default InventoryDashboard;