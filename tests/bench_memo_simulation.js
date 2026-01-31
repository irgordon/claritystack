const { performance } = require('perf_hooks');

// Simulation parameters
const NUM_PARENT_RENDERS = 1000;
const VISIBLE_CELLS = 50;
const CELL_RENDER_COST_MS = 0.1; // 100 microseconds per cell

function busyWait(ms) {
    const start = performance.now();
    while (performance.now() - start < ms);
}

// Simulates the Grid component from react-window
// It behaves like a PureComponent or React.memo wrapped component
function GridComponent({ itemData, prevItemData }) {
    // If props.itemData is strictly equal to prevProps.itemData, we skip render
    // logic similar to shouldComponentUpdate(nextProps) { return nextProps.itemData !== this.props.itemData; }
    if (itemData === prevItemData) {
        return; // Skip render
    }

    // Simulate rendering cells
    for (let i = 0; i < VISIBLE_CELLS; i++) {
        // Accessing data to ensure it's not optimized away
        const val = itemData.chunks.length;
        busyWait(CELL_RENDER_COST_MS);
    }
}

function runBenchmark() {
    console.log("Benchmarking React Memoization Impact...");
    console.log(`Simulating ${NUM_PARENT_RENDERS} parent renders with ${VISIBLE_CELLS} visible cells.`);
    console.log(`Cell render cost: ${CELL_RENDER_COST_MS}ms`);

    // Baseline: New object every time
    // Simulates: itemData={{ chunks: photos, chunkOffsets, totalCount }}
    let baselineDuration = 0;
    {
        const start = performance.now();
        let prevItemData = null;

        // Data is conceptually constant (photos haven't changed yet)
        const chunks = ['a', 'b'];

        for (let i = 0; i < NUM_PARENT_RENDERS; i++) {
            // Parent renders and creates NEW object literal
            const itemData = { chunks: chunks };

            GridComponent({ itemData, prevItemData });

            prevItemData = itemData;
        }
        const end = performance.now();
        baselineDuration = end - start;
        console.log(`Baseline (New Object): ${baselineDuration.toFixed(2)}ms`);
    }

    // Optimized: Memoized object
    // Simulates: useMemo(() => ({ chunks: photos, ... }), [photos])
    let optimizedDuration = 0;
    {
        const start = performance.now();
        let prevItemData = null;

        // Data is conceptually constant
        const chunks = ['a', 'b'];
        // useMemo result (constant as long as chunks is constant)
        const memoizedItemData = { chunks: chunks };

        for (let i = 0; i < NUM_PARENT_RENDERS; i++) {
            // Parent renders but reuses memoized object
            const itemData = memoizedItemData;

            GridComponent({ itemData, prevItemData });

            prevItemData = itemData;
        }
        const end = performance.now();
        optimizedDuration = end - start;
        console.log(`Optimized (Memoized):  ${optimizedDuration.toFixed(2)}ms`);
    }

    // Summary
    const improvement = baselineDuration - optimizedDuration;
    const factor = baselineDuration / (optimizedDuration || 0.001); // avoid div by zero
    console.log(`\nImprovement: ${improvement.toFixed(2)}ms`);
    console.log(`Speedup Factor: ${factor.toFixed(1)}x`);
}

runBenchmark();
