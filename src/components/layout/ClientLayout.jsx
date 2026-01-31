import React from 'react';
import { Link } from 'react-router-dom';

export default function ClientLayout({ children }) {
  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center">
             <div className="h-8 w-8 bg-blue-600 rounded flex items-center justify-center font-bold text-white mr-2">C</div>
             <span className="font-bold text-xl tracking-tight text-gray-900">CLARITY</span>
          </div>
          <div className="flex items-center space-x-4">
            <Link to="/client/projects" className="text-sm font-medium text-gray-500 hover:text-gray-900">My Projects</Link>
            <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
              JD
            </div>
          </div>
        </div>
      </header>
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {children}
      </main>
    </div>
  );
}
