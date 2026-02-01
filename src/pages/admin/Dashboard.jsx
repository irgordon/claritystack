import React, { useState, useEffect } from 'react';

const StatCard = ({ title, value, subtext, icon, color = 'blue' }) => (
    <div className="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-start space-x-4">
        <div className={`p-3 rounded-lg bg-${color}-50 text-${color}-600`}>
            {icon}
        </div>
        <div>
            <p className="text-sm font-medium text-gray-500">{title}</p>
            <h3 className="text-2xl font-bold text-gray-900 mt-1">{value}</h3>
            {subtext && <p className="text-xs text-gray-400 mt-1">{subtext}</p>}
        </div>
    </div>
);

export default function Dashboard() {
  const [health, setHealth] = useState(null);
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [healthRes, logsRes] = await Promise.all([
          fetch('/api/admin/health').then(r => r.ok ? r.json() : null),
          fetch('/api/admin/logs').then(r => r.ok ? r.json() : [])
        ]);
        setHealth(healthRes);
        setLogs(logsRes || []);
      } catch (err) {
        if (import.meta.env.DEV) {
          console.error("Failed to fetch dashboard data", err);
        }
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) return (
    <div className="flex h-full items-center justify-center">
        <div className="text-gray-400 flex flex-col items-center">
            <svg className="animate-spin h-8 w-8 mb-4" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading Dashboard...</span>
        </div>
    </div>
  );

  const trafficCount = logs.filter(l => l.context?.category === 'traffic').length;
  const storageServed = logs.filter(l => l.context?.category === 'storage').length;
  const storageUploads = logs.filter(l => l.context?.category === 'storage' && l.message.includes('Upload')).length;

  return (
    <div className="space-y-8">
        <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
            <div className="text-sm text-gray-500">
                System Status: <span className="text-green-600 font-medium">● Operational</span>
            </div>
        </div>

        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard
                title="Visitor Traffic"
                value={trafficCount}
                subtext="Recent sessions"
                color="blue"
                icon={
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                }
            />
            <StatCard
                title="Files Served"
                value={storageServed}
                subtext="Total retrievals"
                color="indigo"
                icon={
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                }
            />
            <StatCard
                title="Disk Usage"
                value={health?.server?.disk_free ? (health.server.disk_free / 1024 / 1024 / 1024).toFixed(1) + ' GB' : 'N/A'}
                subtext="Free space available"
                color="green"
                icon={
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                    </svg>
                }
            />
             <StatCard
                title="PHP Version"
                value={health?.server?.php_version?.split('-')[0] || 'N/A'}
                subtext={health?.server?.software || 'Unknown Server'}
                color="purple"
                icon={
                    <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                }
            />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* System Info */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4">Environment Details</h3>
                <div className="space-y-4">
                    <div>
                        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Database</p>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-600">Driver</span>
                            <span className="font-medium text-gray-900">{health?.database?.driver}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm mt-1">
                            <span className="text-gray-600">Version</span>
                            <span className="font-medium text-gray-900 truncate max-w-[150px]">{health?.database?.version}</span>
                        </div>
                    </div>
                    <div className="pt-4 border-t border-gray-50">
                        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Configuration</p>
                         <div className="bg-gray-50 rounded p-2 text-xs font-mono text-gray-600 overflow-x-auto">
                            {JSON.stringify(health?.env || {}, null, 2)}
                        </div>
                    </div>
                </div>
            </div>

            {/* Recent Logs */}
            <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col">
                <div className="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 className="text-lg font-bold text-gray-900">System Logs</h3>
                    <span className="text-xs text-gray-500">{logs.length} entries</span>
                </div>
                <div className="flex-1 overflow-x-auto">
                    <table className="min-w-full text-sm text-left">
                        <thead className="bg-gray-50 text-gray-500 font-medium">
                            <tr>
                                <th className="px-6 py-3">Time</th>
                                <th className="px-6 py-3">Level</th>
                                <th className="px-6 py-3">Category</th>
                                <th className="px-6 py-3">Message</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.slice(0, 8).map((log, i) => (
                                <tr key={i} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-3 text-gray-500 whitespace-nowrap text-xs font-mono">{log.timestamp.split('T')[1]?.split('.')[0] || log.timestamp}</td>
                                    <td className="px-6 py-3">
                                        <span className={`px-2 py-0.5 rounded text-xs font-bold border ${
                                            log.level === 'ERROR' || log.level === 'CRITICAL' ? 'bg-red-50 text-red-700 border-red-100' :
                                            log.level === 'WARNING' ? 'bg-yellow-50 text-yellow-700 border-yellow-100' : 'bg-green-50 text-green-700 border-green-100'
                                        }`}>
                                            {log.level}
                                        </span>
                                    </td>
                                    <td className="px-6 py-3 text-gray-600">{log.context?.category || 'General'}</td>
                                    <td className="px-6 py-3 text-gray-800 truncate max-w-xs">{log.message}</td>
                                </tr>
                            ))}
                            {logs.length === 0 && (
                                <tr><td colSpan="4" className="p-8 text-center text-gray-400">No logs available</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
  );
}
