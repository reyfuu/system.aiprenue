// MCP server untuk System AI Preneur.
// Satu server standar → dipakai ChatGPT, Claude, & Hermes Agent (semua bicara protokol MCP).
// Transport: Streamable HTTP (stateless). Data: langsung ke MariaDB `pipeline`.
import 'dotenv/config';
import crypto from 'node:crypto';
import express from 'express';
import mysql from 'mysql2/promise';
import { z } from 'zod';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';

const PORT = process.env.MCP_PORT || 8765;
const TOKEN = process.env.MCP_TOKEN || ''; // kosong = tanpa auth (khusus dev lokal)

// ── OAuth 2.1 owner-tunggal, tanpa dependency (crypto bawaan Node) ────────────
// ChatGPT & Claude hp/web butuh OAuth (UI mereka tak bisa kirim bearer statis).
// Server MCP ini jadi authorization server-nya sekaligus; owner login pakai
// MCP_TOKEN sbg password. Access token = JWT HMAC (stateless, tahan restart).
// Klien lama (Claude Code/Hermes/task.js) yg kirim `Bearer <MCP_TOKEN>` tetap jalan.
const OAUTH_SECRET = crypto.createHash('sha256').update('mcp-oauth:' + TOKEN).digest();
const codes = new Map(); // ponytail: authorization code in-memory TTL 60s — hilang saat restart, cukup krn ephemeral

const b64url = (s) => Buffer.from(s).toString('base64url');
const now = () => Math.floor(Date.now() / 1000);

