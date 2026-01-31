import React, { useState, useEffect } from 'react';

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
        console.error("Failed to fetch dashboard data", err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) return <div className="p-8 text-gray-600">Loading Dashboard...</div>;

  return (
    <div className="p-8 space-y-8">
        <h1 className="text-3xl font-bold text-gray-800">Admin Dashboard</h1>

        {/* System Health Card */}
        <div className="bg-white rounded-lg shadow p-6 border border-gray-200">
            <h2 className="text-xl font-semibold mb-4 border-b pb-2 flex items-center gap-2">
                <span className="text-green-500">●</span> System Health
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                {/* Server Info */}
                <div>
                    <h3 className="font-bold text-gray-600 mb-2 uppercase text-xs tracking-wider">Server Environment</h3>
                    <ul className="text-sm space-y-1 text-gray-700">
                        <li><span className="font-medium text-gray-900">PHP Version:</span> {health?.server?.php_version || 'N/A'}</li>
                        <li><span className="font-medium text-gray-900">OS:</span> {health?.server?.os || 'N/A'}</li>
                        <li><span className="font-medium text-gray-900">Software:</span> {health?.server?.software || 'N/A'}</li>
                    </ul>
                </div>

                {/* Database Info */}
                <div>
                    <h3 className="font-bold text-gray-600 mb-2 uppercase text-xs tracking-wider">Database</h3>
                    <ul className="text-sm space-y-1 text-gray-700">
                        <li><span className="font-medium text-gray-900">Driver:</span> {health?.database?.driver || 'N/A'}</li>
                        <li><span className="font-medium text-gray-900">Version:</span> {health?.database?.version || 'N/A'}</li>
                        <li><span className="font-medium text-gray-900">Env Driver:</span> {health?.env?.DB_DRIVER || 'N/A'}</li>
                    </ul>
                </div>

                {/* Storage Info */}
                <div>
                    <h3 className="font-bold text-gray-600 mb-2 uppercase text-xs tracking-wider">Storage</h3>
                    <ul className="text-sm space-y-1 text-gray-700">
                        <li><span className="font-medium text-gray-900">Disk Free:</span> {health?.server?.disk_free ? (health.server.disk_free / 1024 / 1024 / 1024).toFixed(2) + ' GB' : 'N/A'}</li>
                        <li><span className="font-medium text-gray-900">Upload Max:</span> {health?.server?.upload_max_filesize || 'N/A'}</li>
                    </ul>
                </div>
            </div>

            {/* Environment Variables (Select) */}
            <div className="mt-6 pt-4 border-t border-gray-100">
                <h3 className="font-bold text-gray-600 mb-2 uppercase text-xs tracking-wider">Environment Config</h3>
                <div className="bg-gray-50 p-3 rounded text-xs font-mono overflow-x-auto text-gray-600 border border-gray-200">
                    {JSON.stringify(health?.env || {}, null, 2)}
                </div>
            </div>
        </div>

        {/* Traffic & Audit Widgets */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Traffic Placeholder */}
            <div className="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 className="text-xl font-semibold mb-4 text-gray-800">Visitor Traffic</h2>
                <div className="h-48 flex flex-col items-center justify-center bg-blue-50 text-blue-500 rounded border border-blue-100">
                    <span className="text-2xl font-bold">{logs.filter(l => l.context?.category === 'traffic').length}</span>
                    <span className="text-sm">Recent Visitors</span>
                </div>
                <p className="text-sm text-gray-500 mt-4">Tracking active sessions and client-side events via <code>/api/log/client</code>.</p>
            </div>

            {/* Storage Auditing */}
            <div className="bg-white rounded-lg shadow p-6 border border-gray-200">
                <h2 className="text-xl font-semibold mb-4 text-gray-800">Storage Audit</h2>
                <div className="space-y-4">
                     <div className="flex justify-between items-center border-b pb-2">
                         <span className="text-gray-600">Files Served (Log)</span>
                         <span className="font-bold">{logs.filter(l => l.context?.category === 'storage').length}</span>
                     </div>
                     <div className="flex justify-between items-center border-b pb-2">
                         <span className="text-gray-600">Uploads (Log)</span>
                         <span className="font-bold">{logs.filter(l => l.context?.category === 'storage' && l.message.includes('Upload')).length}</span>
                     </div>
                     <p className="text-xs text-gray-400">Detailed audit logs available below.</p>
                </div>
            </div>
        </div>

        {/* Log Viewer */}
        <div className="bg-white rounded-lg shadow p-6 border border-gray-200">
            <h2 className="text-xl font-semibold mb-4 text-gray-800">System Logs</h2>
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm text-left">
                    <thead className="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                        <tr>
                            <th className="p-3">Time</th>
                            <th className="p-3">Level</th>
                            <th className="p-3">Category</th>
                            <th className="p-3">Message</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {logs.length > 0 ? logs.map((log, i) => (
                            <tr key={i} className="hover:bg-gray-50 transition-colors">
                                <td className="p-3 text-gray-500 whitespace-nowrap">{log.timestamp}</td>
                                <td className="p-3">
                                    <span className={`px-2 py-1 rounded text-xs font-bold ${
                                        log.level === 'ERROR' || log.level === 'CRITICAL' ? 'bg-red-100 text-red-700' :
                                        log.level === 'WARNING' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'
                                    }`}>
                                        {log.level}
                                    </span>
                                </td>
                                <td className="p-3 text-gray-600">{log.context?.category || 'General'}</td>
                                <td className="p-3 text-gray-800">{log.message}</td>
                            </tr>
                        )) : (
                            <tr><td colSpan="4" className="p-6 text-center text-gray-500">No logs found.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  );
}
