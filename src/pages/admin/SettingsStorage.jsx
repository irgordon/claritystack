import React, { useState, useEffect } from 'react';
import { secureFetch } from '../../utils/apiClient';
import { toast } from 'react-hot-toast';
import Card from '../../components/ui/Card';
import Input from '../../components/ui/Input';
import Button from '../../components/ui/Button';

export default function SettingsStorage() {
    const [driver, setDriver] = useState('local');
    const [loading, setLoading] = useState(true);
    
    const [config, setConfig] = useState({
        cloudinary_name: '',
        cloudinary_key: '',
        cloudinary_secret: '',
        s3_key: '',
        s3_secret: '',
        s3_bucket: '',
        s3_region: 'us-east-1',
        s3_endpoint: '',
        imagekit_public: '',
        imagekit_private: '',
        imagekit_url: ''
    });

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
                    }));
                }
                setLoading(false);
            })
            .catch(err => {
                toast.error("Failed to load settings.");
                setLoading(false);
            });
    }, []);

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
            toast.success("Storage configuration updated successfully");
            setConfig(prev => ({...prev, cloudinary_secret: '', s3_secret: '', imagekit_private: ''}));
        } catch (e) {
            console.error(e);
            toast.error(e.message || "Failed to save settings");
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setConfig(prev => ({ ...prev, [name]: value }));
    };

    if (loading) return <div className="p-8 text-center text-gray-500">Loading configuration...</div>;

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Storage Settings</h1>
                    <p className="text-sm text-gray-500">Manage where your application stores files and media.</p>
                </div>
                <Button onClick={handleSave} variant="primary">Save Changes</Button>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Driver Selection */}
                <div className="lg:col-span-1 space-y-4">
                    <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 className="text-sm font-semibold text-gray-900 mb-4 uppercase tracking-wide">Storage Provider</h3>
                         <div className="space-y-2">
                            {['local', 's3', 'cloudinary', 'imagekit'].map((d) => (
                                <button
                                    key={d}
                                    onClick={() => setDriver(d)}
                                    className={`w-full text-left px-4 py-3 rounded-lg border transition-all duration-200 ${
                                        driver === d
                                        ? 'border-blue-500 bg-blue-50/50 ring-1 ring-blue-500'
                                        : 'border-gray-200 hover:bg-gray-50 hover:border-gray-300'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium capitalize text-gray-900">{d === 's3' ? 'S3 Object Storage' : d}</span>
                                        {driver === d && <div className="h-2.5 w-2.5 rounded-full bg-blue-500"></div>}
                                    </div>
                                </button>
                            ))}
                         </div>
                         <div className="mt-4 p-3 bg-yellow-50 rounded-md border border-yellow-100">
                             <p className="text-xs text-yellow-800">
                                <strong>Note:</strong> Changing the provider affects new uploads only. Existing files must be migrated manually.
                             </p>
                         </div>
                    </div>
                </div>

                {/* Configuration Form */}
                <div className="lg:col-span-2">
                    <Card title={`${driver === 's3' ? 'S3' : driver.charAt(0).toUpperCase() + driver.slice(1)} Configuration`}>
                        {driver === 'local' && (
                             <div className="flex items-start space-x-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div className="p-2 bg-white rounded shadow-sm">
                                    <svg className="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 className="font-medium text-gray-900">Local Server Storage</h4>
                                    <p className="text-sm text-gray-500 mt-1">
                                        Files are stored securely on this server's filesystem. Ensure you have regular backups configured.
                                    </p>
                                </div>
                             </div>
                        )}

                        {driver === 'cloudinary' && (
                            <div className="space-y-5">
                                <Input label="Cloud Name" name="cloudinary_name" value={config.cloudinary_name} onChange={handleChange} />
                                <Input label="API Key" name="cloudinary_key" value={config.cloudinary_key} onChange={handleChange} />
                                <Input
                                    label="API Secret"
                                    name="cloudinary_secret"
                                    type="password"
                                    placeholder="••••••••••••"
                                    helpText="Only enter a new value if you want to change the current secret."
                                    value={config.cloudinary_secret}
                                    onChange={handleChange}
                                />
                            </div>
                        )}

                        {driver === 's3' && (
                            <div className="space-y-5">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <Input label="Access Key" name="s3_key" value={config.s3_key} onChange={handleChange} />
                                    <Input
                                        label="Secret Access Key"
                                        name="s3_secret"
                                        type="password"
                                        placeholder="••••••••••••"
                                        helpText="Only enter a new value if you want to change the current secret."
                                        value={config.s3_secret}
                                        onChange={handleChange}
                                    />
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <Input label="Bucket Name" name="s3_bucket" value={config.s3_bucket} onChange={handleChange} />
                                    <Input label="Region" name="s3_region" value={config.s3_region} onChange={handleChange} placeholder="us-east-1" />
                                </div>
                                <Input
                                    label="Custom Endpoint (Optional)"
                                    name="s3_endpoint"
                                    value={config.s3_endpoint}
                                    onChange={handleChange}
                                    placeholder="e.g. https://nyc3.digitaloceanspaces.com"
                                    helpText="Required for non-AWS providers (DigitalOcean, MinIO, etc). Leave empty for standard AWS S3."
                                />
                            </div>
                        )}

                        {driver === 'imagekit' && (
                            <div className="space-y-5">
                                <Input label="Public Key" name="imagekit_public" value={config.imagekit_public} onChange={handleChange} />
                                <Input
                                    label="Private Key"
                                    name="imagekit_private"
                                    type="password"
                                    placeholder="••••••••••••"
                                    helpText="Only enter a new value if you want to change the current secret."
                                    value={config.imagekit_private}
                                    onChange={handleChange}
                                />
                                <Input label="URL Endpoint" name="imagekit_url" value={config.imagekit_url} onChange={handleChange} placeholder="https://ik.imagekit.io/your_id" />
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </div>
    );
}
