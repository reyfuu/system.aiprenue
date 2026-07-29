// Test client MCP — simulasi apa yang dilakukan ChatGPT/Claude/Hermes saat connect.
import 'dotenv/config';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const ENDPOINT = process.env.MCP_ENDPOINT || `http://127.0.0.1:${process.env.MCP_PORT || 8765}/mcp`;
const TOKEN = process.env.MCP_TOKEN || '';

const call = async (client, name, args = {}) => {
    const r = await client.callTool({ name, arguments: args });
    return r.content?.[0]?.text ?? JSON.stringify(r);
};

(async () => {
    const transport = new StreamableHTTPClientTransport(new URL(ENDPOINT), {
        requestInit: { headers: TOKEN ? { Authorization: `Bearer ${TOKEN}` } : {} },
    });
    const client = new Client({ name: 'test-client', version: '1.0.0' });
    await client.connect(transport);
    console.log('✓ connect OK');

    const tools = await client.listTools();
    console.log('✓ tools:', tools.tools.map((t) => t.name).join(', '));
    if (process.env.MCP_LIST_ONLY === '1') {
        console.log(`✓ ${tools.tools.length} tools discovered`);
        await client.close();
        return;
    }
    if (process.env.MCP_SMOKE_TOOL) {
        console.log(await call(client, process.env.MCP_SMOKE_TOOL));
        await client.close();
        return;
    }

    console.log('\n— list_boards —\n' + (await call(client, 'list_boards')));
    console.log('\n— list_all_kanban —\n' + (await call(client, 'list_all_kanban')));
    console.log('\n— get_kanban_board (board=kerja) —\n' + (await call(client, 'get_kanban_board', { board: 'kerja' })));
    console.log('\n— create_task (board=kerja) —\n' + (await call(client, 'create_task', { board: 'kerja', title: 'Task dari MCP test' })));
    console.log('\n— list_tasks (board=kerja) —\n' + (await call(client, 'list_tasks', { board: 'kerja' })));

    await client.close();
    console.log('\n✓ selesai');
})().catch((e) => { console.error('✗ ERROR:', e.message); process.exit(1); });
