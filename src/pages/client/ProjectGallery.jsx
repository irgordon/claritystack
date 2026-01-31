import React, { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { secureFetch } from '../../utils/apiClient';
import { FixedSizeGrid as Grid } from 'react-window';
import { AutoSizer } from 'react-virtualized-auto-sizer';

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
    const { chunks, chunkOffsets, totalCount } = data;
    const index = rowIndex * 4 + columnIndex;

    if (index >= totalCount) return null;

    const photo = getItem(index, chunks, chunkOffsets);
    if (!photo) return null;

    return (
        <div style={style} className="p-2">
            <img
                src={`/api/files/view/${photo.id}?type=thumb`}
                className="w-full aspect-square object-cover"
                loading="lazy"
                alt={`Project photo ${photo.id}`}
            />
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
                 // Optimization: Append chunk instead of concatenating
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
        <div className="p-4 h-screen flex flex-col">
            <button onClick={download} className="bg-black text-white px-4 py-2 mb-4 shrink-0">Download ZIP</button>
            <div className="flex-1">
                <AutoSizer renderProp={({ height, width }) => {
                        // Wait for measurements
                        if (!width || !height) return null;

                        const columnCount = 4;
                        const columnWidth = width / columnCount;
                        const rowCount = Math.ceil(totalCount / columnCount);

                        return (
                            <Grid
                                columnCount={columnCount}
                                columnWidth={columnWidth}
                                height={height}
                                rowCount={rowCount}
                                rowHeight={columnWidth}
                                width={width}
                                itemData={{ chunks: photos, chunkOffsets, totalCount }}
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
                />
            </div>
        </div>
    );
}
