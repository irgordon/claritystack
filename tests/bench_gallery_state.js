const { performance } = require('perf_hooks');

function benchmark() {
    console.log('Starting ProjectGallery State Update Benchmark...');

    const pageSize = 50;
    const totalItems = 100000; // 2000 pages
    const pages = totalItems / pageSize;

    console.log(`Simulating loading ${totalItems} items in ${pages} pages of ${pageSize}...`);

    // 1. Current: Flat Array with .concat()
    {
        let photos = [];
        const start = performance.now();
        for (let i = 0; i < pages; i++) {
            // Simulate API response
            const resData = new Array(pageSize).fill(i);
            // Update state
            photos = photos.concat(resData);
        }
        const end = performance.now();
        console.log(`Flat .concat():   ${(end - start).toFixed(2)}ms`);
        // Verify
        if (photos.length !== totalItems) console.error('Flat length mismatch');
    }

    // 2. Proposed: Chunked Array
    {
        let photos = [];
        const start = performance.now();
        for (let i = 0; i < pages; i++) {
            const resData = new Array(pageSize).fill(i);
            // Update state: append chunk
            photos = [...photos, resData];
            // Note: In functional update it would be: prev => [...prev, res.data]
            // This copies the *chunks* array (size i), not the data (size i*pageSize).
        }
        const end = performance.now();
        console.log(`Chunked Append:   ${(end - start).toFixed(2)}ms`);

        // Cost of calculating total length (simulated per render, but here just once to verify)
        const countStart = performance.now();
        const count = photos.reduce((acc, p) => acc + p.length, 0);
        const countEnd = performance.now();
        console.log(`Chunked Count calc: ${(countEnd - countStart).toFixed(4)}ms`);

        if (count !== totalItems) console.error('Chunked count mismatch');
    }
}

benchmark();
