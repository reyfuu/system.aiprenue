// MCP server untuk System AI Preneur.
// Satu server standar → dipakai ChatGPT, Claude, & Hermes Agent (semua bicara protokol MCP).
// Transport: Streamable HTTP (stateless). Data: langsung ke MariaDB `pipeline`.
import 'dotenv/config';
import express from 'express';
import mysql from 'mysql2/promise';
import { z } from 'zod';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';

const PORT = process.env.MCP_PORT || 8765;
const TOKEN = process.env.MCP_TOKEN || ''; // kosong = tanpa auth (khusus dev lokal)

// Pool koneksi MariaDB (dipakai bersama semua request)
const db = mysql.createPool({
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_DATABASE || 'pipeline',
    waitForConnections: true,
    connectionLimit: 5,
});

const jsonText = (obj) => ({ content: [{ type: 'text', text: JSON.stringify(obj, null, 2) }] });
const errText = (msg) => ({ content: [{ type: 'text', text: `Error: ${msg}` }], isError: true });

// Bangun instance MCP server + daftarkan tools (fresh per request, mode stateless)
function buildServer() {
    const server = new McpServer({ name: 'pipeline-mcp', version: '0.1.0' });

    // 1) Daftar board kanban + jumlah task
    server.registerTool(
        'list_boards',
        { title: 'List Boards', description: 'Daftar semua board kanban beserta jumlah task aktifnya.', inputSchema: {} },
        async () => {
            const [rows] = await db.query(
                `SELECT c.\`key\`, c.name, c.section,
                    (SELECT COUNT(*) FROM pipelines p WHERE p.category=c.\`key\` AND p.archived_at IS NULL) AS task_count
                 FROM categories c WHERE c.type='kanban' ORDER BY c.name`
            );
            return jsonText({ boards: rows });
        }
    );

    // 2) Daftar task dalam satu board
    server.registerTool(
        'list_tasks',
        {
            title: 'List Tasks',
            description: 'Daftar task aktif dalam satu board kanban (pakai board key dari list_boards).',
            inputSchema: { board: z.string().describe('board key, mis. "hrd"') },
        },
        async ({ board }) => {
            const [rows] = await db.query(
                `SELECT id, endorse AS title, progress AS column_key, done, deadline
                 FROM pipelines WHERE category=? AND archived_at IS NULL ORDER BY id`,
                [board]
            );
            return jsonText({ board, tasks: rows });
        }
    );

    // 3) Buat task baru di board
    server.registerTool(
        'create_task',
        {
            title: 'Create Task',
            description: 'Buat task baru di board kanban. column opsional (default kolom pertama board).',
            inputSchema: {
                board: z.string().describe('board key tujuan'),
                title: z.string().describe('judul/endorse task'),
                column: z.string().optional().describe('key kolom tujuan (opsional)'),
            },
        },
        async ({ board, title, column }) => {
            // Validasi board = kanban
            const [[bd]] = await db.query(`SELECT \`key\` FROM categories WHERE \`key\`=? AND type='kanban'`, [board]);
            if (!bd) return errText(`Board kanban "${board}" tidak ditemukan.`);

            // Tentukan kolom tujuan
            let col = column;
            if (col) {
                const [[c]] = await db.query(`SELECT \`key\` FROM board_columns WHERE board_key=? AND \`key\`=?`, [board, col]);
                if (!c) return errText(`Kolom "${col}" tidak ada di board "${board}".`);
            } else {
                const [[first]] = await db.query(`SELECT \`key\` FROM board_columns WHERE board_key=? ORDER BY position ASC LIMIT 1`, [board]);
                if (!first) return errText(`Board "${board}" belum punya kolom.`);
                col = first.key;
            }

            const [result] = await db.query(
                `INSERT INTO pipelines (category, endorse, progress, account, payment_status, done, created_at, updated_at)
                 VALUES (?, ?, ?, 'fk', 'belum', 0, NOW(), NOW())`,
                [board, title, col]
            );
            return jsonText({ ok: true, task: { id: result.insertId, board, title, column: col } });
        }
    );

    return server;
}

const app = express();
app.use(express.json());

// Auth bearer (kalau MCP_TOKEN di-set)
app.use('/mcp', (req, res, next) => {
    if (!TOKEN) return next(); // dev tanpa token
    if (req.headers.authorization === `Bearer ${TOKEN}`) return next();
    res.status(401).json({ jsonrpc: '2.0', error: { code: -32001, message: 'Unauthorized' }, id: null });
});

// Endpoint MCP (Streamable HTTP, stateless: server+transport baru tiap request)
app.post('/mcp', async (req, res) => {
    try {
        const server = buildServer();
        const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });
        res.on('close', () => { transport.close(); server.close(); });
        await server.connect(transport);
        await transport.handleRequest(req, res, req.body);
    } catch (e) {
        if (!res.headersSent) res.status(500).json({ jsonrpc: '2.0', error: { code: -32603, message: String(e) }, id: null });
    }
});
// Stateless → GET/DELETE tak didukung
app.get('/mcp', (_req, res) => res.status(405).json({ error: 'Method Not Allowed' }));
app.delete('/mcp', (_req, res) => res.status(405).json({ error: 'Method Not Allowed' }));

app.get('/health', (_req, res) => res.json({ ok: true, service: 'pipeline-mcp' }));

app.listen(PORT, () => console.log(`MCP server jalan di http://127.0.0.1:${PORT}/mcp  (auth: ${TOKEN ? 'token' : 'none'})`));
