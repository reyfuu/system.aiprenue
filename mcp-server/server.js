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

// ── Helper OKR ────────────────────────────────────────────────────────────────
// Kuartal & realisasi WAJIB sama persis dgn app Laravel (Quarter + OkrMetrics),
// kalau tidak MCP & halaman /okr menampilkan angka berbeda untuk hal yang sama.
const QSPAN = { 1: ['01-01', '03-31'], 2: ['04-01', '06-30'], 3: ['07-01', '09-30'], 4: ['10-01', '12-31'] };

function currentQuarter() {
    const d = new Date();
    return { year: d.getFullYear(), quarter: Math.floor(d.getMonth() / 3) + 1 };
}
function quarterRange(year, quarter) {
    const [a, b] = QSPAN[quarter];
    return [`${year}-${a} 00:00:00`, `${year}-${b} 23:59:59`];
}

// Realisasi metrik auto — cerminan App\Support\OkrMetrics::realisasi().
//  view       = SUM(views) konten yang published_at di kuartal
//  omset      = SUM(amount_idr) transaksi pemasukan di kuartal
//  subscriber = SUM(followers) snapshot TERAKHIR tiap akun (≤ akhir kuartal),
//               bukan jumlah seluruh baris (itu menghitung orang yg sama berkali)
async function okrRealisasi(year, quarter) {
    const [start, end] = quarterRange(year, quarter);
    const [[v]] = await db.query(
        `SELECT COALESCE(SUM(views),0) n FROM insight_contents WHERE published_at BETWEEN ? AND ?`, [start, end]);
    const [[o]] = await db.query(
        `SELECT COALESCE(SUM(amount_idr),0) n FROM transactions WHERE type='pemasukan' AND date BETWEEN ? AND ?`, [start, end]);
    const [[s]] = await db.query(
        `SELECT COALESCE(SUM(ia.followers),0) n FROM insight_accounts ia
         JOIN (SELECT platform, akun, MAX(tanggal) tanggal FROM insight_accounts WHERE tanggal <= ? GROUP BY platform, akun) t
           ON ia.platform=t.platform AND ia.akun=t.akun AND ia.tanggal=t.tanggal`, [end]);
    return { view: Number(v.n), subscriber: Number(s.n), omset: Number(o.n) };
}

const pct = (actual, target) => (Number(target) > 0 ? Math.round((actual / target) * 1000) / 10 : null);

// Owner sbg pencatat default objek OKR baru (kolom created_by/owner_id).
async function ownerId() {
    const [[u]] = await db.query(`SELECT id FROM users WHERE role='owner' ORDER BY id LIMIT 1`);
    return u ? u.id : null;
}

