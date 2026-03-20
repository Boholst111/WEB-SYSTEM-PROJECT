import React, { useEffect, useState } from 'react';
import { useAppSelector } from '../store';
import { preorderApi, PreOrderNotification } from '../services/preorderApi';
import { 
  BellIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon,
  EyeIcon,
  EyeSlashIcon
} from '@heroicons/react/24/outline';

interface PreOrderNotificationsProps {
  preorderId?: number; // If provided, show notifications for specific pre-order
  showUnreadOnly?: boolean;
  maxItems?: number;
  onNotificationClick?: (notification: PreOrderNotification) => void;
}

const PreOrderNotifications: React.FC<PreOrderNotificationsProps> = ({
  preorderId,
  showUnreadOnly = false,
  maxItems,
  onNotificationClick
}) => {
  const { preorders } = useAppSelector(state => state.preorders);
  const [notifications, setNotifications] = useState<PreOrderNotification[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<'all' | 'unread' | 'arrival' | 'payment_reminder'>('all');

  useEffect(() => {
    loadNotifications();
  }, [preorderId, preorders]);

  const loadNotifications = async () => {
    if (preorderId) {
      // Load notifications for specific pre-order
      await loadPreOrderNotifications(preorderId);
    } else {
      // Load notifications for all user's pre-orders
      await loadAllNotifications();
    }
  };

  const loadPreOrderNotifications = async (id: number) => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await preorderApi.getPreOrderNotifications(id);
      setNotifications(response.data);
    } catch (err: any) {
      setError(err.message || 'Failed to load notifications');
    } finally {
      setLoading(false);
    }
  };

  const loadAllNotifications = async () => {
    setLoading(true);
    setError(null);
    
    try {
      // Load notifications for all pre-orders
      const allNotifications: PreOrderNotification[] = [];
      
      for (const preorder of preorders) {
        try {
          const response = await preorderApi.getPreOrderNotifications(preorder.id);
          const notificationsWithPreOrder = response.data.map(notification => ({
            ...notification,
            preorderInfo: {
              id: preorder.id,
              productName: preorder.product.name,
              productImage: preorder.product.images[0]
            }
          }));
          allNotifications.push(...notificationsWithPreOrder);
        } catch (err) {
          // Continue loading other notifications even if one fails
          console.error(`Failed to load notifications for pre-order ${preorder.id}:`, err);
        }
      }
      
      // Sort by creation date (newest first)
      allNotifications.sort((a, b) => 
        new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
      );
      
      setNotifications(allNotifications);
    } catch (err: any) {
      setError(err.message || 'Failed to load notifications');
    } finally {
      setLoading(false);
    }
  };

  const getFilteredNotifications = () => {
    let filtered = notifications;

    if (showUnreadOnly) {
      filtered = filtered.filter(n => !n.isRead);
    }

    if (filter !== 'all') {
      if (filter === 'unread') {
        filtered = filtered.filter(n => !n.isRead);
      } else {
        filtered = filtered.filter(n => n.type === filter);
      }
    }

    if (maxItems) {
      filtered = filtered.slice(0, maxItems);
    }

    return filtered;
  };

  const getNotificationIcon = (type: PreOrderNotification['type']) => {
    switch (type) {
      case 'arrival':
        return <CheckCircleIcon className="w-5 h-5 text-green-600" />;
      case 'payment_reminder':
        return <ExclamationTriangleIcon className="w-5 h-5 text-yellow-600" />;
      case 'status_update':
        return <InformationCircleIcon className="w-5 h-5 text-blue-600" />;
      default:
        return <BellIcon className="w-5 h-5 text-gray-600" />;
    }
  };

  const getNotificationBgColor = (type: PreOrderNotification['type'], isRead: boolean) => {
    if (isRead) return 'bg-white';
    
    switch (type) {
      case 'arrival':
        return 'bg-green-50';
      case 'payment_reminder':
        return 'bg-yellow-50';
      case 'status_update':
        return 'bg-blue-50';
      default:
        return 'bg-gray-50';
    }
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    const diffHours = Math.floor(diffTime / (1000 * 60 * 60));
    const diffMinutes = Math.floor(diffTime / (1000 * 60));

    if (diffMinutes < 1) return 'Just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    
    return date.toLocaleDateString('en-PH', {
      month: 'short',
      day: 'numeric',
      year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
  };

  const handleNotificationClick = (notification: PreOrderNotification) => {
    if (onNotificationClick) {
      onNotificationClick(notification);
    }
  };

  const markAsRead = async (notificationId: number) => {
    // This would typically call an API to mark the notification as read
    setNotifications(prev => 
      prev.map(n => 
        n.id === notificationId ? { ...n, isRead: true } : n
      )
    );
  };

  const markAllAsRead = async () => {
    // This would typically call an API to mark all notifications as read
    setNotifications(prev => 
      prev.map(n => ({ ...n, isRead: true }))
    );
  };

  const filteredNotifications = getFilteredNotifications();
  const unreadCount = notifications.filter(n => !n.isRead).length;

  if (loading && notifications.length === 0) {
    return (
      <div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-4 bg-gray-300 rounded w-1/4"></div>
          {[...Array(3)].map((_, i) => (
            <div key={i} className="flex space-x-4">
              <div className="w-10 h-10 bg-gray-300 rounded-full"></div>
              <div className="flex-1 space-y-2">
                <div className="h-4 bg-gray-300 rounded w-3/4"></div>
                <div className="h-3 bg-gray-300 rounded w-1/2"></div>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-md border border-gray-200">
      {/* Header */}
      <div className="px-6 py-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center">
            <BellIcon className="w-5 h-5 text-gray-600 mr-2" />
            <h3 className="text-lg font-semibold text-gray-900">
              {preorderId ? 'Pre-Order Notifications' : 'All Notifications'}
            </h3>
            {unreadCount > 0 && (
              <span className="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1">
                {unreadCount}
              </span>
            )}
          </div>
          
          {!preorderId && (
            <div className="flex items-center space-x-2">
              {/* Filter Dropdown */}
              <select
                value={filter}
                onChange={(e) => setFilter(e.target.value as any)}
                className="text-sm border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option value="all">All</option>
                <option value="unread">Unread</option>
                <option value="arrival">Arrivals</option>
                <option value="payment_reminder">Payment Reminders</option>
              </select>
              
              {unreadCount > 0 && (
                <button
                  onClick={markAllAsRead}
                  className="text-sm text-primary-600 hover:text-primary-700 font-medium"
                >
                  Mark all read
                </button>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="max-h-96 overflow-y-auto">
        {error ? (
          <div className="p-6 text-center">
            <ExclamationTriangleIcon className="mx-auto h-12 w-12 text-red-400 mb-4" />
            <h3 className="text-sm font-medium text-red-800 mb-2">Error loading notifications</h3>
            <p className="text-sm text-red-600 mb-4">{error}</p>
            <button
              onClick={loadNotifications}
              className="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200"
            >
              Try Again
            </button>
          </div>
        ) : filteredNotifications.length === 0 ? (
          <div className="p-6 text-center">
            <BellIcon className="mx-auto h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-sm font-medium text-gray-900 mb-2">No notifications</h3>
            <p className="text-sm text-gray-500">
              {showUnreadOnly || filter === 'unread' 
                ? 'All caught up! No unread notifications.'
                : 'You\'ll see notifications about your pre-orders here.'
              }
            </p>
          </div>
        ) : (
          <div className="divide-y divide-gray-200">
            {filteredNotifications.map((notification) => (
              <div
                key={notification.id}
                className={`p-4 hover:bg-gray-50 cursor-pointer transition-colors ${
                  getNotificationBgColor(notification.type, notification.isRead)
                }`}
                onClick={() => handleNotificationClick(notification)}
              >
                <div className="flex items-start">
                  <div className="flex-shrink-0 mr-3">
                    {getNotificationIcon(notification.type)}
                  </div>
                  
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <h4 className={`text-sm font-medium ${
                          notification.isRead ? 'text-gray-900' : 'text-gray-900 font-semibold'
                        }`}>
                          {notification.title}
                        </h4>
                        <p className={`text-sm mt-1 ${
                          notification.isRead ? 'text-gray-600' : 'text-gray-700'
                        }`}>
                          {notification.message}
                        </p>
                        
                        {/* Pre-order info for global notifications */}
                        {!preorderId && (notification as any).preorderInfo && (
                          <div className="flex items-center mt-2">
                            <img
                              src={(notification as any).preorderInfo.productImage || '/placeholder-product.jpg'}
                              alt={(notification as any).preorderInfo.productName}
                              className="w-6 h-6 object-cover rounded mr-2"
                            />
                            <span className="text-xs text-gray-500">
                              {(notification as any).preorderInfo.productName}
                            </span>
                          </div>
                        )}
                      </div>
                      
                      <div className="flex items-center ml-4">
                        <span className="text-xs text-gray-500">
                          {formatDateTime(notification.createdAt)}
                        </span>
                        {!notification.isRead && (
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              markAsRead(notification.id);
                            }}
                            className="ml-2 p-1 text-gray-400 hover:text-gray-600"
                            title="Mark as read"
                          >
                            <EyeIcon className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                  
                  {!notification.isRead && (
                    <div className="w-2 h-2 bg-blue-600 rounded-full ml-2 mt-2"></div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Footer */}
      {!preorderId && filteredNotifications.length > 0 && maxItems && notifications.length > maxItems && (
        <div className="px-6 py-3 border-t border-gray-200 text-center">
          <button
            onClick={() => setFilter('all')}
            className="text-sm text-primary-600 hover:text-primary-700 font-medium"
          >
            View all {notifications.length} notifications
          </button>
        </div>
      )}
    </div>
  );
};

export default PreOrderNotifications;