function signJwt(claims, ttlSec) {
    const head = b64url(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
    const body = b64url(JSON.stringify({ ...claims, iat: now(), exp: now() + ttlSec }));
    const sig = crypto.createHmac('sha256', OAUTH_SECRET).update(head + '.' + body).digest('base64url');
    return `${head}.${body}.${sig}`;
}
function verifyJwt(token) {
    try {
        const [h, b, s] = String(token).split('.');
        if (!h || !b || !s) return null;
        const expect = crypto.createHmac('sha256', OAUTH_SECRET).update(h + '.' + b).digest('base64url');
        if (s.length !== expect.length || !crypto.timingSafeEqual(Buffer.from(s), Buffer.from(expect))) return null;
        const claims = JSON.parse(Buffer.from(b, 'base64url').toString());
        return claims.exp && claims.exp >= now() ? claims : null;
    } catch { return null; }
}
const pkceOk = (verifier, challenge) =>
    crypto.createHash('sha256').update(String(verifier)).digest('base64url') === challenge;

// URL publik server (buat metadata OAuth). Set MCP_PUBLIC_URL di produksi.
function baseUrl(req) {
    if (process.env.MCP_PUBLIC_URL) return process.env.MCP_PUBLIC_URL.replace(/\/$/, '');
    const proto = req.headers['x-forwarded-proto'] || req.protocol || 'https';
    return `${proto}://${req.headers.host}`;
}

// Access token diterima: JWT OAuth (ChatGPT/hp/web) ATAU token statis (Claude Code/Hermes/CLI).
function authOk(req) {
    if (!TOKEN) return true; // dev tanpa token
    const m = /^Bearer (.+)$/.exec(req.headers.authorization || '');
    if (!m) return false;
    return m[1] === TOKEN || !!verifyJwt(m[1]);
}

function tokenSet() {
    return {
        access_token: signJwt({ sub: 'owner' }, 3600),
        token_type: 'Bearer',
        expires_in: 3600,
        refresh_token: signJwt({ sub: 'owner', typ: 'refresh' }, 60 * 60 * 24 * 30),
    };
}

function loginPage({ redirect_uri, code_challenge, state, client_id, err }) {
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    return `<!doctype html><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">
<title>Login MCP — AI Preneur</title>
<body style="font-family:system-ui;max-width:340px;margin:12vh auto;padding:0 20px">
<h2>MCP Pipeline</h2>
<p style="color:#64748b">Masuk untuk menghubungkan Claude.</p>
${err ? `<p style="color:#dc2626">${esc(err)}</p>` : ''}
<form method=post action=/authorize>
<input type=hidden name=redirect_uri value="${esc(redirect_uri)}">
<input type=hidden name=code_challenge value="${esc(code_challenge)}">
<input type=hidden name=state value="${esc(state)}">
<input type=hidden name=client_id value="${esc(client_id)}">
<input type=password name=password placeholder="Password (MCP_TOKEN)" autofocus required
 style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box">
<button style="width:100%;margin-top:12px;padding:10px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:600">Masuk</button>
</form>`;
}

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

    // 4) Ubah task yang sudah ada (by id): deadline, column, done, atau title
    server.registerTool(
        'update_task',
        {
            title: 'Update Task',
            description: 'Ubah field task by id. Isi minimal satu: deadline (YYYY-MM-DD atau null untuk hapus), column, done, title.',
            inputSchema: {
                id: z.number().int().describe('id task (dari list_tasks)'),
                deadline: z.string().nullable().optional().describe('YYYY-MM-DD, atau null untuk hapus'),
                column: z.string().optional().describe('key kolom tujuan'),
                done: z.boolean().optional().describe('tandai selesai'),
                title: z.string().optional().describe('judul/endorse baru'),
            },
        },
        async ({ id, deadline, column, done, title }) => {
            const [[task]] = await db.query(`SELECT category FROM pipelines WHERE id=? AND archived_at IS NULL`, [id]);
            if (!task) return errText(`Task id ${id} tidak ditemukan.`);

            const sets = [], vals = [];
            if (deadline !== undefined) { sets.push('deadline=?'); vals.push(deadline || null); }
            if (done !== undefined) { sets.push('done=?'); vals.push(done ? 1 : 0); }
            if (title !== undefined) { sets.push('endorse=?'); vals.push(title); }
            if (column !== undefined) {
                const [[c]] = await db.query(`SELECT \`key\` FROM board_columns WHERE board_key=? AND \`key\`=?`, [task.category, column]);
                if (!c) return errText(`Kolom "${column}" tidak ada di board "${task.category}".`);
                sets.push('progress=?'); vals.push(column);
            }
            if (!sets.length) return errText('Tidak ada field yang diubah.');

            sets.push('updated_at=NOW()');
            await db.query(`UPDATE pipelines SET ${sets.join(', ')} WHERE id=?`, [...vals, id]);
            const [[row]] = await db.query(`SELECT id, endorse AS title, progress AS column_key, done, deadline FROM pipelines WHERE id=?`, [id]);
            return jsonText({ ok: true, task: row });
        }
    );

    // ── Sales Pipeline (board `sales`, type=pipeline) ─────────────────────────
    // Enum mengikuti App\Models\Pipeline (JENIS/ACCOUNTS) + kolom board sales.
    const JENIS = ['endorse', 'coaching_1on1', 'coaching_perusahaan', 'agensi', 'speaker'];
    const ACCOUNTS = ['fk', 'ai_preneur'];
    const PAYMENT = ['belum', 'dp', 'lunas'];

    // Board sales diambil dinamis (type='pipeline') — cuma ada satu.
    const salesKey = async () => {
      const [[b]] = await db.query(`SELECT \`key\` FROM categories WHERE type='pipeline' LIMIT 1`);
      return b ? b.key : null;
    };

    // 5) Stage (kolom) pipeline sales
    server.registerTool(
      'list_pipeline_stages',
      { title: 'List Pipeline Stages', description: 'Daftar stage/kolom pipeline sales (lead → deal). Pakai stage key ini untuk create_deal/update_deal.', inputSchema: {} },
      async () => {
        const key = await salesKey();
        if (!key) return errText('Board sales tidak ditemukan.');
        const [rows] = await db.query(`SELECT \`key\`, name FROM board_columns WHERE board_key=? ORDER BY position`, [key]);
        return jsonText({ board: key, stages: rows });
      }
    );

    // 6) Daftar deal aktif di pipeline sales (opsional filter stage/jenis)
    server.registerTool(
      'list_deals',
      {
        title: 'List Deals',
        description: 'Daftar deal aktif di pipeline sales. Filter opsional: stage (key kolom) atau jenis.',
        inputSchema: {
          stage: z.string().optional().describe('filter key stage, mis. "nego"'),
          jenis: z.string().optional().describe('filter jenis deal'),
        },
      },
      async ({ stage, jenis }) => {
        const key = await salesKey();
        if (!key) return errText('Board sales tidak ditemukan.');
        const where = ['category=?', 'archived_at IS NULL'];
        const vals = [key];
        if (stage) { where.push('progress=?'); vals.push(stage); }
        if (jenis) { where.push('jenis=?'); vals.push(jenis); }
        const [rows] = await db.query(
          `SELECT id, endorse AS nama, jenis, account, progress AS stage, payment_status,
                  amount_idr, amount_usd, deadline, kontak_wa, kontak_gmail, kontak_ig, link
           FROM pipelines WHERE ${where.join(' AND ')} ORDER BY id DESC`,
          vals
        );
        return jsonText({ board: key, count: rows.length, deals: rows });
      }
    );

    // 7) Buat deal baru (wajib: nama; stage default kolom pertama = lead)
    server.registerTool(
      'create_deal',
      {
        title: 'Create Deal',
        description: 'Buat deal baru di pipeline sales. Wajib: nama. Field lain opsional.',
        inputSchema: {
          nama: z.string().describe('nama lead/klien (kolom endorse)'),
          jenis: z.enum(JENIS).optional().describe('jenis deal'),
          account: z.enum(ACCOUNTS).optional().describe('akun'),
          stage: z.string().optional().describe('key stage tujuan (default kolom pertama: lead)'),
          amount_idr: z.number().optional().describe('nilai IDR'),
          amount_usd: z.number().optional().describe('nilai USD'),
          payment_status: z.enum(PAYMENT).optional().describe('status bayar'),
          deadline: z.string().optional().describe('YYYY-MM-DD'),
          kontak_wa: z.string().optional(),
          kontak_gmail: z.string().optional(),
          kontak_ig: z.string().optional(),
          link: z.string().optional(),
          notes: z.string().optional(),
        },
      },
      async (a) => {
        const key = await salesKey();
        if (!key) return errText('Board sales tidak ditemukan.');
        // Stage: validasi yang diberi, atau ambil kolom pertama board.
        let stage = a.stage;
        if (stage) {
          const [[c]] = await db.query(`SELECT \`key\` FROM board_columns WHERE board_key=? AND \`key\`=?`, [key, stage]);
          if (!c) return errText(`Stage "${stage}" tidak ada di board sales.`);
        } else {
          const [[first]] = await db.query(`SELECT \`key\` FROM board_columns WHERE board_key=? ORDER BY position ASC LIMIT 1`, [key]);
          stage = first ? first.key : 'lead';
        }
        // INSERT dinamis: cuma kolom yang diisi (sisanya pakai default DB).
        const cols = ['category', 'endorse', 'progress'];
        const vals = [key, a.nama, stage];
        const opt = {
          jenis: a.jenis, account: a.account, amount_idr: a.amount_idr, amount_usd: a.amount_usd,
          payment_status: a.payment_status, deadline: a.deadline || undefined,
          kontak_wa: a.kontak_wa, kontak_gmail: a.kontak_gmail, kontak_ig: a.kontak_ig, link: a.link, notes: a.notes,
        };
        for (const [k, v] of Object.entries(opt)) { if (v !== undefined) { cols.push(k); vals.push(v); } }
        const ph = cols.map(() => '?').join(', ');
        const [result] = await db.query(
          `INSERT INTO pipelines (${cols.map((c) => `\`${c}\``).join(', ')}, created_at, updated_at) VALUES (${ph}, NOW(), NOW())`,
          vals
        );
        return jsonText({ ok: true, deal: { id: result.insertId, nama: a.nama, stage } });
      }
    );

    // 8) Ubah deal by id (pindah stage, nilai, status bayar, deadline, dll)
    server.registerTool(
      'update_deal',
      {
        title: 'Update Deal',
        description: 'Ubah field deal sales by id (dari list_deals). Isi minimal satu field.',
        inputSchema: {
          id: z.number().int().describe('id deal'),
          stage: z.string().optional().describe('pindah ke stage (key kolom)'),
          jenis: z.enum(JENIS).optional(),
          account: z.enum(ACCOUNTS).optional(),
          amount_idr: z.number().optional(),
          amount_usd: z.number().optional(),
          payment_status: z.enum(PAYMENT).optional(),
          deadline: z.string().nullable().optional().describe('YYYY-MM-DD atau null untuk hapus'),
          nama: z.string().optional().describe('nama/endorse baru'),
          notes: z.string().optional(),
        },
      },
      async (a) => {
        const key = await salesKey();
        if (!key) return errText('Board sales tidak ditemukan.');
        const [[deal]] = await db.query(`SELECT id FROM pipelines WHERE id=? AND category=? AND archived_at IS NULL`, [a.id, key]);
        if (!deal) return errText(`Deal id ${a.id} tidak ditemukan di pipeline sales.`);
        if (a.stage !== undefined) {
          const [[c]] = await db.query(`SELECT \`key\` FROM board_columns WHERE board_key=? AND \`key\`=?`, [key, a.stage]);
          if (!c) return errText(`Stage "${a.stage}" tidak ada di board sales.`);
        }
        const map = {
          progress: a.stage, jenis: a.jenis, account: a.account, amount_idr: a.amount_idr,
          amount_usd: a.amount_usd, payment_status: a.payment_status, endorse: a.nama, notes: a.notes,
        };
        const sets = [], vals = [];
        for (const [col, v] of Object.entries(map)) { if (v !== undefined) { sets.push(`\`${col}\`=?`); vals.push(v); } }
        if (a.deadline !== undefined) { sets.push('deadline=?'); vals.push(a.deadline || null); }
        if (!sets.length) return errText('Tak ada field untuk diubah.');
        sets.push('updated_at=NOW()');
        await db.query(`UPDATE pipelines SET ${sets.join(', ')} WHERE id=?`, [...vals, a.id]);
        const [[row]] = await db.query(
          `SELECT id, endorse AS nama, progress AS stage, payment_status, amount_idr, amount_usd, deadline
           FROM pipelines WHERE id=?`, [a.id]
        );
        return jsonText({ ok: true, deal: row });
      }
    );

    return server;
}

