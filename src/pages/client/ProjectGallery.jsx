import React, { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { useParams, Link } from 'react-router-dom';
import { secureFetch } from '../../utils/apiClient';
import { FixedSizeGrid as Grid } from 'react-window';
import AutoSizer from 'react-virtualized-auto-sizer';
import Button from '../../components/ui/Button';

// Helper to retrieve item by global index from chunked arrays
const getItem = (index, chunks, offsets) => {
    // Binary search for the chunk index
    let low = 0;
    let high = offsets.length - 1;
    let chunkIndex = -1;

    while (low <= high) {
        const mid = (low + high) >>> 1;
        if (offsets[mid] <= index) {
            chunkIndex = mid;
            low = mid + 1;
        } else {
            high = mid - 1;
        }
    }

    if (chunkIndex === -1 || chunkIndex >= chunks.length) return undefined;

    const offset = offsets[chunkIndex];
    const localIndex = index - offset;
    const chunk = chunks[chunkIndex];

    if (localIndex >= chunk.length) return undefined;

    return chunk[localIndex];
};

const Cell = ({ columnIndex, rowIndex, style, data }) => {
    const { chunks, chunkOffsets, totalCount, columnCount } = data;
    const index = rowIndex * columnCount + columnIndex;

    if (index >= totalCount) return null;

    const photo = getItem(index, chunks, chunkOffsets);
    if (!photo) return null;

    return (
        <div style={style} className="p-2">
            <div className="relative group w-full h-full rounded-lg overflow-hidden shadow-sm bg-gray-100">
                <img
                    src={`/api/files/view/${photo.id}?type=thumb`}
                    className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                    loading="lazy"
                    alt={`Project photo ${photo.id}`}
                />
                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2">
                    <button className="bg-white/90 hover:bg-white text-gray-900 rounded-full p-2 transition-colors">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                             <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                     <button className="bg-white/90 hover:bg-white text-gray-900 rounded-full p-2 transition-colors">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default function ProjectGallery() {
    const { id } = useParams();
    const [photos, setPhotos] = useState([]); // Array of arrays
    const [page, setPage] = useState(1);
    const loadingRef = useRef(false);

    // Calculate metadata efficiently
    const { chunkOffsets, totalCount } = useMemo(() => {
        const offsets = [];
        let total = 0;
        for (const chunk of photos) {
            offsets.push(total);
            total += chunk.length;
        }
        return { chunkOffsets: offsets, totalCount: total };
    }, [photos]);

    const loadPhotos = useCallback(async (p) => {
        loadingRef.current = true;

        try {
            const res = await secureFetch(`/api/projects/${id}/photos?page=${p}`);
            if (res.data && Array.isArray(res.data)) {
                 setPhotos(prev => [...prev, res.data]);
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

    return (
        <div className="flex flex-col h-full -mt-8">
        {/* Negative margin to counteract Layout padding if needed, but ClientLayout has py-8.
            We want full height grid.
            Actually, let's just accept the padding or make the grid fill available space.
        */}

            <div className="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                <div>
                    <div className="flex items-center text-sm text-gray-500 mb-1">
                        <Link to="/client/projects" className="hover:text-blue-600">Projects</Link>
                        <svg className="w-4 h-4 mx-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" /></svg>
                        <span className="text-gray-900">Project #{id}</span>
                    </div>
                    <h1 className="text-3xl font-bold text-gray-900">Wedding Photos</h1>
                </div>
                <div>
                     <Button variant="primary" onClick={download}>
                        <svg className="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download All Photos
                     </Button>
                </div>
            </div>

            <div className="flex-1 min-h-[500px] border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                <AutoSizer>
                    {({ height, width }) => {
                        if (!width || !height) return null;

                        // Responsive column count
                        const columnCount = width < 640 ? 2 : width < 1024 ? 3 : 4;
                        const columnWidth = width / columnCount;
                        const rowCount = Math.ceil(totalCount / columnCount);

                        // Pass columnCount to itemData so Cell can calculate index
                        const itemData = { chunks: photos, chunkOffsets, totalCount, columnCount };

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
                                    if (visibleRowStopIndex >= rowCount - 2 && !loadingRef.current && totalCount > 0) {
                                         loadingRef.current = true;
                                         setPage(prev => prev + 1);
                                    }
                                }}
                            >
                                {Cell}
                            </Grid>
                        );
                    }}
                </AutoSizer>
            </div>
        </div>
    );
}
