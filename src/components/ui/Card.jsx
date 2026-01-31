import React from 'react';

export default function Card({ children, className = '', noPadding = false, title, actions }) {
  return (
    <div className={`bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden ${className}`}>
      {(title || actions) && (
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          {title && <h3 className="text-lg font-medium text-gray-900">{title}</h3>}
          {actions && <div className="flex space-x-2">{actions}</div>}
        </div>
      )}
      <div className={noPadding ? '' : 'p-6'}>
        {children}
      </div>
    </div>
  );
}
