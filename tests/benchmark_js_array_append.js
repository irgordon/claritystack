
const { performance } = require('perf_hooks');

const BATCH_SIZE = 50; // Typical API response size
const ITERATIONS = 500; // Total items = 50 * 500 = 25,000
const REPEATS = 100;

function benchmarkSpread() {
    let arr = [];
    const batch = Array(BATCH_SIZE).fill(0).map((_, i) => ({ id: i, url: 'http://example.com' }));

    const start = performance.now();
    for (let i = 0; i < ITERATIONS; i++) {
        arr = [...arr, ...batch];
    }
    return performance.now() - start;
}

function benchmarkConcat() {
    let arr = [];
    const batch = Array(BATCH_SIZE).fill(0).map((_, i) => ({ id: i, url: 'http://example.com' }));

    const start = performance.now();
    for (let i = 0; i < ITERATIONS; i++) {
        arr = arr.concat(batch);
    }
    return performance.now() - start;
}

console.log(`Benchmarking array append methods...`);
console.log(`Batch Size: ${BATCH_SIZE}`);
console.log(`Iterations per run: ${ITERATIONS}`);
console.log(`Total Repeats: ${REPEATS}`);

let totalSpreadTime = 0;
let totalConcatTime = 0;

// Warmup
for(let i=0; i<5; i++) {
    benchmarkSpread();
    benchmarkConcat();
}

for (let r = 0; r < REPEATS; r++) {
    totalSpreadTime += benchmarkSpread();
    totalConcatTime += benchmarkConcat();
}

console.log(`\nResults (Average over ${REPEATS} runs):`);
console.log(`Spread Operator ([...prev, ...new]): ${(totalSpreadTime / REPEATS).toFixed(4)} ms`);
console.log(`Array.concat (prev.concat(new)):    ${(totalConcatTime / REPEATS).toFixed(4)} ms`);

const improvement = ((totalSpreadTime - totalConcatTime) / totalSpreadTime) * 100;
console.log(`\nImprovement: ${improvement.toFixed(2)}%`);
