import React from 'react';
import { Link } from 'react-router-dom';

export default function ClientLayout({ children }) {
  return (
    <div className="min-h-screen bg-gray-50 font-sans text-gray-900">
      <header className="bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center space-x-3">
             <div className="h-9 w-9 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-white shadow-sm">C</div>
             <span className="font-bold text-lg tracking-tight text-gray-900">CLARITY</span>
          </div>
          <div className="flex items-center space-x-6">
            <Link to="/client/projects" className="text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors">My Projects</Link>
            <div className="h-9 w-9 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 border border-white shadow-sm flex items-center justify-center text-xs font-bold text-gray-600">
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
