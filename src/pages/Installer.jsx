import React, { useState } from 'react';
import { secureFetch } from '../utils/apiClient';
import { toast } from 'react-hot-toast';
import Card from '../components/ui/Card';
import Input from '../components/ui/Input';
import Button from '../components/ui/Button';

export default function Installer() {
  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState({
    db_host: 'localhost',
    db_name: 'clarity_db',
    db_user: 'postgres',
    db_pass: '',
    business_name: '',
    primary_color: '#3b82f6',
    link_timeout: 10,
    admin_email: '',
    admin_password: '',
    no_reply_email: ''
  });

  const handleChange = (e) => {
      const { name, value } = e.target;
      setData(prev => ({ ...prev, [name]: value }));
  };

  const install = async () => {
    if (!data.admin_email || !data.admin_password) {
        toast.error("Please fill in all fields");
        return;
    }

    setLoading(true);
    try {
      await secureFetch('/api/install', { method: 'POST', body: JSON.stringify(data) });
      toast.success("Installation successful! Redirecting...");
      setTimeout(() => {
          window.location.href = '/admin/login';
      }, 1500);
    } catch (e) {
        toast.error(e.message || "Installation failed");
        setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full">
            <div className="text-center mb-8">
                <div className="inline-flex items-center justify-center h-12 w-12 rounded bg-blue-600 text-white font-bold text-xl mb-4">C</div>
                <h1 className="text-3xl font-bold text-gray-900">ClarityStack</h1>
                <p className="text-gray-500 mt-2">Enterprise Setup Wizard</p>
            </div>

            <Card className="shadow-xl">
                <div className="flex items-center justify-between mb-6 px-2">
                    <div className="flex items-center">
                        {/* Step 1 */}
                        <div className={`flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold border-2 ${step >= 1 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 text-gray-500'}`}>1</div>
                        <div className={`h-1 w-6 mx-1 ${step >= 2 ? 'bg-blue-600' : 'bg-gray-200'}`}></div>

                        {/* Step 2 */}
                        <div className={`flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold border-2 ${step >= 2 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 text-gray-500'}`}>2</div>
                        <div className={`h-1 w-6 mx-1 ${step >= 3 ? 'bg-blue-600' : 'bg-gray-200'}`}></div>

                        {/* Step 3 */}
                        <div className={`flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold border-2 ${step >= 3 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 text-gray-500'}`}>3</div>
                    </div>
                    <div className="text-sm font-medium text-gray-500">
                        Step {step} of 3
                    </div>
                </div>

                {step === 1 && (
                    <div className="space-y-4 animate-fade-in">
                        <div className="text-center mb-6">
                            <h2 className="text-xl font-bold text-gray-900">Database Connection</h2>
                            <p className="text-sm text-gray-500">Enter your PostgreSQL credentials.</p>
                        </div>

                        <Input
                            label="Database Host"
                            name="db_host"
                            value={data.db_host}
                            onChange={handleChange}
                            placeholder="localhost"
                        />
                        <Input
                            label="Database Name"
                            name="db_name"
                            value={data.db_name}
                            onChange={handleChange}
                            placeholder="clarity_db"
                        />
                         <Input
                            label="Database User"
                            name="db_user"
                            value={data.db_user}
                            onChange={handleChange}
                            placeholder="postgres"
                        />
                        <Input
                            label="Database Password"
                            name="db_pass"
                            type="password"
                            value={data.db_pass}
                            onChange={handleChange}
                            placeholder="••••••••"
                        />

                        <div className="pt-4">
                            <Button
                                onClick={() => {
                                    if(data.db_host && data.db_name && data.db_user) setStep(2);
                                    else toast.error("Please complete database fields");
                                }}
                                className="w-full"
                            >
                                Continue
                            </Button>
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div className="space-y-4 animate-fade-in">
                        <div className="text-center mb-6">
                            <h2 className="text-xl font-bold text-gray-900">Business Details</h2>
                            <p className="text-sm text-gray-500">Configure your brand identity.</p>
                        </div>

                        <Input
                            label="Business Name"
                            name="business_name"
                            value={data.business_name}
                            onChange={handleChange}
                            placeholder="My Awesome Agency"
                        />

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Primary Brand Color</label>
                            <div className="flex items-center space-x-2">
                                <input
                                    type="color"
                                    name="primary_color"
                                    value={data.primary_color}
                                    onChange={handleChange}
                                    className="h-10 w-10 border p-1 rounded cursor-pointer"
                                />
                                <Input
                                    name="primary_color"
                                    value={data.primary_color}
                                    onChange={handleChange}
                                    className="flex-1"
                                />
                            </div>
                        </div>

                        <Input
                            label="Link Timeout (Minutes)"
                            name="link_timeout"
                            type="number"
                            value={data.link_timeout}
                            onChange={handleChange}
                            helpText="How long temporary links remain valid."
                        />

                        <div className="pt-4 flex space-x-3">
                            <Button variant="outline" onClick={() => setStep(1)} className="flex-1">Back</Button>
                            <Button
                                onClick={() => {
                                    if(data.business_name) setStep(3);
                                    else toast.error("Business Name is required");
                                }}
                                className="flex-1"
                            >
                                Continue
                            </Button>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4 animate-fade-in">
                        <div className="text-center mb-6">
                            <h2 className="text-xl font-bold text-gray-900">Admin Account</h2>
                            <p className="text-sm text-gray-500">Create your master administrative user.</p>
                        </div>

                        <Input
                            label="Admin Email"
                            name="admin_email"
                            type="email"
                            value={data.admin_email}
                            onChange={handleChange}
                            placeholder="admin@example.com"
                        />

                        <Input
                            label="Password"
                            name="admin_password"
                            type="password"
                            value={data.admin_password}
                            onChange={handleChange}
                            placeholder="••••••••"
                        />

                         <Input
                            label="No-Reply Email (Optional)"
                            name="no_reply_email"
                            type="email"
                            value={data.no_reply_email}
                            onChange={handleChange}
                            placeholder="noreply@example.com"
                        />

                        <div className="pt-4 flex space-x-3">
                            <Button variant="outline" onClick={() => setStep(2)} className="flex-1">Back</Button>
                            <Button variant="primary" onClick={install} loading={loading} className="flex-1">Complete Setup</Button>
                        </div>
                    </div>
                )}
            </Card>

            <p className="text-center text-xs text-gray-400 mt-6">
                &copy; {new Date().getFullYear()} ClarityStack. All rights reserved.
            </p>
        </div>
    </div>
  );
}
