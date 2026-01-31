const { performance } = require('perf_hooks');

function benchmark() {
    console.log('Starting Array Optimization Benchmark...');

    const initialSize = 50000;
    const batchSize = 1000;
    const iterations = 500;

    // 0. Spread (Baseline)
    {
        let state = new Array(initialSize).fill(0);
        const start = performance.now();
        for (let i = 0; i < iterations; i++) {
            const newData = new Array(batchSize).fill(i);
            state = [...state, ...newData];
        }
        const end = performance.now();
        console.log(`Spread:          ${(end - start).toFixed(2)}ms`);
    }

    // 1. Concat (Current)
    {
        let state = new Array(initialSize).fill(0);
        const start = performance.now();
        for (let i = 0; i < iterations; i++) {
            const newData = new Array(batchSize).fill(i);
            state = state.concat(newData);
        }
        const end = performance.now();
        console.log(`.concat():       ${(end - start).toFixed(2)}ms`);
    }

    // 2. Slice + Push loop
    {
        let state = new Array(initialSize).fill(0);
        const start = performance.now();
        for (let i = 0; i < iterations; i++) {
            const newData = new Array(batchSize).fill(i);
            // Clone
            const next = state.slice();
            // Push loop
            for (let j = 0; j < newData.length; j++) {
                next.push(newData[j]);
            }
            state = next;
        }
        const end = performance.now();
        console.log(`Slice + Loop:    ${(end - start).toFixed(2)}ms`);
    }

    // 3. Slice + Push spread (watch out for stack limits in real life, but ok for batchSize=1000)
    {
        let state = new Array(initialSize).fill(0);
        const start = performance.now();
        for (let i = 0; i < iterations; i++) {
            const newData = new Array(batchSize).fill(i);
            const next = state.slice();
            next.push(...newData);
            state = next;
        }
        const end = performance.now();
        console.log(`Slice + Spread:  ${(end - start).toFixed(2)}ms`);
    }
}

benchmark();
