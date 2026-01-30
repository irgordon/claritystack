const { performance } = require('perf_hooks');

function benchmark() {
    console.log('Starting Array Concatenation Benchmark...');

    const initialSize = 10000;
    const batchSize = 1000;
    const iterations = 100;

    // Baseline: Spread Operator
    let stateSpread = new Array(initialSize).fill(0);
    const startSpread = performance.now();
    for (let i = 0; i < iterations; i++) {
        const newData = new Array(batchSize).fill(i);
        stateSpread = [...stateSpread, ...newData];
    }
    const endSpread = performance.now();
    const durationSpread = endSpread - startSpread;

    // Optimization: .concat()
    let stateConcat = new Array(initialSize).fill(0);
    const startConcat = performance.now();
    for (let i = 0; i < iterations; i++) {
        const newData = new Array(batchSize).fill(i);
        stateConcat = stateConcat.concat(newData);
    }
    const endConcat = performance.now();
    const durationConcat = endConcat - startConcat;

    console.log(`\nResults (Appending ${batchSize} items ${iterations} times to initial array of ${initialSize}):`);
    console.log(`Spread Operator: ${durationSpread.toFixed(2)}ms`);
    console.log(`.concat():       ${durationConcat.toFixed(2)}ms`);

    const improvement = ((durationSpread - durationConcat) / durationSpread) * 100;
    console.log(`\nImprovement: ${improvement.toFixed(2)}% faster`);

    // Verify consistency
    if (stateSpread.length !== stateConcat.length) {
        console.error('Error: Resulting array lengths do not match!');
        process.exit(1);
    }
}

benchmark();
