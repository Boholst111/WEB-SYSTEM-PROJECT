import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/Card';
import { Badge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Select } from '../ui/Select';
import { Modal } from '../ui/Modal';
import { Textarea } from '../ui/Textarea';
import { Clock, Package, Calendar, CheckCircle, AlertCircle } from 'lucide-react';
import { inventoryApi } from '../../services/inventoryApi';
import { PreOrder } from '../../types/inventory';

const PreOrderArrivals: React.FC = () => {
  const [preorders, setPreorders] = useState<PreOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedPreOrder, setSelectedPreOrder] = useState<PreOrder | null>(null);
  const [showArrivalModal, setShowArrivalModal] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  
  const [filters, setFilters] = useState({
    arrival_status: '',
    estimated_from: '',
    estimated_to: ''
  });

  const [arrivalForm, setArrivalForm] = useState({
    actual_arrival_date: '',
    notes: ''
  });

  useEffect(() => {
    loadPreOrderArrivals();
  }, [filters, currentPage]);

  const loadPreOrderArrivals = async () => {
    try {
      setLoading(true);
      const response = await inventoryApi.getPreOrderArrivals({
        ...filters,
        page: currentPage,
        per_page: 20
      });
      
      if (response.success) {
        setPreorders(response.data.data);
        setTotalPages(response.data.last_page);
      } else {
        setError('Failed to load pre-order arrivals');
      }
    } catch (err) {
      setError('Error loading pre-order arrivals');
      console.error('Pre-order arrivals loading error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key: string, value: string) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setCurrentPage(1);
  };

  const handleMarkArrived = (preorder: PreOrder) => {
    setSelectedPreOrder(preorder);
    setArrivalForm({
      actual_arrival_date: new Date().toISOString().split('T')[0],
      notes: ''
    });
    setShowArrivalModal(true);
  };

  const handleArrivalSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!selectedPreOrder || !arrivalForm.actual_arrival_date) {
      return;
    }

    try {
      const response = await inventoryApi.updatePreOrderArrival(selectedPreOrder.id, {
        actual_arrival_date: arrivalForm.actual_arrival_date,
        notes: arrivalForm.notes
      });

      if (response.success) {
        setShowArrivalModal(false);
        setSelectedPreOrder(null);
        loadPreOrderArrivals();
      } else {
        setError(response.message || 'Failed to update arrival');
      }
    } catch (err) {
      setError('Error updating arrival');
      console.error('Arrival update error:', err);
    }
  };

  const getArrivalStatusBadge = (preorder: PreOrder) => {
    if (preorder.actual_arrival_date) {
      return <Badge variant="success">Arrived</Badge>;
    }
    
    if (preorder.estimated_arrival_date) {
      const estimatedDate = new Date(preorder.estimated_arrival_date);
      const today = new Date();
      
      if (estimatedDate < today) {
        return <Badge variant="destructive">Overdue</Badge>;
      } else if (estimatedDate <= new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000)) {
        return <Badge variant="warning">Due Soon</Badge>;
      }
    }
    
    return <Badge variant="secondary">Pending</Badge>;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
  };

  const getDaysOverdue = (estimatedDate: string) => {
    const estimated = new Date(estimatedDate);
    const today = new Date();
    const diffTime = today.getTime() - estimated.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays > 0 ? diffDays : 0;
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <Clock className="h-6 w-6 text-blue-500" />
          <h2 className="text-xl font-semibold">Pre-order Arrivals</h2>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Total Pre-orders</p>
                <p className="text-2xl font-bold">{preorders.length}</p>
              </div>
              <Package className="h-8 w-8 text-blue-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Arrived</p>
                <p className="text-2xl font-bold text-green-600">
                  {preorders.filter(p => p.actual_arrival_date).length}
                </p>
              </div>
              <CheckCircle className="h-8 w-8 text-green-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Overdue</p>
                <p className="text-2xl font-bold text-red-600">
                  {preorders.filter(p => 
                    !p.actual_arrival_date && 
                    p.estimated_arrival_date && 
                    new Date(p.estimated_arrival_date) < new Date()
                  ).length}
                </p>
              </div>
              <AlertCircle className="h-8 w-8 text-red-500" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Due This Week</p>
                <p className="text-2xl font-bold text-yellow-600">
                  {preorders.filter(p => {
                    if (!p.estimated_arrival_date || p.actual_arrival_date) return false;
                    const estimated = new Date(p.estimated_arrival_date);
                    const weekFromNow = new Date();
                    weekFromNow.setDate(weekFromNow.getDate() + 7);
                    return estimated <= weekFromNow && estimated >= new Date();
                  }).length}
                </p>
              </div>
              <Calendar className="h-8 w-8 text-yellow-500" />
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
              value={filters.arrival_status}
              onValueChange={(value) => handleFilterChange('arrival_status', value)}
            >
              <option value="">All Status</option>
              <option value="pending">Pending</option>
              <option value="arrived">Arrived</option>
              <option value="overdue">Overdue</option>
            </Select>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Estimated From
              </label>
              <Input
                type="date"
                value={filters.estimated_from}
                onChange={(e) => handleFilterChange('estimated_from', e.target.value)}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Estimated To
              </label>
              <Input
                type="date"
                value={filters.estimated_to}
                onChange={(e) => handleFilterChange('estimated_to', e.target.value)}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Pre-orders Table */}
      <Card>
        <CardHeader>
          <CardTitle>Pre-order Arrivals</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex justify-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
          ) : error ? (
            <div className="text-center py-8 text-red-600">{error}</div>
          ) : preorders.length === 0 ? (
            <div className="text-center py-8 text-gray-500">
              <Clock className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <p>No pre-orders found</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left p-2">Pre-order</th>
                    <th className="text-left p-2">Product</th>
                    <th className="text-left p-2">Customer</th>
                    <th className="text-left p-2">Estimated Arrival</th>
                    <th className="text-left p-2">Actual Arrival</th>
                    <th className="text-left p-2">Status</th>
                    <th className="text-left p-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {preorders.map((preorder) => (
                    <tr key={preorder.id} className="border-b hover:bg-gray-50">
                      <td className="p-2">
                        <div>
                          <p className="font-medium">{preorder.preorder_number}</p>
                          <p className="text-sm text-gray-500">Qty: {preorder.quantity}</p>
                        </div>
                      </td>
                      <td className="p-2">
                        <div className="flex items-center space-x-3">
                          {preorder.product?.main_image && (
                            <img
                              src={preorder.product.main_image}
                              alt={preorder.product.name}
                              className="w-10 h-10 object-cover rounded"
                            />
                          )}
                          <div>
                            <p className="font-medium">{preorder.product?.name}</p>
                            <p className="text-sm text-gray-500">{preorder.product?.brand?.name}</p>
                          </div>
                        </div>
                      </td>
                      <td className="p-2">
                        <div>
                          <p className="font-medium">{preorder.user?.first_name} {preorder.user?.last_name}</p>
                          <p className="text-sm text-gray-500">{preorder.user?.email}</p>
                        </div>
                      </td>
                      <td className="p-2">
                        {preorder.estimated_arrival_date ? (
                          <div>
                            <p>{formatDate(preorder.estimated_arrival_date)}</p>
                            {!preorder.actual_arrival_date && getDaysOverdue(preorder.estimated_arrival_date) > 0 && (
                              <p className="text-xs text-red-600">
                                {getDaysOverdue(preorder.estimated_arrival_date)} days overdue
                              </p>
                            )}
                          </div>
                        ) : (
                          <span className="text-gray-400">Not set</span>
                        )}
                      </td>
                      <td className="p-2">
                        {preorder.actual_arrival_date ? (
                          <p className="text-green-600">{formatDate(preorder.actual_arrival_date)}</p>
                        ) : (
                          <span className="text-gray-400">Pending</span>
                        )}
                      </td>
                      <td className="p-2">{getArrivalStatusBadge(preorder)}</td>
                      <td className="p-2">
                        {!preorder.actual_arrival_date && (
                          <Button
                            size="sm"
                            onClick={() => handleMarkArrived(preorder)}
                          >
                            Mark Arrived
                          </Button>
                        )}
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

      {/* Arrival Update Modal */}
      {showArrivalModal && selectedPreOrder && (
        <Modal isOpen onClose={() => setShowArrivalModal(false)} title="Mark Pre-order as Arrived">
          <form onSubmit={handleArrivalSubmit} className="space-y-4">
            {/* Pre-order Info */}
            <div className="bg-gray-50 p-4 rounded-lg">
              <div className="flex items-center space-x-3">
                {selectedPreOrder.product?.main_image && (
                  <img
                    src={selectedPreOrder.product.main_image}
                    alt={selectedPreOrder.product.name}
                    className="w-12 h-12 object-cover rounded"
                  />
                )}
                <div>
                  <h3 className="font-medium">{selectedPreOrder.product?.name}</h3>
                  <p className="text-sm text-gray-500">Pre-order: {selectedPreOrder.preorder_number}</p>
                  <p className="text-sm text-gray-500">Quantity: {selectedPreOrder.quantity}</p>
                </div>
              </div>
            </div>

            {/* Arrival Date */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Actual Arrival Date *
              </label>
              <Input
                type="date"
                value={arrivalForm.actual_arrival_date}
                onChange={(e) => setArrivalForm(prev => ({ ...prev, actual_arrival_date: e.target.value }))}
                required
              />
            </div>

            {/* Notes */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Notes
              </label>
              <Textarea
                value={arrivalForm.notes}
                onChange={(e) => setArrivalForm(prev => ({ ...prev, notes: e.target.value }))}
                placeholder="Optional notes about the arrival..."
                rows={3}
              />
            </div>

            {/* Actions */}
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={() => setShowArrivalModal(false)}
              >
                Cancel
              </Button>
              <Button type="submit">
                Mark as Arrived
              </Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
};

export default PreOrderArrivals;