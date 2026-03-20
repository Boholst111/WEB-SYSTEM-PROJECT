import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { AlertTriangle, Package, RefreshCw } from 'lucide-react';
import { inventoryApi } from '../../services/inventoryApi';
import { Product } from '../../types/inventory';
import StockUpdateModal from './StockUpdateModal';

const LowStockAlerts: React.FC = () => {
  const [lowStockProducts, setLowStockProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [threshold, setThreshold] = useState(5);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [showStockModal, setShowStockModal] = useState(false);

  useEffect(() => {
    loadLowStockProducts();
  }, [threshold]);

  const loadLowStockProducts = async () => {
    try {
      setLoading(true);
      const response = await inventoryApi.getLowStock({ threshold });
      
      if (response.success) {
        setLowStockProducts(response.data);
      } else {
        setError('Failed to load low stock products');
      }
    } catch (err) {
      setError('Error loading low stock products');
      console.error('Low stock loading error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleStockUpdate = (product: Product) => {
    setSelectedProduct(product);
    setShowStockModal(true);
  };

  const handleStockUpdateSuccess = () => {
    setShowStockModal(false);
    setSelectedProduct(null);
    loadLowStockProducts();
  };

  const getUrgencyLevel = (stock: number) => {
    if (stock === 0) return 'critical';
    if (stock <= 2) return 'high';
    if (stock <= threshold / 2) return 'medium';
    return 'low';
  };

  const getUrgencyBadge = (stock: number) => {
    const urgency = getUrgencyLevel(stock);
    
    switch (urgency) {
      case 'critical':
        return <Badge variant="destructive">Critical</Badge>;
      case 'high':
        return <Badge variant="destructive">High</Badge>;
      case 'medium':
        return <Badge variant="warning">Medium</Badge>;
      default:
        return <Badge variant="secondary">Low</Badge>;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <AlertTriangle className="h-6 w-6 text-yellow-500" />
          <h2 className="text-xl font-semibold">Low Stock Alerts</h2>
        </div>
        <div className="flex items-center space-x-3">
          <div className="flex items-center space-x-2">
            <label className="text-sm font-medium">Threshold:</label>
            <Input
              type="number"
              min="1"
              max="50"
              value={threshold}
              onChange={(e) => setThreshold(parseInt(e.target.value) || 5)}
              className="w-20"
            />
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={loadLowStockProducts}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Alerts</p>
                <p className="text-2xl font-bold">{lowStockProducts.length}</p>
              </div>
              <AlertTriangle className="h-8 w-8 text-yellow-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Critical (0 stock)</p>
                <p className="text-2xl font-bold text-red-600">
                  {lowStockProducts.filter(p => p.stock_quantity === 0).length}
                </p>
              </div>
              <Package className="h-8 w-8 text-red-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">High Priority (≤2)</p>
                <p className="text-2xl font-bold text-orange-600">
                  {lowStockProducts.filter(p => p.stock_quantity > 0 && p.stock_quantity <= 2).length}
                </p>
              </div>
              <Package className="h-8 w-8 text-orange-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Medium Priority</p>
                <p className="text-2xl font-bold text-yellow-600">
                  {lowStockProducts.filter(p => p.stock_quantity > 2 && p.stock_quantity <= threshold).length}
                </p>
              </div>
              <Package className="h-8 w-8 text-yellow-500" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Low Stock Products */}
      <Card>
        <CardHeader>
          <CardTitle>Low Stock Products</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex justify-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
          ) : error ? (
            <div className="text-center py-8 text-red-600">{error}</div>
          ) : lowStockProducts.length === 0 ? (
            <div className="text-center py-8 text-gray-500">
              <Package className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <p>No low stock alerts at this threshold</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left p-2">Product</th>
                    <th className="text-left p-2">SKU</th>
                    <th className="text-left p-2">Current Stock</th>
                    <th className="text-left p-2">Urgency</th>
                    <th className="text-left p-2">Last Movement</th>
                    <th className="text-left p-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {lowStockProducts
                    .sort((a, b) => a.stock_quantity - b.stock_quantity)
                    .map((product) => (
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
                        <span className={`font-bold text-lg ${
                          product.stock_quantity === 0 ? 'text-red-600' :
                          product.stock_quantity <= 2 ? 'text-orange-600' :
                          'text-yellow-600'
                        }`}>
                          {product.stock_quantity}
                        </span>
                      </td>
                      <td className="p-2">{getUrgencyBadge(product.stock_quantity)}</td>
                      <td className="p-2 text-sm text-gray-500">
                        {product.inventory_movements && product.inventory_movements.length > 0 ? (
                          <div>
                            <p>{product.inventory_movements[0].movement_type}</p>
                            <p>{new Date(product.inventory_movements[0].created_at).toLocaleDateString()}</p>
                          </div>
                        ) : (
                          'No recent activity'
                        )}
                      </td>
                      <td className="p-2">
                        <div className="flex space-x-2">
                          <Button
                            size="sm"
                            onClick={() => handleStockUpdate(product)}
                          >
                            Restock
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              // TODO: Navigate to product edit page
                              console.log('Edit product:', product.id);
                            }}
                          >
                            Edit
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

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

export default LowStockAlerts;