const { performance } = require('perf_hooks');

// Mock Browser Environment
global.fetch = async (url, options) => {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 50));
    return { ok: true, json: async () => ({ status: 'logged' }) };
};

global.window = {
    location: { pathname: '/dashboard' }
};

// --- Baseline Implementation (Current) ---
const baselineState = {
    fetchCount: 0
};

// Mocking the useEffect logic
const logVisitBaseline = async (path) => {
    global.window.location.pathname = path;
    try {
        baselineState.fetchCount++;
        await fetch('/api/log/client', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                level: 'INFO',
                message: `Visit: ${path}`,
                category: 'traffic',
                context: { ua: 'test-agent' }
            })
        });
    } catch(e) {}
};


// --- Optimized Implementation (Planned) ---
const optimizedState = {
    fetchCount: 0,
    queue: [],
    timeout: null
};

const BATCH_INTERVAL = 100; // ms for test speed (real app might be 2000ms)

const logVisitOptimized = async (path) => {
    optimizedState.queue.push({
        level: 'INFO',
        message: `Visit: ${path}`,
        category: 'traffic',
        context: { ua: 'test-agent' }
    });

    if (!optimizedState.timeout) {
        optimizedState.timeout = setTimeout(async () => {
            const batch = [...optimizedState.queue];
            optimizedState.queue = [];
            optimizedState.timeout = null;

            optimizedState.fetchCount++;
            await fetch('/api/log/client', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(batch)
            });
        }, BATCH_INTERVAL);
    }
};

// --- Benchmark Runner ---

async function runBenchmark() {
    console.log("⚡ Benchmarking Client-Side Logging Strategy");
    console.log("-------------------------------------------");

    const ITERATIONS = 50;

    // 1. Run Baseline
    console.log(`\nRunning Baseline (Immediate Fetch) for ${ITERATIONS} simulated navigations...`);
    const startBaseline = performance.now();

    // Simulate rapid navigation
    const baselinePromises = [];
    for (let i = 0; i < ITERATIONS; i++) {
        baselinePromises.push(logVisitBaseline(`/page/${i}`));
        // tiny delay between clicks
        await new Promise(r => setTimeout(r, 10));
    }
    await Promise.all(baselinePromises);

    const endBaseline = performance.now();
    console.log(`Baseline completed in ${(endBaseline - startBaseline).toFixed(2)}ms`);
    console.log(`Total Requests: ${baselineState.fetchCount}`);


    // 2. Run Optimized
    console.log(`\nRunning Optimized (Buffered Fetch) for ${ITERATIONS} simulated navigations...`);
    const startOptimized = performance.now();

    for (let i = 0; i < ITERATIONS; i++) {
        logVisitOptimized(`/page/${i}`);
        await new Promise(r => setTimeout(r, 10));
    }

    // Wait for final flush
    await new Promise(r => setTimeout(r, BATCH_INTERVAL + 100));

    const endOptimized = performance.now();
    console.log(`Optimized completed in ${(endOptimized - startOptimized).toFixed(2)}ms`);
    console.log(`Total Requests: ${optimizedState.fetchCount}`);

    // Summary
    console.log("\n-------------------------------------------");
    console.log(`Reduction in HTTP Requests: ${((baselineState.fetchCount - optimizedState.fetchCount) / baselineState.fetchCount * 100).toFixed(1)}%`);
    console.log("-------------------------------------------");
}

runBenchmark();
