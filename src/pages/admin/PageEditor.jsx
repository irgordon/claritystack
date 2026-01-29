import React, { useState, useEffect } from 'react';
import { secureFetch } from '../../utils/apiClient';

const BlockNode = ({ node, index, path, onDrop, availableBlocks }) => {
    const canNest = availableBlocks.find(b => b.type === node.type)?.allows_nesting;
    return (
        <div className="ml-4 mt-2 p-2 border bg-white shadow-sm">
            <div className="font-bold text-xs uppercase text-blue-600">{node.type}</div>
            {canNest && (
                <div className="mt-2 border-2 border-dashed p-2 bg-gray-50">
                    {node.children?.map((child, i) => (
                        <BlockNode key={i} node={child} index={i} path={[...path, index, 'children']} onDrop={onDrop} availableBlocks={availableBlocks} />
                    ))}
                    <select className="mt-2 text-xs" onChange={(e) => onDrop([...path, index, 'children'], e.target.value)}>
                        <option value="">+ Add Block</option>
                        {availableBlocks.map(b => <option key={b.type} value={b.type}>{b.type}</option>)}
                    </select>
                </div>
            )}
        </div>
    );
};

export default function PageEditor() {
    const [tree, setTree] = useState([]);
    const [blocks, setBlocks] = useState([]);

    useEffect(() => { secureFetch('/api/admin/cms/blocks').then(setBlocks); }, []);

    const addBlock = (path, type) => {
        // Implementation omitted for brevity: Deep clone tree, push to path
        console.log("Add", type, "to", path);
    };

    return (
        <div className="p-8">
            <h1 className="font-bold mb-4">Page Builder</h1>
            {tree.map((node, i) => <BlockNode key={i} node={node} index={i} path={[]} onDrop={addBlock} availableBlocks={blocks} />)}
            <div className="mt-4">
                <h3 className="text-sm font-bold">Add Root Block</h3>
                {blocks.map(b => <button key={b.type} onClick={() => setTree([...tree, {type: b.type, props: {}, children: []}])} className="mr-2 border px-2">{b.type}</button>)}
            </div>
        </div>
    );
}
