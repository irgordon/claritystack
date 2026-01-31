import React from 'react';

export default function Input({
  label,
  error,
  helpText,
  className = '',
  id,
  type = 'text',
  ...props
}) {
  const inputId = id || props.name || Math.random().toString(36).substr(2, 9);

  return (
    <div className={`w-full ${className}`}>
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <div className="relative">
        <input
          id={inputId}
          type={type}
          className={`
            appearance-none block w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400
            focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors duration-200
            ${error ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300'}
          `}
          {...props}
        />
      </div>
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
      {helpText && !error && <p className="mt-1 text-sm text-gray-500">{helpText}</p>}
    </div>
  );
}
