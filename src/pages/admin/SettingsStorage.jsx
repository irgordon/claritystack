import React, { useState, useEffect } from 'react';
import { secureFetch } from '../../utils/apiClient';
import { toast } from 'react-hot-toast';

export default function SettingsStorage() {
    const [driver, setDriver] = useState('local');
    const [loading, setLoading] = useState(true);
    
    // Config State stores the form inputs
    const [config, setConfig] = useState({
        cloudinary_name: '',
        cloudinary_key: '',
        cloudinary_secret: '', // Will remain empty unless user changes it
        s3_key: '',
        s3_secret: '',
        s3_bucket: '',
        s3_region: 'us-east-1',
        s3_endpoint: '',
        imagekit_public: '',
        imagekit_private: '',
        imagekit_url: ''
    });

    // 1. Load Current Public Settings on Mount
    useEffect(() => {
        secureFetch('/api/admin/settings')
            .then(data => {
                if (data) {
                    setDriver(data.storage_driver || 'local');
                    setConfig(prev => ({
                        ...prev,
                        cloudinary_name: data.cloudinary_name || '',
                        cloudinary_key: data.cloudinary_key || '',
                        s3_key: data.s3_key || '',
                        s3_bucket: data.s3_bucket || '',
                        s3_region: data.s3_region || 'us-east-1',
                        s3_endpoint: data.s3_endpoint || '',
                        imagekit_public: data.imagekit_public || '',
                        imagekit_url: data.imagekit_url || '',
                        // Note: Secrets are NOT returned from the API for security.
                    }));
                }
                setLoading(false);
            })
            .catch(err => {
                toast.error("Failed to load settings.");
                setLoading(false);
            });
    }, []);

    // 2. Handle Save Action
    const handleSave = async () => {
        const payload = {
            driver,
            ...config
        };

        try {
            await secureFetch('/api/admin/settings/storage', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            toast.success("Storage configuration updated!");
            // Optionally clear secret fields to indicate they are saved
            setConfig(prev => ({...prev, cloudinary_secret: '', s3_secret: '', imagekit_private: ''}));
        } catch (e) {
            console.error(e);
            toast.error(e.message || "Failed to save settings");
        }
    };

    // 3. Generic Input Handler
    const handleChange = (e) => {
        const { name, value } = e.target;
        setConfig(prev => ({ ...prev, [name]: value }));
    };

    if (loading) return <div className="p-8 text-gray-500">Loading configuration...</div>;

    return (
        <div className="bg-white p-8 rounded shadow max-w-4xl mx-auto mt-8">
            <h2 className="text-2xl font-bold mb-6 text-gray-900 border-b pb-2">Storage Configuration</h2>
            
            {/* Driver Selector */}
            <div className="mb-8">
                <label className="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">Storage Provider</label>
                <select 
                    value={driver} 
                    onChange={(e) => setDriver(e.target.value)}
                    className="w-full border border-gray-300 p-3 rounded bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none"
                >
                    <option value="local">Local Server Storage (Default)</option>
                    <option value="s3">AWS S3 / DigitalOcean Spaces / Wasabi</option>
                    <option value="cloudinary">Cloudinary</option>
                    <option value="imagekit">ImageKit</option>
                </select>
                <p className="text-xs text-gray-500 mt-2">
                    Note: Changing the driver will apply to <strong>new uploads only</strong>. Existing files may become inaccessible if not migrated.
                </p>
            </div>

            {/* DYNAMIC FORM SECTIONS */}
            
            {/* CLOUDINARY */}
            {driver === 'cloudinary' && (
                <div className="space-y-4 border-l-4 border-blue-500 pl-6 py-2 bg-blue-50 rounded-r animate-fade-in">
                    <h3 className="font-bold text-blue-800 mb-4">Cloudinary Credentials</h3>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">Cloud Name</label>
                        <input name="cloudinary_name" value={config.cloudinary_name} onChange={handleChange} className="w-full border p-2 rounded mt-1" />
                    </div>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">API Key</label>
                        <input name="cloudinary_key" value={config.cloudinary_key} onChange={handleChange} className="w-full border p-2 rounded mt-1" />
                    </div>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">API Secret</label>
                        <input 
                            name="cloudinary_secret" 
                            type="password" 
                            placeholder="•••••••••••• (Leave empty to keep existing)" 
                            value={config.cloudinary_secret} 
                            onChange={handleChange} 
                            className="w-full border p-2 rounded mt-1" 
                        />
                    </div>
                </div>
            )}

            {/* AWS S3 */}
            {driver === 's3' && (
                <div className="space-y-4 border-l-4 border-yellow-500 pl-6 py-2 bg-yellow-50 rounded-r animate-fade-in">
                    <h3 className="font-bold text-yellow-800 mb-4">S3 / Spaces Object Storage</h3>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">Access Key ID</label>
                        <input name="s3_key" value={config.s3_key} onChange={handleChange} className="w-full border p-2 rounded mt-1" />
                    </div>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">Secret Access Key</label>
                        <input 
                            name="s3_secret" 
                            type="password" 
                            placeholder="•••••••••••• (Leave empty to keep existing)" 
                            value={config.s3_secret} 
                            onChange={handleChange} 
                            className="w-full border p-2 rounded mt-1" 
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase">Bucket Name</label>
                            <input name="s3_bucket" value={config.s3_bucket} onChange={handleChange} className="w-full border p-2 rounded mt-1" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-gray-500 uppercase">Region</label>
                            <input name="s3_region" value={config.s3_region} onChange={handleChange} placeholder="us-east-1" className="w-full border p-2 rounded mt-1" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">Endpoint (Optional)</label>
                        <input 
                            name="s3_endpoint" 
                            value={config.s3_endpoint} 
                            onChange={handleChange} 
                            placeholder="e.g. https://nyc3.digitaloceanspaces.com" 
                            className="w-full border p-2 rounded mt-1" 
                        />
                        <p className="text-xs text-gray-400 mt-1">Required for DigitalOcean, Wasabi, or MinIO. Leave empty for standard AWS.</p>
                    </div>
                </div>
            )}

            {/* IMAGEKIT */}
            {driver === 'imagekit' && (
                <div className="space-y-4 border-l-4 border-purple-500 pl-6 py-2 bg-purple-50 rounded-r animate-fade-in">
                    <h3 className="font-bold text-purple-800 mb-4">ImageKit.io Configuration</h3>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">Public Key</label>
                        <input name="imagekit_public" value={config.imagekit_public} onChange={handleChange} className="w-full border p-2 rounded mt-1" />
                    </div>
                    
                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">Private Key</label>
                        <input 
                            name="imagekit_private" 
                            type="password" 
                            placeholder="•••••••••••• (Leave empty to keep existing)" 
                            value={config.imagekit_private} 
                            onChange={handleChange} 
                            className="w-full border p-2 rounded mt-1" 
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-gray-500 uppercase">URL Endpoint</label>
                        <input name="imagekit_url" value={config.imagekit_url} onChange={handleChange} placeholder="https://ik.imagekit.io/your_id" className="w-full border p-2 rounded mt-1" />
                    </div>
                </div>
            )}

            {/* LOCAL */}
            {driver === 'local' && (
                <div className="p-4 bg-gray-100 rounded text-sm text-gray-600">
                    Files will be stored on the local server filesystem in <code>/storage_secure</code>. Ensure your disk has sufficient space.
                </div>
            )}

            <div className="mt-8 pt-6 border-t flex justify-end">
                <button 
                    onClick={handleSave} 
                    className="bg-gray-900 text-white px-8 py-3 rounded font-bold hover:bg-black transition-colors shadow-lg"
                >
                    Save Configuration
                </button>
            </div>
        </div>
    );
}
