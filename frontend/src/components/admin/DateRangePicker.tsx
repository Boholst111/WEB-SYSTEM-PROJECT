import React, { useState } from 'react';
import { CalendarIcon, ChevronDownIcon } from '@heroicons/react/24/outline';

interface DateRangePickerProps {
  dateRange: {
    from: string;
    to: string;
    period: 'daily' | 'weekly' | 'monthly';
  };
  onChange: (dateRange: { from: string; to: string; period: 'daily' | 'weekly' | 'monthly' }) => void;
}

const DateRangePicker: React.FC<DateRangePickerProps> = ({ dateRange, onChange }) => {
  const [isOpen, setIsOpen] = useState(false);

  const presetRanges = [
    {
      label: 'Last 7 days',
      from: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      to: new Date().toISOString().split('T')[0],
      period: 'daily' as const
    },
    {
      label: 'Last 30 days',
      from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      to: new Date().toISOString().split('T')[0],
      period: 'daily' as const
    },
    {
      label: 'Last 3 months',
      from: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      to: new Date().toISOString().split('T')[0],
      period: 'weekly' as const
    },
    {
      label: 'Last 12 months',
      from: new Date(Date.now() - 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      to: new Date().toISOString().split('T')[0],
      period: 'monthly' as const
    }
  ];

  const handlePresetSelect = (preset: typeof presetRanges[0]) => {
    onChange({
      from: preset.from,
      to: preset.to,
      period: preset.period
    });
    setIsOpen(false);
  };

  const handleCustomDateChange = (field: 'from' | 'to', value: string) => {
    onChange({
      ...dateRange,
      [field]: value
    });
  };

  const handlePeriodChange = (period: 'daily' | 'weekly' | 'monthly') => {
    onChange({
      ...dateRange,
      period
    });
  };

  const formatDateRange = () => {
    const fromDate = new Date(dateRange.from);
    const toDate = new Date(dateRange.to);
    
    const formatOptions: Intl.DateTimeFormatOptions = { 
      month: 'short', 
      day: 'numeric',
      year: fromDate.getFullYear() !== toDate.getFullYear() ? 'numeric' : undefined
    };
    
    return `${fromDate.toLocaleDateString('en-US', formatOptions)} - ${toDate.toLocaleDateString('en-US', formatOptions)}`;
  };

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center space-x-2 px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <CalendarIcon className="h-4 w-4" />
        <span>{formatDateRange()}</span>
        <ChevronDownIcon className="h-4 w-4" />
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg border border-gray-200 z-50">
          <div className="p-4">
            {/* Preset Ranges */}
            <div className="mb-4">
              <h4 className="text-sm font-medium text-gray-900 mb-2">Quick Select</h4>
              <div className="space-y-1">
                {presetRanges.map((preset) => (
                  <button
                    key={preset.label}
                    onClick={() => handlePresetSelect(preset)}
                    className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md"
                  >
                    {preset.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Custom Date Range */}
            <div className="mb-4">
              <h4 className="text-sm font-medium text-gray-900 mb-2">Custom Range</h4>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-xs text-gray-500 mb-1">From</label>
                  <input
                    type="date"
                    value={dateRange.from}
                    onChange={(e) => handleCustomDateChange('from', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1">To</label>
                  <input
                    type="date"
                    value={dateRange.to}
                    onChange={(e) => handleCustomDateChange('to', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>

            {/* Period Selection */}
            <div className="mb-4">
              <h4 className="text-sm font-medium text-gray-900 mb-2">Group By</h4>
              <div className="flex space-x-2">
                {(['daily', 'weekly', 'monthly'] as const).map((period) => (
                  <button
                    key={period}
                    onClick={() => handlePeriodChange(period)}
                    className={`px-3 py-1 text-xs rounded-md ${
                      dateRange.period === period
                        ? 'bg-blue-100 text-blue-700 border border-blue-300'
                        : 'bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200'
                    }`}
                  >
                    {period.charAt(0).toUpperCase() + period.slice(1)}
                  </button>
                ))}
              </div>
            </div>

            {/* Apply Button */}
            <div className="flex justify-end">
              <button
                onClick={() => setIsOpen(false)}
                className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                Apply
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DateRangePicker;