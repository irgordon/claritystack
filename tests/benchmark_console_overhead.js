const iterations = 1000000;

console.log(`Running benchmark with ${iterations} iterations...`);

// Baseline: calling console.error (mocked to avoid spam, but still a function call)
// In a real browser, console.error is much heavier than a simple function call.
// We'll simulate a "heavy" console operation by doing a small amount of work.
const originalConsoleError = console.error;
let callCount = 0;
console.error = function(...args) {
    callCount++;
    // Simulate some work that a browser console might do (string formatting, stack trace capture, etc)
    const err = new Error();
    return err.stack;
};

const startBaseline = performance.now();
for (let i = 0; i < iterations; i++) {
    console.error("Failed to fetch dashboard data", new Error("Test error"));
}
const endBaseline = performance.now();

// Optimization: checking a flag (simulating import.meta.env.DEV)
const DEV = false;
const startOptimization = performance.now();
for (let i = 0; i < iterations; i++) {
    if (DEV) {
        console.error("Failed to fetch dashboard data", new Error("Test error"));
    }
}
const endOptimization = performance.now();

// Restore console
console.error = originalConsoleError;

const baselineTime = endBaseline - startBaseline;
const optimizationTime = endOptimization - startOptimization;

console.log(`Baseline (console.error): ${baselineTime.toFixed(2)}ms`);
console.log(`Optimization (if check): ${optimizationTime.toFixed(2)}ms`);
console.log(`Speedup: ${(baselineTime / optimizationTime).toFixed(2)}x`);
console.log(`Time saved per call: ${((baselineTime - optimizationTime) / iterations * 1000).toFixed(4)}μs`);
