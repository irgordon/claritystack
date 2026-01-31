const { performance } = require('perf_hooks');

const PAGE_SIZE = 20;
const PAGE_COUNT = 500; // 10000 items
const TOTAL_ITEMS = PAGE_SIZE * PAGE_COUNT;

// Data generation
const generatePage = (pageIndex) => {
    return Array.from({ length: PAGE_SIZE }, (_, i) => ({
        id: pageIndex * PAGE_SIZE + i,
        token: `token_${pageIndex}_${i}`
    }));
};

const chunks = [];
const chunkOffsets = [];
let total = 0;

for (let i = 0; i < PAGE_COUNT; i++) {
    const page = generatePage(i);
    chunks.push(page);
    chunkOffsets.push(total);
    total += page.length;
}

const flatList = chunks.flat();

// Current Implementation: Binary Search
const getItemCurrent = (index, chunks, offsets) => {
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

// Benchmark Lookup
const ITERATIONS = 1000000; // 1 million lookups

console.log(`Benchmarking with ${TOTAL_ITEMS} items...`);

const startCurrent = performance.now();
let chk1 = 0;
for (let i = 0; i < ITERATIONS; i++) {
    const idx = (i * 17) % TOTAL_ITEMS; // Random-ish access
    const item = getItemCurrent(idx, chunks, chunkOffsets);
    if (item) chk1++;
}
const endCurrent = performance.now();

const startFlat = performance.now();
let chk2 = 0;
for (let i = 0; i < ITERATIONS; i++) {
    const idx = (i * 17) % TOTAL_ITEMS;
    const item = flatList[idx];
    if (item) chk2++;
}
const endFlat = performance.now();

console.log(`Lookup ${ITERATIONS} items:`);
console.log(`Current (Binary Search): ${(endCurrent - startCurrent).toFixed(3)} ms`);
console.log(`Flat Array Access: ${(endFlat - startFlat).toFixed(3)} ms`);


// Benchmark Append
const newPage = generatePage(PAGE_COUNT);

const startAppendCurrent = performance.now();
// Simulating state update: [...prev, newPage]
// And the useMemo cost to recalculate offsets
for(let k=0; k<100; k++) { // Repeat to measure better
    const newChunks = [...chunks, newPage];
    const newOffsets = [];
    let t = 0;
    for (const c of newChunks) {
        newOffsets.push(t);
        t += c.length;
    }
}
const endAppendCurrent = performance.now();


const startAppendFlat = performance.now();
// Simulating state update: [...prev, ...newPage]
for(let k=0; k<100; k++) {
    const newFlat = [...flatList, ...newPage];
}
const endAppendFlat = performance.now();

console.log(`\nAppend 1 page (avg of 100 runs, to existing ${TOTAL_ITEMS} items):`);
console.log(`Current (Chunk append + recalc): ${((endAppendCurrent - startAppendCurrent)/100).toFixed(3)} ms`);
console.log(`Flat Array Append: ${((endAppendFlat - startAppendFlat)/100).toFixed(3)} ms`);
