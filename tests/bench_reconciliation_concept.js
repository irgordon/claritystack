const { performance } = require('perf_hooks');

const LIST_SIZE = 10000;
const ITEM_UPDATE_COST_MS = 0.01; // Simulate some CPU work for updating a component

// Create initial list
const initialList = Array.from({ length: LIST_SIZE }, (_, i) => ({ id: `id-${i}`, value: `value-${i}` }));

// Create new list (remove first item)
const newList = initialList.slice(1);

function simulateWork(ms) {
    const start = performance.now();
    while (performance.now() - start < ms) {
        // burn cpu
    }
}

// Strategy 1: Index Keys
// React compares oldList[0] with newList[0].
// They have same key (0), but different props (value-0 vs value-1).
// So it triggers an update.
function benchIndexKeys() {
    const start = performance.now();
    let updates = 0;
    // Iterate over the length of the new list (since keys 0..N-1 exist in both)
    for (let i = 0; i < newList.length; i++) {
        const oldItem = initialList[i];
        const newItem = newList[i];

        // In index-key world, key is 'i'. React matches them.
        // But props changed?
        if (oldItem.value !== newItem.value) {
            updates++;
            simulateWork(ITEM_UPDATE_COST_MS);
        }
    }
    const end = performance.now();
    return { time: end - start, updates };
}

// Strategy 2: Stable IDs
// React matches oldList item with ID 'id-1' to newList item with ID 'id-1'.
// Props are same. No update.
function benchStableKeys() {
    const start = performance.now();
    let updates = 0;

    // Create map for O(1) lookup (React does something similar internally with Map)
    const newMap = new Map(newList.map(item => [item.id, item]));

    for (const oldItem of initialList) {
        if (newMap.has(oldItem.id)) {
            const newItem = newMap.get(oldItem.id);
            if (oldItem.value !== newItem.value) {
                updates++;
                simulateWork(ITEM_UPDATE_COST_MS);
            }
        } else {
            // Unmount - assume negligible cost for this bench or fixed cost
        }
    }
    const end = performance.now();
    return { time: end - start, updates };
}

console.log(`Benchmarking List Reconciliation (Size: ${LIST_SIZE})`);
console.log("Scenario: Remove first item (worst case for index keys)");

const resultIndex = benchIndexKeys();
console.log(`Index Keys: ${resultIndex.time.toFixed(2)}ms (${resultIndex.updates} updates)`);

const resultStable = benchStableKeys();
console.log(`Stable Keys: ${resultStable.time.toFixed(2)}ms (${resultStable.updates} updates)`);

const improvement = resultIndex.time / resultStable.time;
console.log(`Speedup: ${improvement.toFixed(1)}x`);