const OKR_SOURCES = ['auto', 'manual', 'kartu'];
const OKR_METRICS = ['view', 'subscriber', 'omset'];
const OKR_UNITS = ['angka', 'rupiah', 'persen'];

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

    // ── OKR: menyusun & memantau strategi kuartalan ─────────────────────────
    // Alur yang dimaksud: AI membaca list_okr utk tahu posisi, lalu menyusun
    // Objective + Key Result. KR bersumber 'kartu' dieksekusi lewat kartu
    // todolist (create_task → link_task_to_kr), jadi progress goal bergerak
    // sendiri saat kartunya diselesaikan.

    // 5) Lihat OKR satu kuartal + realisasi (default kuartal berjalan)
    server.registerTool(
        'list_okr',
        {
            title: 'List OKR',
            description: 'Objective + Key Result satu kuartal beserta realisasi & capaian. Default kuartal berjalan. Pakai ini dulu sebelum menyusun strategi.',
            inputSchema: {
                year: z.number().int().optional().describe('tahun, mis. 2026 (default: sekarang)'),
                quarter: z.number().int().min(1).max(4).optional().describe('1–4 (default: kuartal berjalan)'),
            },
        },
        async ({ year, quarter }) => {
            const cur = currentQuarter();
            year = year || cur.year;
            quarter = quarter || cur.quarter;

            const real = await okrRealisasi(year, quarter);
            const [objs] = await db.query(
                `SELECT id, title, description, position FROM objectives WHERE year=? AND quarter=? ORDER BY position, id`, [year, quarter]);

            const objectives = [];
            for (const o of objs) {
                const [krs] = await db.query(
                    `SELECT id, title, source, metric, target, actual_manual, unit FROM key_results WHERE objective_id=? ORDER BY position, id`, [o.id]);
                const key_results = [];
                for (const kr of krs) {
                    let actual = 0, cards = null;
                    if (kr.source === 'auto') actual = real[kr.metric] ?? 0;
                    else if (kr.source === 'manual') actual = Number(kr.actual_manual ?? 0);
                    else if (kr.source === 'kartu') {
                        const [[c]] = await db.query(
                            `SELECT COUNT(*) total, COALESCE(SUM(completed_at IS NOT NULL),0) done FROM pipelines WHERE key_result_id=?`, [kr.id]);
                        actual = Number(c.done);
                        cards = { done: Number(c.done), total: Number(c.total) };
                    }
                    key_results.push({
                        id: kr.id, title: kr.title, source: kr.source, metric: kr.metric,
                        target: Number(kr.target), actual, percent: pct(actual, kr.target), unit: kr.unit,
                        ...(cards ? { cards } : {}),
                    });
                }
                objectives.push({ id: o.id, title: o.title, description: o.description, key_results });
            }
            return jsonText({ year, quarter, realisasi_metrik: real, objectives });
        }
    );

    // 6) Buat Objective (kalimat tujuan) untuk satu kuartal
    server.registerTool(
        'create_objective',
        {
            title: 'Create Objective',
            description: 'Buat Objective (goal kualitatif) untuk satu kuartal. Isi Key Result terukurnya lewat create_key_result.',
            inputSchema: {
                title: z.string().describe('kalimat tujuan, mis. "Jadi rujukan konten AI di Indonesia"'),
                year: z.number().int().optional().describe('default: tahun berjalan'),
                quarter: z.number().int().min(1).max(4).optional().describe('default: kuartal berjalan'),
                description: z.string().optional().describe('penjelasan singkat (opsional)'),
            },
        },
        async ({ title, year, quarter, description }) => {
            const cur = currentQuarter();
            year = year || cur.year;
            quarter = quarter || cur.quarter;
            const owner = await ownerId();
            const [[mx]] = await db.query(`SELECT COALESCE(MAX(position),-1)+1 pos FROM objectives WHERE year=? AND quarter=?`, [year, quarter]);
            const [r] = await db.query(
                `INSERT INTO objectives (year, quarter, title, description, position, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())`,
                [year, quarter, title, description || null, mx.pos, owner]);
            return jsonText({ ok: true, objective: { id: r.insertId, year, quarter, title } });
        }
    );

    // 7) Tambah Key Result terukur ke sebuah Objective
    server.registerTool(
        'create_key_result',
        {
            title: 'Create Key Result',
            description: 'Tambah Key Result ke Objective. source: "auto" (view/subscriber/omset dari data — wajib isi metric), "manual" (angka diisi tangan), atau "kartu" (realisasi dari kartu todolist tertaut yang selesai).',
            inputSchema: {
                objective_id: z.number().int().describe('id Objective (dari list_okr)'),
                title: z.string().describe('nama KR, mis. "Total view seluruh konten"'),
                source: z.enum(['auto', 'manual', 'kartu']).describe('sumber realisasi'),
                target: z.number().describe('angka target'),
                metric: z.enum(['view', 'subscriber', 'omset']).optional().describe('WAJIB bila source=auto'),
                unit: z.enum(['angka', 'rupiah', 'persen']).optional().describe('default angka; diabaikan bila source=kartu'),
            },
        },
        async ({ objective_id, title, source, target, metric, unit }) => {
            const [[obj]] = await db.query(`SELECT id FROM objectives WHERE id=?`, [objective_id]);
            if (!obj) return errText(`Objective id ${objective_id} tidak ditemukan.`);
            if (source === 'auto' && !OKR_METRICS.includes(metric))
                return errText('source=auto wajib menyertakan metric: view | subscriber | omset.');

            // Bersihkan kolom sesuai sumber — cerminan validasiKeyResult() di Laravel.
            let m = null, u = OKR_UNITS.includes(unit) ? unit : 'angka';
            if (source === 'auto') m = metric;
            else if (source === 'kartu') u = 'angka';

            const owner = await ownerId();
            const [[mx]] = await db.query(`SELECT COALESCE(MAX(position),-1)+1 pos FROM key_results WHERE objective_id=?`, [objective_id]);
            const [r] = await db.query(
                `INSERT INTO key_results (objective_id, title, source, metric, target, actual_manual, unit, owner_id, position, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, NOW(), NOW())`,
                [objective_id, title, source, m, target, u, owner, mx.pos, owner]);
            return jsonText({ ok: true, key_result: { id: r.insertId, objective_id, title, source, metric: m, target, unit: u } });
        }
    );

    // 8) Tautkan kartu todolist ke KR bersumber 'kartu' (langkah pencapaian goal)
    server.registerTool(
        'link_task_to_kr',
        {
            title: 'Link Task to Key Result',
            description: 'Jadikan sebuah task todolist sbg langkah menuju Key Result bersumber "kartu". Menyelesaikan task menggerakkan angka KR. key_result_id null untuk melepas tautan.',
            inputSchema: {
                task_id: z.number().int().describe('id task (harus di board "todolist")'),
                key_result_id: z.number().int().nullable().describe('id KR sumber "kartu", atau null untuk melepas'),
            },
        },
        async ({ task_id, key_result_id }) => {
            const [[task]] = await db.query(`SELECT id, category FROM pipelines WHERE id=? AND archived_at IS NULL`, [task_id]);
            if (!task) return errText(`Task id ${task_id} tidak ditemukan.`);
            // Gerbang sama dgn PipelineController::tautanKrValid(): todolist + KR 'kartu'.
            if (task.category !== 'todolist') return errText('Tautan OKR hanya untuk task di board "todolist".');

            if (key_result_id !== null) {
                const [[kr]] = await db.query(`SELECT id FROM key_results WHERE id=? AND source='kartu'`, [key_result_id]);
                if (!kr) return errText(`Key Result id ${key_result_id} tidak ada / bukan bersumber "kartu".`);
            }
            await db.query(`UPDATE pipelines SET key_result_id=?, updated_at=NOW() WHERE id=?`, [key_result_id, task_id]);
            return jsonText({ ok: true, task_id, key_result_id });
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
