import React, { useEffect, useState, useCallback, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { secureFetch } from '../../utils/apiClient';
import { FixedSizeGrid as Grid } from 'react-window';
import { AutoSizer } from 'react-virtualized-auto-sizer';

const Cell = ({ columnIndex, rowIndex, style, data }) => {
    const index = rowIndex * 4 + columnIndex;
    if (index >= data.length) return null;
    const photo = data[index];
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
    const [photos, setPhotos] = useState([]);
    const [page, setPage] = useState(1);
    const loadingRef = useRef(false);

    const loadPhotos = useCallback(async (p) => {
        loadingRef.current = true;

        try {
            const res = await secureFetch(`/api/projects/${id}/photos?page=${p}`);
            if (res.data && Array.isArray(res.data)) {
                 setPhotos(prev => prev.concat(res.data));
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
                        const rowCount = Math.ceil(photos.length / columnCount);

                        return (
                            <Grid
                                columnCount={columnCount}
                                columnWidth={columnWidth}
                                height={height}
                                rowCount={rowCount}
                                rowHeight={columnWidth}
                                width={width}
                                itemData={photos}
                                onItemsRendered={({ visibleRowStopIndex }) => {
                                    if (visibleRowStopIndex >= rowCount - 2 && !loadingRef.current && photos.length > 0) {
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
