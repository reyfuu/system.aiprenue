// CLI fallback untuk mengakses SELURUH tool System AI Preneur tanpa client MCP
// penuh. Perintah Kanban lama dipertahankan agar skrip yang sudah memakai file
// ini tidak rusak; `tools` dan `call` membuka semua kapabilitas server.js.
//   node task.js boards
//   node task.js list <board>
//   node task.js create <board> "<judul>" [column]
//   node task.js tools
//   node task.js describe create_order
//   node task.js call list_okr '{"year":2026,"quarter":3}'
import 'dotenv/config';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const ENDPOINT = process.env.MCP_ENDPOINT || `http://127.0.0.1:${process.env.MCP_PORT || 8765}/mcp`;
const TOKEN = process.env.MCP_TOKEN || '';
const [cmd, ...rest] = process.argv.slice(2);

const usage = () => {
    console.error(`Pakai:
  node task.js tools
  node task.js describe <nama_tool>
  node task.js call <nama_tool> ['{"field":"nilai"}']
  node task.js dashboard|orders|finance|content|scripts|insights|users
  node task.js absences|mindmaps|okr|kpi|tracking|access|inventory|upload|audit
  node task.js boards
  node task.js list <board>
  node task.js create <board> "<judul>" [column]
  node task.js update <id> <field> <nilai|null>`);
    process.exit(1);
};

const CALLS = {
    dashboard: () => ['get_dashboard_summary', {}],
    orders: () => ['list_orders', {}],
    finance: () => ['list_finance', {}],
    content: () => ['list_content', {}],
    scripts: () => ['list_scripts', {}],
    insights: () => ['list_insights', {}],
    users: () => ['list_users', {}],
    absences: () => ['list_absences', {}],
    mindmaps: () => ['list_mindmaps', {}],
    okr: () => ['list_okr', {}],
    kpi: () => ['list_kpi', {}],
    tracking: () => ['list_tracking', {}],
    access: () => ['list_access', {}],
    inventory: () => ['list_inventory', {}],
    upload: () => ['get_upload_status', {}],
    audit: () => ['list_audit_logs', {}],
    boards: () => ['list_boards', {}],
    list: ([board]) => board ? ['list_tasks', { board }] : usage(),
    create: ([board, title, column]) =>
        board && title ? ['create_task', { board, title, ...(column ? { column } : {}) }] : usage(),
    update: ([id, field, value]) =>
        id && field ? ['update_task', { id: Number(id), [field]: value === 'null' ? null : value }] : usage(),
};

let name;
let args;
if (cmd === 'call') {
    name = rest[0];
    if (!name) usage();
    try {
        args = rest[1] ? JSON.parse(rest[1]) : {};
    } catch {
        console.error('✗ Argumen tool harus berupa JSON object yang valid.');
        process.exit(1);
    }
    if (!args || Array.isArray(args) || typeof args !== 'object') {
        console.error('✗ Argumen tool harus berupa JSON object.');
        process.exit(1);
    }
} else if (!['tools', 'describe'].includes(cmd)) {
    const build = CALLS[cmd];
    if (!build) usage();
    [name, args] = build(rest);
}

const transport = new StreamableHTTPClientTransport(new URL(ENDPOINT), {
    requestInit: { headers: TOKEN ? { Authorization: `Bearer ${TOKEN}` } : {} },
});
const client = new Client({ name: 'task-cli', version: '1.0.0' });
try {
    await client.connect(transport);
    if (cmd === 'tools' || cmd === 'describe') {
        const result = await client.listTools();
        if (cmd === 'describe') {
            const tool = result.tools.find((item) => item.name === rest[0]);
            if (!tool) {
                console.error(`✗ Tool "${rest[0] || ''}" tidak ditemukan.`);
                process.exitCode = 1;
            } else {
                console.log(JSON.stringify(tool, null, 2));
            }
            await client.close();
            process.exit();
        }
        for (const tool of result.tools) {
            console.log(`${tool.name}\t${tool.description || ''}`);
        }
        console.error(`\n${result.tools.length} tool tersedia dari ${ENDPOINT}`);
        await client.close();
        process.exit(0);
    }
    const r = await client.callTool({ name, arguments: args });
    console.log(r.content?.[0]?.text ?? JSON.stringify(r));
    await client.close();
} catch (e) {
    console.error('✗ ERROR:', e.message);
    process.exit(1);
}
