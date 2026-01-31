import React, { useEffect } from 'react';
import { Routes, Route, Navigate, useLocation } from 'react-router-dom';

// Layouts
import AdminLayout from './components/layout/AdminLayout';
import ClientLayout from './components/layout/ClientLayout';

// Pages
import Dashboard from './pages/admin/Dashboard';
import SettingsStorage from './pages/admin/SettingsStorage';
import PageEditor from './pages/admin/PageEditor';
import ProjectGallery from './pages/client/ProjectGallery';
import Installer from './pages/Installer';

// Placeholder for Client Projects List
const ClientProjects = () => (
    <div className="bg-white p-8 rounded shadow">
      <h1 className="text-2xl font-bold mb-4">My Projects</h1>
      <p>Select a project to view details.</p>
    </div>
);

export default function App() {
  const location = useLocation();

  useEffect(() => {
    const logVisit = async () => {
      try {
        await fetch('/api/log/client', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            level: 'INFO',
            message: `Visit: ${window.location.pathname}`,
            category: 'traffic',
            context: {
              ua: navigator.userAgent,
              referrer: document.referrer
            }
          })
        });
      } catch(e) { /* ignore */ }
    };
    logVisit();
  }, [location]);

  return (
    <Routes>
      {/* Installer Route - No Layout */}
      <Route path="/install" element={<Installer />} />
      <Route path="/admin/login" element={<div className="flex h-screen items-center justify-center">Login Placeholder</div>} />

      {/* Admin Routes */}
      <Route path="/admin/*" element={
        <AdminLayout>
          <Routes>
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="settings/storage" element={<SettingsStorage />} />
            <Route path="pages" element={<PageEditor />} />
            <Route path="*" element={<Navigate to="dashboard" replace />} />
          </Routes>
        </AdminLayout>
      } />

      {/* Client Routes */}
      <Route path="/client/*" element={
        <ClientLayout>
          <Routes>
            <Route path="projects" element={<ClientProjects />} />
            <Route path="projects/:id" element={<ProjectGallery />} />
            <Route path="*" element={<Navigate to="projects" replace />} />
          </Routes>
        </ClientLayout>
      } />

      {/* Default Redirect */}
      <Route path="/" element={<Navigate to="/admin/dashboard" replace />} />
    </Routes>
  );
}
