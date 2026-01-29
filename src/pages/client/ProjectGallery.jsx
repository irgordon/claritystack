import React, { useEffect, useState, useRef, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { secureFetch } from '../../utils/apiClient';

export default function ProjectGallery() {
    const { id } = useParams();
    const [photos, setPhotos] = useState([]);
    const [page, setPage] = useState(1);
    const observer = useRef();

    const loadPhotos = useCallback(async (p) => {
        const res = await secureFetch(`/api/projects/${id}/photos?page=${p}`);
        setPhotos(prev => [...prev, ...res.data]);
    }, [id]);

    useEffect(() => { loadPhotos(1); }, [loadPhotos]);

    const lastRef = useCallback(node => {
        if (observer.current) observer.current.disconnect();
        observer.current = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) {
                setPage(prev => { loadPhotos(prev + 1); return prev + 1; });
            }
        });
        if (node) observer.current.observe(node);
    }, [loadPhotos]);

    const download = async () => {
        const res = await secureFetch(`/api/projects/${id}/download/generate`, { method: 'POST' });
        window.location.href = res.url;
    };

    return (
        <div className="p-4">
            <button onClick={download} className="bg-black text-white px-4 py-2 mb-4">Download ZIP</button>
            <div className="grid grid-cols-4 gap-4">
                {photos.map((photo, i) => (
                    <img 
                        key={i} 
                        ref={i === photos.length - 1 ? lastRef : null} 
                        src={`/api/files/view/${photo.id}?type=thumb`} 
                        className="w-full aspect-square object-cover" 
                        loading="lazy"
                    />
                ))}
            </div>
        </div>
    );
}
