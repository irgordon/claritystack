import React, { useState } from 'react';
import { secureFetch } from '../utils/apiClient';

export default function Installer() {
  const [step, setStep] = useState(1);
  const [data, setData] = useState({
    business_name: '', primary_color: '#3b82f6', link_timeout: 10,
    admin_email: '', admin_password: '', no_reply_email: ''
  });

  const install = async () => {
    try {
      await secureFetch('/api/install', { method: 'POST', body: JSON.stringify(data) });
      window.location.href = '/admin/login';
    } catch (e) { alert(e.message); }
  };

  return (
    <div className="p-8 max-w-md mx-auto bg-white rounded shadow">
      <h1 className="text-xl font-bold mb-4">ClarityStack Setup</h1>
      {step === 1 && (
        <div className="space-y-4">
          <input className="w-full border p-2" placeholder="Business Name" onChange={e => setData({...data, business_name: e.target.value})} />
          <input className="w-full border p-2" type="color" value={data.primary_color} onChange={e => setData({...data, primary_color: e.target.value})} />
          <input className="w-full border p-2" type="number" placeholder="Link Timeout (mins)" value={data.link_timeout} onChange={e => setData({...data, link_timeout: e.target.value})} />
          <button onClick={() => setStep(2)} className="w-full bg-blue-600 text-white p-2">Next</button>
        </div>
      )}
      {step === 2 && (
        <div className="space-y-4">
          <input className="w-full border p-2" placeholder="Admin Email" onChange={e => setData({...data, admin_email: e.target.value})} />
          <input className="w-full border p-2" type="password" placeholder="Password" onChange={e => setData({...data, admin_password: e.target.value})} />
          <button onClick={install} className="w-full bg-green-600 text-white p-2">Install</button>
        </div>
      )}
    </div>
  );
}
