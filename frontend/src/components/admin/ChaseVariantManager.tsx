import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Select } from '../ui/Select';
import { Modal } from '../ui/Modal';
import { Input } from '../ui/Input';
import { Textarea } from '../ui/Textarea';
import { Star, Package, DollarSign, TrendingUp, ShoppingCart } from 'lucide-react';
import { inventoryApi } from '../../services/inventoryApi';
import { Product } from '../../types/inventory';
import StockUpdateModal from './StockUpdateModal';

const ChaseVariantManager: React.FC = () => {
  const [chaseVariants, setChaseVariants] = useState<Product[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [showStockModal, setShowStockModal] = useState(false);
  const [showPurchaseOrderModal, setShowPurchaseOrderModal] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  
  const [filters, setFilters] = useState({
    availability: ''
  });

  const [purchaseOrderForm, setPurchaseOrderForm] = useState({
    supplier_name: '',
    supplier_email: '',
    expected_delivery_date: '',
    notes: '',
    products: [] as Array<{
      product_id: number;
      quantity: number;
      unit_cost: number;
    }>
  });

  useEffect(() => {
    loadChaseVariants();
  }, [filters, currentPage]);

  const loadChaseVariants = async () => {
    try {
      setLoading(true);
      const response = await inventoryApi.getChaseVariants({
        ...filters,
        page: currentPage,
        per_page: 15
      });
      
      if (response.success) {
        setChaseVariants(response.data.data);
        setSummary(response.data.summary);
        setTotalPages(response.data.last_page);
      } else {
        setError('Failed to load chase variants');
      }
    } catch (err) {
      setError('Error loading chase variants');
      console.error('Chase variants loading error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key: string, value: string) => {
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
    loadChaseVariants();
  };

  const handleCreatePurchaseOrder = (product?: Product) => {
    if (product) {
      setPurchaseOrderForm(prev => ({
        ...prev,
        products: [{
          product_id: product.id,
          quantity: 1,
          unit_cost: product.current_price * 0.7 // Assume 30% markup
        }]
      }));
    }
    setShowPurchaseOrderModal(true);
  };

  const handlePurchaseOrderSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const response = await inventoryApi.createPurchaseOrder(purchaseOrderForm);
      
      if (response.success) {
        setShowPurchaseOrderModal(false);
        setPurchaseOrderForm({
          supplier_name: '',
          supplier_email: '',
          expected_delivery_date: '',
          notes: '',
          products: []
        });
        // Show success message
        alert('Purchase order created successfully!');
      } else {
        setError(response.message || 'Failed to create purchase order');
      }
    } catch (err) {
      setError('Error creating purchase order');
      console.error('Purchase order error:', err);
    }
  };

  const getAvailabilityBadge = (product: Product) => {
    if (product.stock_quantity === 0) {
      return <Badge variant="destructive">Sold Out</Badge>;
    }
    
    if (product.stock_quantity <= 2) {
      return <Badge variant="warning">Very Limited</Badge>;
    }
    
    if (product.stock_quantity <= 5) {
      return <Badge variant="secondary">Limited</Badge>;
    }
    
    return <Badge variant="success">Available</Badge>;
  };

  const getRarityLevel = (product: Product) => {
    // This could be based on various factors like price, stock history, etc.
    if (product.current_price > 5000) return 'Ultra Rare';
    if (product.current_price > 2000) return 'Super Rare';
    if (product.current_price > 1000) return 'Rare';
    return 'Limited';
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <Star className="h-6 w-6 text-yellow-500" />
          <h2 className="text-xl font-semibold">Chase Variant Manager</h2>
        </div>
        <Button onClick={() => handleCreatePurchaseOrder()}>
          Create Purchase Order
        </Button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Chase Variants</p>
                <p className="text-2xl font-bold">{summary?.total_chase_variants || 0}</p>
              </div>
              <Star className="h-8 w-8 text-yellow-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Available</p>
                <p className="text-2xl font-bold text-green-600">{summary?.available || 0}</p>
              </div>
              <Package className="h-8 w-8 text-green-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Sold Out</p>
                <p className="text-2xl font-bold text-red-600">{summary?.sold_out || 0}</p>
              </div>
              <TrendingUp className="h-8 w-8 text-red-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Avg. Price</p>
                <p className="text-2xl font-bold">₱{summary?.average_price?.toFixed(0) || 0}</p>
              </div>
              <DollarSign className="h-8 w-8 text-blue-500" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Select
              value={filters.availability}
              onValueChange={(value) => handleFilterChange('availability', value)}
            >
              <option value="">All Availability</option>
              <option value="available">Available</option>
              <option value="reserved">Reserved</option>
              <option value="sold_out">Sold Out</option>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Chase Variants Grid */}
      <Card>
        <CardHeader>
          <CardTitle>Chase Variants</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex justify-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
          ) : error ? (
            <div className="text-center py-8 text-red-600">{error}</div>
          ) : chaseVariants.length === 0 ? (
            <div className="text-center py-8 text-gray-500">
              <Star className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <p>No chase variants found</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {chaseVariants.map((product) => (
                <Card key={product.id} className="overflow-hidden">
                  <div className="relative">
                    {product.main_image ? (
                      <img
                        src={product.main_image}
                        alt={product.name}
                        className="w-full h-48 object-cover"
                      />
                    ) : (
                      <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
                        <Package className="h-12 w-12 text-gray-400" />
                      </div>
                    )}
                    <div className="absolute top-2 right-2">
                      <Badge variant="secondary" className="bg-yellow-100 text-yellow-800">
                        <Star className="h-3 w-3 mr-1" />
                        Chase
                      </Badge>
                    </div>
                    <div className="absolute top-2 left-2">
                      <Badge variant="outline" className="bg-white">
                        {getRarityLevel(product)}
                      </Badge>
                    </div>
                  </div>
                  
                  <CardContent className="p-4">
                    <div className="space-y-3">
                      <div>
                        <h3 className="font-semibold text-lg">{product.name}</h3>
                        <p className="text-sm text-gray-500">{product.brand?.name}</p>
                        <p className="text-xs text-gray-400 font-mono">{product.sku}</p>
                      </div>

                      <div className="flex items-center justify-between">
                        <div>
                          <p className="text-2xl font-bold text-green-600">
                            ₱{product.current_price.toFixed(2)}
                          </p>
                          {product.base_price > product.current_price && (
                            <p className="text-sm text-gray-500 line-through">
                              ₱{product.base_price.toFixed(2)}
                            </p>
                          )}
                        </div>
                        {getAvailabilityBadge(product)}
                      </div>

                      <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-600">Stock:</span>
                        <span className={`font-medium ${
                          product.stock_quantity === 0 ? 'text-red-600' :
                          product.stock_quantity <= 2 ? 'text-yellow-600' :
                          'text-green-600'
                        }`}>
                          {product.stock_quantity} units
                        </span>
                      </div>

                      {product.inventory_movements && product.inventory_movements.length > 0 && (
                        <div className="text-xs text-gray-500">
                          Last activity: {product.inventory_movements[0].movement_type} on{' '}
                          {new Date(product.inventory_movements[0].created_at).toLocaleDateString()}
                        </div>
                      )}

                      <div className="flex space-x-2 pt-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleStockUpdate(product)}
                          className="flex-1"
                        >
                          Update Stock
                        </Button>
                        <Button
                          size="sm"
                          onClick={() => handleCreatePurchaseOrder(product)}
                          className="flex-1"
                        >
                          <ShoppingCart className="h-4 w-4 mr-1" />
                          Order
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex justify-center mt-6 space-x-2">
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

      {/* Stock Update Modal */}
      {showStockModal && selectedProduct && (
        <StockUpdateModal
          product={selectedProduct}
          onClose={() => setShowStockModal(false)}
          onSuccess={handleStockUpdateSuccess}
        />
      )}

      {/* Purchase Order Modal */}
      {showPurchaseOrderModal && (
        <Modal isOpen onClose={() => setShowPurchaseOrderModal(false)} title="Create Purchase Order">
          <form onSubmit={handlePurchaseOrderSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Supplier Name *
                </label>
                <Input
                  value={purchaseOrderForm.supplier_name}
                  onChange={(e) => setPurchaseOrderForm(prev => ({ ...prev, supplier_name: e.target.value }))}
                  placeholder="Enter supplier name"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Supplier Email *
                </label>
                <Input
                  type="email"
                  value={purchaseOrderForm.supplier_email}
                  onChange={(e) => setPurchaseOrderForm(prev => ({ ...prev, supplier_email: e.target.value }))}
                  placeholder="supplier@example.com"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Expected Delivery Date *
              </label>
              <Input
                type="date"
                value={purchaseOrderForm.expected_delivery_date}
                onChange={(e) => setPurchaseOrderForm(prev => ({ ...prev, expected_delivery_date: e.target.value }))}
                required
              />
            </div>

            {/* Product Selection */}
            {purchaseOrderForm.products.map((item, index) => (
              <div key={index} className="border rounded-lg p-4 space-y-3">
                <h4 className="font-medium">Product {index + 1}</h4>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Quantity *
                    </label>
                    <Input
                      type="number"
                      min="1"
                      value={item.quantity}
                      onChange={(e) => {
                        const newProducts = [...purchaseOrderForm.products];
                        newProducts[index].quantity = parseInt(e.target.value) || 1;
                        setPurchaseOrderForm(prev => ({ ...prev, products: newProducts }));
                      }}
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Unit Cost *
                    </label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      value={item.unit_cost}
                      onChange={(e) => {
                        const newProducts = [...purchaseOrderForm.products];
                        newProducts[index].unit_cost = parseFloat(e.target.value) || 0;
                        setPurchaseOrderForm(prev => ({ ...prev, products: newProducts }));
                      }}
                      required
                    />
                  </div>
                  <div className="flex items-end">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        const newProducts = purchaseOrderForm.products.filter((_, i) => i !== index);
                        setPurchaseOrderForm(prev => ({ ...prev, products: newProducts }));
                      }}
                    >
                      Remove
                    </Button>
                  </div>
                </div>
              </div>
            ))}

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Notes
              </label>
              <Textarea
                value={purchaseOrderForm.notes}
                onChange={(e) => setPurchaseOrderForm(prev => ({ ...prev, notes: e.target.value }))}
                placeholder="Additional notes for the purchase order..."
                rows={3}
              />
            </div>

            <div className="flex justify-end space-x-3 pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={() => setShowPurchaseOrderModal(false)}
              >
                Cancel
              </Button>
              <Button type="submit">
                Create Purchase Order
              </Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
};

export default ChaseVariantManager;