const app = express();
app.set('trust proxy', true); // hormati X-Forwarded-Proto dari nginx (TLS)
app.use(express.json());
app.use(express.urlencoded({ extended: false })); // form login & token endpoint

// ── OAuth endpoints (publik, tak butuh auth) ─────────────────────────────────
// Discovery: resource → authorization server
app.get('/.well-known/oauth-protected-resource', (req, res) =>
    res.json({ resource: `${baseUrl(req)}/mcp`, authorization_servers: [baseUrl(req)] }));
app.get(['/.well-known/oauth-authorization-server', '/.well-known/openid-configuration'], (req, res) => {
    const u = baseUrl(req);
    res.json({
        issuer: u,
        authorization_endpoint: `${u}/authorize`,
        token_endpoint: `${u}/token`,
        registration_endpoint: `${u}/register`,
        response_types_supported: ['code'],
        grant_types_supported: ['authorization_code', 'refresh_token'],
        code_challenge_methods_supported: ['S256'],
        token_endpoint_auth_methods_supported: ['none'],
    });
});

// Dynamic Client Registration (RFC 7591) — ChatGPT/Claude daftar sendiri.
// Owner-tunggal: client_id bukan rahasia; keamanan dari password owner + PKCE + tanda tangan JWT.
app.post('/register', (req, res) =>
    res.status(201).json({
        client_id: 'c_' + crypto.randomBytes(12).toString('hex'),
        client_id_issued_at: now(),
        redirect_uris: req.body?.redirect_uris || [],
        token_endpoint_auth_method: 'none',
    }));

