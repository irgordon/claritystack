import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { Toaster } from 'react-hot-toast'; // Notification library
import App from './App';
import { BrandingProvider } from './context/BrandingContext';
import './index.css'; // Imports the Tailwind directives

// 1. Find the Root Element in index.html
const rootElement = document.getElementById('root');

if (rootElement) {
  ReactDOM.createRoot(rootElement).render(
    <React.StrictMode>
      {/* 2. Inject Global Branding (CSS Variables) */}
      <BrandingProvider>
        
        {/* 3. Enable Client-Side Routing */}
        <BrowserRouter>
          
          {/* 4. The Main Application Tree */}
          <App />
          
          {/* 5. Global Toast Notification container */}
          <Toaster 
            position="top-right"
            toastOptions={{
              className: 'text-sm font-medium',
              duration: 4000,
              style: {
                background: '#333',
                color: '#fff',
              },
            }}
          />
        </BrowserRouter>
      </BrandingProvider>
    </React.StrictMode>
  );
} else {
  console.error("Failed to find the root element. Application cannot mount.");
}
