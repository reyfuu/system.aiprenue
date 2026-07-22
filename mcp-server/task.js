// CLI kecil untuk kelola task tanpa perlu client MCP penuh.
// Lewat server MCP yang sama (server.js) → validasi board/kolom & bentuk INSERT
// cuma ada di satu tempat. Dipakai saat agen tak bisa load schema MCP di sesi.
//   node task.js boards
//   node task.js list <board>
//   node task.js create <board> "<judul>" [column]
import 'dotenv/config';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const ENDPOINT = `http://127.0.0.1:${process.env.MCP_PORT || 8765}/mcp`;
const TOKEN = process.env.MCP_TOKEN || '';
const [cmd, ...rest] = process.argv.slice(2);

const usage = () => {
    console.error('Pakai: node task.js boards | list <board> | create <board> "<judul>" [column] | update <id> deadline <YYYY-MM-DD|null>');
    process.exit(1);
};

const CALLS = {
    boards: () => ['list_boards', {}],
    list: ([board]) => board ? ['list_tasks', { board }] : usage(),
    create: ([board, title, column]) =>
        board && title ? ['create_task', { board, title, ...(column ? { column } : {}) }] : usage(),
    update: ([id, field, value]) =>
        id && field ? ['update_task', { id: Number(id), [field]: value === 'null' ? null : value }] : usage(),
};

const build = CALLS[cmd];
if (!build) usage();
const [name, args] = build(rest);

const transport = new StreamableHTTPClientTransport(new URL(ENDPOINT), {
    requestInit: { headers: TOKEN ? { Authorization: `Bearer ${TOKEN}` } : {} },
});
const client = new Client({ name: 'task-cli', version: '1.0.0' });
try {
    await client.connect(transport);
    const r = await client.callTool({ name, arguments: args });
    console.log(r.content?.[0]?.text ?? JSON.stringify(r));
    await client.close();
} catch (e) {
    console.error('✗ ERROR:', e.message);
    process.exit(1);
}