// Authorize — form login owner
app.get('/authorize', (req, res) => {
    const { redirect_uri, code_challenge, state = '', client_id = '' } = req.query;
    if (!redirect_uri || !code_challenge) return res.status(400).send('invalid_request');
    res.type('html').send(loginPage({ redirect_uri, code_challenge, state, client_id, err: '' }));
});
app.post('/authorize', (req, res) => {
    const { redirect_uri, code_challenge, state = '', client_id = '', password = '' } = req.body;
    if (!redirect_uri || !code_challenge) return res.status(400).send('invalid_request');
    if (!TOKEN || password !== TOKEN)
        return res.status(401).type('html').send(loginPage({ redirect_uri, code_challenge, state, client_id, err: 'Password salah.' }));
    const code = crypto.randomBytes(24).toString('base64url');
    codes.set(code, { code_challenge, redirect_uri, exp: now() + 60 });
    const sep = redirect_uri.includes('?') ? '&' : '?';
    res.redirect(`${redirect_uri}${sep}code=${code}&state=${encodeURIComponent(state)}`);
});

// Token — tukar code (PKCE) atau refresh_token jadi access token
app.post('/token', (req, res) => {
    const { grant_type } = req.body;
    if (grant_type === 'authorization_code') {
        const entry = codes.get(req.body.code);
        codes.delete(req.body.code);
        if (!entry || entry.exp < now()) return res.status(400).json({ error: 'invalid_grant' });
        if (!pkceOk(req.body.code_verifier, entry.code_challenge)) return res.status(400).json({ error: 'invalid_grant' });
        return res.json(tokenSet());
    }
    if (grant_type === 'refresh_token') {
        const claims = verifyJwt(req.body.refresh_token);
        if (!claims || claims.typ !== 'refresh') return res.status(400).json({ error: 'invalid_grant' });
        return res.json(tokenSet());
    }
    res.status(400).json({ error: 'unsupported_grant_type' });
});

