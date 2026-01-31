const assert = require('assert');

// Mock data generation
const generatePage = (pageIndex, pageSize) => {
    return Array.from({ length: pageSize }, (_, i) => ({
        id: pageIndex * pageSize + i,
        val: `val_${pageIndex}_${i}`
    }));
};

const PAGE_SIZE = 20;
const PAGE_COUNT = 5;

// State simulation
let photos = [];

console.log("Simulating loading pages...");
for (let i = 0; i < PAGE_COUNT; i++) {
    const newPage = generatePage(i, PAGE_SIZE);
    // Logic from ProjectGallery.jsx: setPhotos(prev => [...prev, ...res.data]);
    photos = [...photos, ...newPage];
    console.log(`Loaded page ${i}, total photos: ${photos.length}`);
}

assert.strictEqual(photos.length, PAGE_SIZE * PAGE_COUNT, "Total count mismatch");

// Grid Access simulation
const columnCount = 4;
const totalCount = photos.length;
const rowCount = Math.ceil(totalCount / columnCount);

console.log(`Grid configuration: ${columnCount} columns, ${rowCount} rows`);

for (let rowIndex = 0; rowIndex < rowCount; rowIndex++) {
    for (let columnIndex = 0; columnIndex < columnCount; columnIndex++) {
        const index = rowIndex * columnCount + columnIndex;

        // Logic from Cell component
        if (index >= photos.length) {
            // Should be out of bounds
            const item = photos[index];
            assert.strictEqual(item, undefined, `Item at index ${index} should be undefined`);
            continue;
        }

        const photo = photos[index];
        assert.ok(photo, `Photo at index ${index} should exist`);
        assert.strictEqual(photo.id, index, `Photo ID mismatch at index ${index}`);
    }
}

console.log("Verification successful: Flat array access logic is correct.");
