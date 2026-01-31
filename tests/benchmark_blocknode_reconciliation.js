const crypto = require('crypto');

// --- Mock Data Generation ---

function generateId() {
    return crypto.randomUUID();
}

function createNode(type, children = []) {
    return {
        id: generateId(),
        type,
        props: {},
        children
    };
}

function generateTree(depth, breadth) {
    if (depth === 0) return [];
    const children = [];
    for (let i = 0; i < breadth; i++) {
        children.push(createNode('Container', generateTree(depth - 1, breadth)));
    }
    return children;
}

// Generate a reasonably sized tree
// Depth 3, Breadth 5 -> 5 roots, each has 5 children (25), each has 5 (125) = 155 nodes
const TREE_DEPTH = 4;
const TREE_BREADTH = 4;
const mockTree = generateTree(TREE_DEPTH, TREE_BREADTH);

// --- Simulation Helpers ---

// Flattens the tree into a list of "Render Instances"
// simulating how React traverses and calls the component function
function traverseAndCollectProps(nodes, path = [], parentHandlers) {
    let instances = [];

    nodes.forEach((node, index) => {
        const currentPath = [...path, index, 'children']; // New array reference created here in actual code

        // In the unoptimized component, these functions are recreated in the parent render
        // But here we are simulating the props passed *to* BlockNode
        // The props passed to BlockNode are:
        // node, index, path, onAddChild, onDelete, availableBlocks

        const props = {
            node: node, // Same reference if node hasn't changed
            index: index,
            path: currentPath, // New reference every time in recursion
            onAddChild: parentHandlers.onAddChild,
            onDelete: parentHandlers.onDelete,
            availableBlocks: parentHandlers.availableBlocks
        };

        instances.push({ id: node.id, props });

        if (node.children) {
            instances = instances.concat(traverseAndCollectProps(node.children, currentPath, parentHandlers));
        }
    });
    return instances;
}

// --- Benchmark ---

console.log("⚡ Benchmarking BlockNode Reconciliation...");

// 1. Initial Render
const initialHandlers = {
    onAddChild: () => {}, // In unoptimized, this is a new function every render
    onDelete: () => {},   // In unoptimized, this is a new function every render
    availableBlocks: []
};
const initialRender = traverseAndCollectProps(mockTree, [], initialHandlers);
console.log(`Generated Tree with ${initialRender.length} nodes.`);

// 2. Second Render (No state change, but parent re-rendered)
// In the unoptimized version:
// - onAddChild is a NEW function reference
// - onDelete is a NEW function reference
// - availableBlocks is likely stable (state), but let's assume it is.
// - node objects are STABLE (same references)
const nextHandlersUnoptimized = {
    onAddChild: () => {}, // NEW reference
    onDelete: () => {},   // NEW reference
    availableBlocks: initialHandlers.availableBlocks
};

const nextRenderUnoptimized = traverseAndCollectProps(mockTree, [], nextHandlersUnoptimized);

// 3. Compare for Unoptimized (Baseline)
let unoptimizedReRenders = 0;

for (let i = 0; i < initialRender.length; i++) {
    const prev = initialRender[i].props;
    const next = nextRenderUnoptimized[i].props;

    // React's default behavior for a component (without React.memo) is to re-render
    // if the parent renders. So technically 100%.
    // But even with React.memo, if props are referentially different, it re-renders.

    const isNodeSame = prev.node === next.node;
    const isIndexSame = prev.index === next.index;
    const isPathSame = prev.path === next.path; // Will be false (new array)
    const isAddChildSame = prev.onAddChild === next.onAddChild; // Will be false
    const isDeleteSame = prev.onDelete === next.onDelete; // Will be false

    // In strict default React (no memo), it renders always.
    // If we assume the user *wants* to optimize, they usually add React.memo.
    // But React.memo only works if props are shallowly equal.

    const propsShallowEqual =
        isNodeSame && isIndexSame && isPathSame && isAddChildSame && isDeleteSame;

    if (!propsShallowEqual) {
        unoptimizedReRenders++;
    }
}

// 4. Compare for Optimized (Proposed)
// - Handlers are stable (useCallback)
// - Custom comparator handles 'path' array value equality
const nextHandlersOptimized = {
    onAddChild: initialHandlers.onAddChild, // STABLE reference
    onDelete: initialHandlers.onDelete,     // STABLE reference
    availableBlocks: initialHandlers.availableBlocks
};

const nextRenderOptimized = traverseAndCollectProps(mockTree, [], nextHandlersOptimized);

let optimizedReRenders = 0;

// Custom comparator simulation
function arePropsEqual(prev, next) {
    if (prev.node !== next.node) return false;
    if (prev.index !== next.index) return false;
    if (prev.onAddChild !== next.onAddChild) return false;
    if (prev.onDelete !== next.onDelete) return false;

    // Path comparison (Value equality)
    if (prev.path.length !== next.path.length) return false;
    for (let k = 0; k < prev.path.length; k++) {
        if (prev.path[k] !== next.path[k]) return false;
    }

    return true;
}

for (let i = 0; i < initialRender.length; i++) {
    const prev = initialRender[i].props;
    const next = nextRenderOptimized[i].props;

    // Even though 'path' is a new array reference in 'next',
    // our custom comparator should see it as equal.

    if (!arePropsEqual(prev, next)) {
        optimizedReRenders++;
    }
}

console.log("\n📊 Results:");
console.log(`- Total Nodes: ${initialRender.length}`);
console.log(`- Baseline Re-renders (Default/Unstable Props): ${unoptimizedReRenders} (${Math.round(unoptimizedReRenders/initialRender.length*100)}%)`);
console.log(`- Optimized Re-renders (Stable Props + Custom Compare): ${optimizedReRenders} (${Math.round(optimizedReRenders/initialRender.length*100)}%)`);
console.log(`\nimpact: ${unoptimizedReRenders - optimizedReRenders} fewer re-renders per update.`);