// Auth /mcp: token statis (Claude Code/Hermes/CLI) ATAU JWT OAuth (ChatGPT/hp/web)
app.use('/mcp', (req, res, next) => {
    if (authOk(req)) return next();
    res.set('WWW-Authenticate', `Bearer resource_metadata="${baseUrl(req)}/.well-known/oauth-protected-resource"`);
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

// Self-check OAuth (tanpa DB/HTTP): node server.js --selftest
if (process.argv.includes('--selftest')) {
    const ok = (c, m) => { if (!c) { console.error('FAIL:', m); process.exit(1); } };
    const t = signJwt({ sub: 'owner' }, 60);
    ok(verifyJwt(t)?.sub === 'owner', 'jwt round-trip');
    ok(verifyJwt(t + 'x') === null, 'jwt tamper ditolak');
    ok(verifyJwt(signJwt({ sub: 'owner' }, -1)) === null, 'jwt kadaluarsa ditolak');
    const v = 'verifier-abc', ch = crypto.createHash('sha256').update(v).digest('base64url');
    ok(pkceOk(v, ch) && !pkceOk('salah', ch), 'pkce S256');
    console.log('OAuth self-check OK');
    process.exit(0);
}

app.listen(PORT, () => console.log(`MCP server jalan di http://127.0.0.1:${PORT}/mcp  (auth: ${TOKEN ? 'token+oauth' : 'none'})`));
