import React, { useState, useEffect, useCallback, memo } from 'react';
import { secureFetch } from '../../utils/apiClient';
import Card from '../../components/ui/Card';
import Button from '../../components/ui/Button';
import Badge from '../../components/ui/Badge';

const generateId = () => window.crypto?.randomUUID() || Math.random().toString(36).substr(2, 9);

const arePropsEqual = (prevProps, nextProps) => {
    // Standard reference checks
    if (prevProps.node !== nextProps.node) return false;
    if (prevProps.index !== nextProps.index) return false;
    if (prevProps.onAddChild !== nextProps.onAddChild) return false;
    if (prevProps.onDelete !== nextProps.onDelete) return false;
    if (prevProps.availableBlocks !== nextProps.availableBlocks) return false;

    // Path array comparison (value equality)
    if (prevProps.path.length !== nextProps.path.length) return false;
    for (let i = 0; i < prevProps.path.length; i++) {
        if (prevProps.path[i] !== nextProps.path[i]) return false;
    }

    return true;
};

// Recursive Block Node Component
const BlockNode = memo(({ node, index, path, onAddChild, onDelete, availableBlocks }) => {
    const blockDef = availableBlocks.find(b => b.type === node.type);
    const canNest = blockDef?.allows_nesting;

    return (
        <div className="relative group">
            <div className="bg-white border border-gray-200 rounded-lg shadow-sm mb-4 transition-all hover:shadow-md">
                <div className="flex items-center justify-between p-3 bg-gray-50 border-b border-gray-100 rounded-t-lg">
                    <div className="flex items-center space-x-2">
                        <Badge variant="info">{node.type}</Badge>
                        <span className="text-xs text-gray-500 font-mono">#{node.id.substr(0,8)}</span>
                    </div>
                    <div className="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                         <button onClick={() => onDelete(path, index)} className="text-red-500 hover:text-red-700 p-1">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                         </button>
                    </div>
                </div>

                <div className="p-3">
                    {/* Block Props/Content Placeholder */}
                    <div className="text-sm text-gray-400 italic mb-2">Configure block properties...</div>

                    {canNest && (
                        <div className="pl-4 border-l-2 border-gray-100 space-y-2 mt-2">
                            {node.children?.map((child, i) => (
                                <BlockNode
                                    key={child.id || i}
                                    node={child}
                                    index={i}
                                    path={[...path, index, 'children']}
                                    onAddChild={onAddChild}
                                    onDelete={onDelete}
                                    availableBlocks={availableBlocks}
                                />
                            ))}

                            {/* Add Child Dropdown */}
                            <div className="relative inline-block text-left mt-2">
                                <select
                                    className="text-xs border-gray-300 rounded shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    onChange={(e) => {
                                        if(e.target.value) onAddChild([...path, index, 'children'], e.target.value);
                                        e.target.value = '';
                                    }}
                                >
                                    <option value="">+ Add Nested Block</option>
                                    {availableBlocks.map(b => <option key={b.type} value={b.type}>{b.type}</option>)}
                                </select>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}, arePropsEqual);

export default function PageEditor() {
    const [tree, setTree] = useState([]);
    const [blocks, setBlocks] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        secureFetch('/api/admin/cms/blocks')
            .then(data => {
                setBlocks(data || []);
                setLoading(false);
            })
            .catch(() => {
                // Mock blocks if API fails for demo
                setBlocks([
                    { type: 'Container', allows_nesting: true },
                    { type: 'Text', allows_nesting: false },
                    { type: 'Image', allows_nesting: false },
                    { type: 'Grid', allows_nesting: true }
                ]);
                setLoading(false);
            });
    }, []);

    // Helper to clone and traverse
    const updateTree = (currentTree, path, action) => {
        if (path.length === 0) {
            return action(currentTree);
        }
        const [head, ...tail] = path;
        const newTree = [...currentTree];
        // If head is index (number)
        if (typeof head === 'number') {
            newTree[head] = {
                ...newTree[head],
                [tail[0]]: updateTree(newTree[head][tail[0]], tail.slice(1), action)
            };
            return newTree;
        }

        const index = head;
        const key = tail[0]; // 'children'
        const remaining = tail.slice(1);

        if (key) {
             const node = { ...newTree[index] };
             node[key] = updateTree(node[key], remaining, action);
             newTree[index] = node;
             return newTree;
        } else {
             // Should not happen with current logic unless deleting?
             return newTree;
        }
    };

    const handleAddBlock = useCallback((path, type) => {
        const newBlock = { id: generateId(), type, props: {}, children: [] };

        if (path.length === 0) {
            setTree(prev => [...prev, newBlock]);
            return;
        }

        setTree(prev => {
            // Traverse
            const deepUpdate = (nodes, p) => {
                if (p.length === 0) return [...nodes, newBlock];
                const idx = p[0];
                const key = p[1]; // 'children'
                const rest = p.slice(2);

                return nodes.map((node, i) => {
                    if (i === idx) {
                        return { ...node, [key]: deepUpdate(node[key], rest) };
                    }
                    return node;
                });
            };
            return deepUpdate(prev, path);
        });
    }, []); // Empty dependency array as it uses functional state update

    const handleDelete = useCallback((path, index) => {
         // Logic to delete node at index in path
         // If path is [], delete tree[index]
         if (path.length === 0) {
             setTree(prev => prev.filter((_, i) => i !== index));
             return;
         }

         // If path is [0, 'children'], delete tree[0].children[index]
         setTree(prev => {
            const deepDelete = (nodes, p) => {
                if (p.length === 0) return nodes.filter((_, i) => i !== index);
                const idx = p[0];
                const key = p[1];
                const rest = p.slice(2);

                return nodes.map((node, i) => {
                    if (i === idx) {
                         return { ...node, [key]: deepDelete(node[key], rest) };
                    }
                    return node;
                });
            };
            return deepDelete(prev, path);
         });
    }, []); // Empty dependency array as it uses functional state update

    return (
        <div className="flex h-[calc(100vh-64px)] -m-8">
            {/* Main Canvas */}
            <div className="flex-1 bg-gray-100 p-8 overflow-y-auto">
                <div className="max-w-3xl mx-auto">
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-bold text-gray-900">Page Layout</h1>
                        <div className="text-sm text-gray-500">
                            {tree.length} Root Blocks
                        </div>
                    </div>

                    {tree.length === 0 && (
                        <div className="text-center py-20 border-2 border-dashed border-gray-300 rounded-lg">
                            <h3 className="text-lg font-medium text-gray-500">Canvas is empty</h3>
                            <p className="text-gray-400">Add a block from the right sidebar to get started.</p>
                        </div>
                    )}

                    {tree.map((node, i) => (
                        <BlockNode
                            key={node.id || i}
                            node={node}
                            index={i}
                            path={[]}
                            onAddChild={handleAddBlock}
                            onDelete={handleDelete}
                            availableBlocks={blocks}
                        />
                    ))}
                </div>
            </div>

            {/* Right Sidebar - Toolbox */}
            <div className="w-80 bg-white border-l border-gray-200 p-6 shadow-xl flex flex-col">
                <h3 className="font-bold text-gray-900 mb-4 uppercase tracking-wider text-xs">Component Library</h3>

                <div className="flex-1 overflow-y-auto pr-2 space-y-2">
                    {loading ? (
                        <div className="text-sm text-gray-400">Loading blocks...</div>
                    ) : (
                        blocks.map(b => (
                            <button
                                key={b.type}
                                onClick={() => handleAddBlock([], b.type)}
                                className="w-full flex items-center p-3 rounded border border-gray-200 bg-white hover:border-blue-500 hover:shadow-md transition-all text-left group"
                            >
                                <div className="h-8 w-8 rounded bg-blue-100 text-blue-600 flex items-center justify-center mr-3 font-bold text-xs group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                    {b.type.charAt(0)}
                                </div>
                                <div>
                                    <div className="font-medium text-sm text-gray-900">{b.type}</div>
                                    <div className="text-xs text-gray-500">{b.allows_nesting ? 'Container' : 'Content'}</div>
                                </div>
                                <div className="ml-auto opacity-0 group-hover:opacity-100 transition-opacity">
                                    <svg className="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                            </button>
                        ))
                    )}
                </div>

                <div className="mt-4 pt-4 border-t border-gray-100">
                     <Button variant="primary" className="w-full">Save Page</Button>
                </div>
            </div>
        </div>
    );
}
