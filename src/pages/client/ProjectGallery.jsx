import React, { useEffect, useState, useCallback, useRef, useMemo, memo } from 'react';
import { useParams, Link } from 'react-router-dom';
import { secureFetch } from '../../utils/apiClient';
import { FixedSizeGrid as Grid } from 'react-window';
import AutoSizer from 'react-virtualized-auto-sizer';
import Button from '../../components/ui/Button';

const Cell = ({ columnIndex, rowIndex, style, data }) => {
    const { photos, columnCount } = data;
    const index = rowIndex * columnCount + columnIndex;

    if (index >= photos.length) return null;

    const photo = photos[index];
    if (!photo) return null;

    return (
        <div style={style} className="p-2">
            <div className="relative group w-full h-full rounded-xl overflow-hidden bg-gray-100 shadow-sm transition-shadow hover:shadow-md">
                <img
                    src={`/api/files/view/${photo.id}?type=thumb${photo.token ? `&token=${encodeURIComponent(photo.token)}` : ''}`}
                    className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                    loading="lazy"
                    alt={`Project photo ${photo.id}`}
                />
                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center space-x-3 backdrop-blur-[2px]">
                    <button
                        className="bg-white/90 hover:bg-white text-gray-900 rounded-full p-3 transition-transform hover:scale-110 shadow-lg"
                        title="View Photo"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                             <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                     <button
                        className="bg-white/90 hover:bg-white text-gray-900 rounded-full p-3 transition-transform hover:scale-110 shadow-lg"
                        title="Download Photo"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
};

const GalleryGrid = memo(({ height, width, photos, onLoadMore }) => {
    // Responsive column count
    const columnCount = width < 640 ? 2 : width < 1024 ? 3 : 4;
    const columnWidth = width / columnCount;
    const rowCount = Math.ceil(photos.length / columnCount);

    // Pass columnCount to itemData so Cell can calculate index
    const itemData = useMemo(() => ({ photos, columnCount }), [photos, columnCount]);

    return (
        <Grid
            columnCount={columnCount}
            columnWidth={columnWidth}
            height={height}
            rowCount={rowCount}
            rowHeight={columnWidth}
            width={width}
            itemData={itemData}
            onItemsRendered={({ visibleRowStopIndex }) => {
                if (visibleRowStopIndex >= rowCount - 2 && photos.length > 0) {
                     onLoadMore();
                }
            }}
            className="scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-transparent"
        >
            {Cell}
        </Grid>
    );
});

export default function ProjectGallery() {
    const { id } = useParams();
    const [photos, setPhotos] = useState([]); // Flat array of photos
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const loadingRef = useRef(false);

    const loadPhotos = useCallback(async (p) => {
        loadingRef.current = true;

        try {
            const res = await secureFetch(`/api/projects/${id}/photos?page=${p}`);
            if (res.data && Array.isArray(res.data)) {
                 setPhotos(prev => [...prev, ...res.data]);
            }
            if (res.meta && res.meta.total_pages) {
                setTotalPages(res.meta.total_pages);
            }
        } catch (e) {
            console.error(e);
        } finally {
            loadingRef.current = false;
        }
    }, [id]);

    useEffect(() => {
        loadPhotos(page);
    }, [page, loadPhotos]);

    const download = async () => {
        const res = await secureFetch(`/api/projects/${id}/download/generate`, { method: 'POST' });
        window.location.href = res.url;
    };

    const handleLoadMore = useCallback(() => {
        if (!loadingRef.current && page < totalPages) {
             loadingRef.current = true;
             setPage(prev => prev + 1);
        }
    }, [page, totalPages]);

    return (
        <div className="flex flex-col h-[calc(100vh-8rem)]">
            <div className="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
                <div>
                    <div className="flex items-center text-sm text-gray-500 mb-2 font-medium">
                        <Link to="/client/projects" className="hover:text-blue-600 transition-colors">Projects</Link>
                        <svg className="w-4 h-4 mx-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                        <span className="text-gray-900">Project #{id}</span>
                    </div>
                    <h1 className="text-3xl font-bold text-gray-900 tracking-tight">Gallery</h1>
                </div>
                <div>
                     <Button variant="primary" onClick={download} className="shadow-sm hover:shadow-md transition-shadow">
                        <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download All Photos
                     </Button>
                </div>
            </div>

            <div className="flex-1 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden relative">
                <AutoSizer>
                    {({ height, width }) => {
                        if (!width || !height) return null;
                        return (
                            <GalleryGrid
                                height={height}
                                width={width}
                                photos={photos}
                                onLoadMore={handleLoadMore}
                            />
                        );
                    }}
                </AutoSizer>
                {photos.length === 0 && !loadingRef.current && (
                    <div className="absolute inset-0 flex items-center justify-center text-gray-400">
                        <div className="text-center">
                            <svg className="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p>No photos found in this gallery.</p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
