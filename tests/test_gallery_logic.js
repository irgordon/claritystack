const assert = require('assert');

// ---------------------------------------------------------
// Logic to be implemented in the component
// ---------------------------------------------------------

// Helper to calculate offsets and total count
function calculateMetadata(chunks) {
    const offsets = [];
    let total = 0;
    for (const chunk of chunks) {
        offsets.push(total);
        total += chunk.length;
    }
    return { offsets, total };
}

// Helper to retrieve item by global index
// Uses binary search for efficiency if chunks vary,
// though simple iteration is fast enough for typical page counts (e.g. 2000).
// Let's implement a simple binary search for correctness and speed.
function getItem(index, chunks, offsets) {
    if (index < 0) return undefined;

    // Binary search for the chunk index
    // We want the largest offset <= index
    let low = 0;
    let high = offsets.length - 1;
    let chunkIndex = -1;

    while (low <= high) {
        const mid = Math.floor((low + high) / 2);
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
}

// ---------------------------------------------------------
// Tests
// ---------------------------------------------------------

console.log('Testing Gallery Logic...');

// Scenario 1: Empty state
{
    const chunks = [];
    const { offsets, total } = calculateMetadata(chunks);
    assert.strictEqual(total, 0);
    assert.strictEqual(getItem(0, chunks, offsets), undefined);
}

// Scenario 2: Single chunk
{
    const chunks = [['a', 'b', 'c']];
    const { offsets, total } = calculateMetadata(chunks);
    assert.strictEqual(total, 3);
    assert.deepStrictEqual(offsets, [0]);
    assert.strictEqual(getItem(0, chunks, offsets), 'a');
    assert.strictEqual(getItem(2, chunks, offsets), 'c');
    assert.strictEqual(getItem(3, chunks, offsets), undefined);
}

// Scenario 3: Multiple chunks (variable size)
{
    const chunks = [
        ['p0', 'p1'],       // 0, 1
        ['p2', 'p3', 'p4'], // 2, 3, 4
        ['p5']              // 5
    ];
    const { offsets, total } = calculateMetadata(chunks); // [0, 2, 5]

    assert.strictEqual(total, 6);
    assert.deepStrictEqual(offsets, [0, 2, 5]);

    assert.strictEqual(getItem(0, chunks, offsets), 'p0');
    assert.strictEqual(getItem(1, chunks, offsets), 'p1');
    assert.strictEqual(getItem(2, chunks, offsets), 'p2'); // First item of 2nd chunk
    assert.strictEqual(getItem(4, chunks, offsets), 'p4'); // Last item of 2nd chunk
    assert.strictEqual(getItem(5, chunks, offsets), 'p5');
    assert.strictEqual(getItem(6, chunks, offsets), undefined);
}

// Scenario 4: Performance check (simulated)
{
    const chunks = [];
    const count = 10000; // 10k chunks
    let expectedTotal = 0;
    for (let i = 0; i < count; i++) {
        chunks.push(['x']);
        expectedTotal++;
    }
    const { offsets, total } = calculateMetadata(chunks);
    assert.strictEqual(total, expectedTotal);

    // Access last item
    const item = getItem(expectedTotal - 1, chunks, offsets);
    assert.strictEqual(item, 'x');
}

console.log('All tests passed!');
