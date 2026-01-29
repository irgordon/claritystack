import React, { createContext, useContext, useState, useEffect } from 'react';
import { secureFetch } from '../utils/apiClient';

const BrandingContext = createContext();

export const BrandingProvider = ({ children }) => {
    // Default Fallback State (used while loading or if API fails)
    const [branding, setBranding] = useState({
        business_name: 'ClarityStack',
        logo_url: '',
        public_config: {
            primary_color: '#3b82f6', // Default Blue
            secondary_color: '#1f2937', // Default Gray
            font_heading: 'Inter',
            font_body: 'Inter',
            border_radius: 8
        },
        loading: true
    });

    // 1. Fetch Branding Config on Mount
    useEffect(() => {
        const fetchConfig = async () => {
            try {
                // We use a public endpoint here because branding is visible to everyone
                // Note: Ensure this endpoint exists in ConfigController.php
                const data = await secureFetch('/api/public/config');
                
                if (data) {
                    setBranding({
                        business_name: data.business_name || 'ClarityStack',
                        logo_url: data.public_config?.logo_url || '',
                        public_config: {
                            primary_color: data.public_config?.primary_color || '#3b82f6',
                            secondary_color: data.public_config?.secondary_color || '#1f2937',
                            font_heading: data.public_config?.font_heading || 'Inter',
                            font_body: data.public_config?.font_body || 'Inter',
                            border_radius: data.public_config?.border_radius || 8
                        },
                        loading: false
                    });
                }
            } catch (error) {
                console.error("Failed to load branding:", error);
                setBranding(prev => ({ ...prev, loading: false }));
            }
        };

        fetchConfig();
    }, []);

    // 2. Inject CSS Variables into the DOM
    useEffect(() => {
        const root = document.documentElement;
        const config = branding.public_config;

        // Colors
        root.style.setProperty('--primary-brand', config.primary_color);
        root.style.setProperty('--secondary-brand', config.secondary_color);
        
        // Typography
        root.style.setProperty('--font-heading', config.font_heading);
        root.style.setProperty('--font-body', config.font_body);
        
        // Shapes
        root.style.setProperty('--border-radius', `${config.border_radius}px`);

        // Document Title
        if (branding.business_name) {
            document.title = branding.business_name;
        }

        // Favicon Injection (Optional)
        if (branding.logo_url) {
            const link = document.querySelector("link[rel~='icon']");
            if (link) {
                link.href = branding.logo_url;
            }
        }

    }, [branding]);

    return (
        <BrandingContext.Provider value={{ 
            appName: branding.business_name,
            logo: branding.logo_url,
            theme: branding.public_config,
            loading: branding.loading
        }}>
            {children}
        </BrandingContext.Provider>
    );
};

// Custom Hook for consuming the context
export const useBranding = () => {
    const context = useContext(BrandingContext);
    if (!context) {
        throw new Error('useBranding must be used within a BrandingProvider');
    }
    return context;
};
