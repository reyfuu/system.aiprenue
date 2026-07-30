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

// Snapshot prioritas {name, color} dari tabel labels. Fallback ke warna default.
async function prioritySnapshot(name) {
    if (!name || !['Urgent', 'Penting'].includes(name)) return null;
    const [[label]] = await db.query(`SELECT name, color FROM labels WHERE name=? LIMIT 1`, [name]);
    return label || { name, color: name === 'Urgent' ? '#dc2626' : '#2563eb' };
}

const OKR_SOURCES = ['auto', 'manual', 'kartu'];
const OKR_METRICS = ['view', 'subscriber', 'omset'];
const OKR_UNITS = ['angka', 'rupiah', 'persen'];
const LIMIT = z.number().int().min(1).max(200).default(50);

// Bentuk Kanban lengkap untuk MCP. Definisi kolom wajib diambil dari
// board_columns, bukan disimpulkan dari task: kolom kosong tetap harus terlihat.
async function kanbanBoard(board) {
    const [[category]] = await db.query(
        `SELECT \`key\`, name, section FROM categories WHERE \`key\`=? AND type='kanban'`,
        [board]
    );
    if (!category) return null;

    const [columns] = await db.query(
        `SELECT id, \`key\`, name, position
         FROM board_columns WHERE board_key=? ORDER BY position, id`,
        [board]
    );
    const [tasks] = await db.query(
        `SELECT p.id, p.endorse AS title, p.progress AS column_key, p.done,
                p.deadline, p.score, p.labels, p.assigned_to, u.name AS assignee
         FROM pipelines p
         LEFT JOIN users u ON u.id=p.assigned_to
         WHERE p.category=? AND p.archived_at IS NULL
         ORDER BY p.position, p.id`,
        [board]
    );

    const grouped = new Map(columns.map((column) => [column.key, { ...column, tasks: [] }]));
    for (const task of tasks) {
        const column = grouped.get(task.column_key);
        if (column) column.tasks.push(task);
    }

    return {
        board: category,
        columns: [...grouped.values()].map((column) => ({
            ...column,
            task_count: column.tasks.length,
        })),
        task_count: tasks.length,
    };
}

// Modul non-board diberikan sebagai tool eksplisit agar model dapat menemukan
// kapabilitasnya tanpa mendapat akses SQL generik. Semua nilai dinamis tetap
// masuk sebagai parameter query, bukan interpolasi.
function registerSystemReadTools(server) {
    server.registerTool(
        'get_dashboard_summary',
        { title: 'Dashboard Summary', description: 'Ringkasan seluruh sistem: omzet order, sales, kanban, content, insight, dan pembukuan.', inputSchema: {} },
        async () => {
            const [[orders]] = await db.query(`SELECT COUNT(*) total, COALESCE(SUM(total_idr),0) omzet_idr, COALESCE(SUM(total_usd),0) omzet_usd FROM orders`);
            const [[sales]] = await db.query(`SELECT COUNT(*) total, COALESCE(SUM(amount_idr),0) nilai_idr, COALESCE(SUM(amount_usd),0) nilai_usd FROM pipelines p JOIN categories c ON c.\`key\`=p.category WHERE c.type='pipeline' AND p.archived_at IS NULL`);
            const [[kanban]] = await db.query(`SELECT COUNT(*) total, SUM(completed_at IS NOT NULL) selesai FROM pipelines p JOIN categories c ON c.\`key\`=p.category WHERE c.type='kanban' AND p.archived_at IS NULL`);
            const [[content]] = await db.query(`SELECT COUNT(*) total, SUM(progress='published') published FROM contents`);
            const [[finance]] = await db.query(`SELECT COALESCE(SUM(CASE WHEN type='pemasukan' THEN amount_idr ELSE 0 END),0) pemasukan, COALESCE(SUM(CASE WHEN type='pengeluaran' THEN amount_idr ELSE 0 END),0) pengeluaran FROM transactions`);
            const [[insight]] = await db.query(`SELECT COUNT(*) konten, COALESCE(SUM(views),0) views FROM insight_contents`);
            return jsonText({ orders, sales, kanban, content, finance: { ...finance, laba: Number(finance.pemasukan) - Number(finance.pengeluaran) }, insight });
        }
    );

    server.registerTool(
        'list_orders',
        {
            title: 'List Orders',
            description: 'Daftar order terbaru beserta customer, pembayaran, deadline, dan nilai.',
            inputSchema: { limit: LIMIT, account: z.enum(['fk', 'ai_preneur']).optional() },
        },
        async ({ limit, account }) => {
            const where = account ? 'WHERE account=?' : '';
            const args = account ? [account, limit] : [limit];
            const [rows] = await db.query(
                `SELECT id, tipe_order, account, nama_customer, telepon, email, tanggal_deadline,
                        tipe_pembayaran, tanggal_bayar, total_idr, total_usd, invoice
                 FROM orders ${where} ORDER BY id DESC LIMIT ?`, args);
            return jsonText({ count: rows.length, orders: rows });
        }
    );

    server.registerTool(
        'list_finance',
        {
            title: 'List Finance',
            description: 'Ringkasan dan transaksi Pembukuan pada rentang tanggal.',
            inputSchema: {
                start: z.string().optional().describe('YYYY-MM-DD'),
                end: z.string().optional().describe('YYYY-MM-DD'),
                limit: LIMIT,
            },
        },
        async ({ start, end, limit }) => {
            const where = [], args = [];
            if (start) { where.push('date>=?'); args.push(start); }
            if (end) { where.push('date<=?'); args.push(end); }
            const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
            const [[summary]] = await db.query(
                `SELECT COALESCE(SUM(CASE WHEN type='pemasukan' THEN amount_idr ELSE 0 END),0) pemasukan,
                        COALESCE(SUM(CASE WHEN type='pengeluaran' THEN amount_idr ELSE 0 END),0) pengeluaran
                 FROM transactions ${clause}`, args);
            const [transactions] = await db.query(
                `SELECT id, type, category, description, amount_idr, date
                 FROM transactions ${clause} ORDER BY date DESC, id DESC LIMIT ?`, [...args, limit]);
            return jsonText({ summary: { ...summary, laba: Number(summary.pemasukan) - Number(summary.pengeluaran) }, transactions });
        }
    );

    server.registerTool(
        'list_content',
        {
            title: 'List Content',
            description: 'Daftar rencana produksi Content beserta editor, progress, jadwal, dan tautan hasil.',
            inputSchema: { progress: z.enum(['draft', 'script', 'editing', 'review', 'scheduled', 'published']).optional(), limit: LIMIT },
        },
        async ({ progress, limit }) => {
            const where = progress ? 'WHERE progress=?' : '';
            const args = progress ? [progress, limit] : [limit];
            const [rows] = await db.query(
                `SELECT id, comp, jenis_postingan, kategori, inti_pesan, editor, progress,
                        tanggal_upload, link_hasil_editing, caption
                 FROM contents ${where} ORDER BY id DESC LIMIT ?`, args);
            return jsonText({ count: rows.length, contents: rows });
        }
    );

    server.registerTool(
        'list_scripts',
        {
            title: 'List Scripts',
            description: 'Daftar naskah Script terbaru. Body disertakan agar AI dapat melanjutkan atau meninjau naskah.',
            inputSchema: { brand: z.enum(['raveloux', 'rave_tailor', 'fk']).optional(), limit: LIMIT },
        },
        async ({ brand, limit }) => {
            const where = brand ? 'WHERE brand=?' : '';
            const args = brand ? [brand, limit] : [limit];
            const [rows] = await db.query(
                `SELECT id, brand, title, body, generated_for, created_at FROM scripts ${where} ORDER BY id DESC LIMIT ?`, args);
            return jsonText({ count: rows.length, scripts: rows });
        }
    );

    server.registerTool(
        'list_insights',
        {
            title: 'List Insights',
            description: 'Ringkasan performa Insight Instagram/YouTube dan konten teratas pada rentang tanggal.',
            inputSchema: {
                platform: z.enum(['instagram', 'youtube']).optional(),
                start: z.string().optional(),
                end: z.string().optional(),
                limit: LIMIT,
            },
        },
        async ({ platform, start, end, limit }) => {
            const where = [], args = [];
            if (platform) { where.push('platform=?'); args.push(platform); }
            if (start) { where.push('published_at>=?'); args.push(start); }
            if (end) { where.push('published_at<=?'); args.push(`${end} 23:59:59`); }
            const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
            const [[summary]] = await db.query(
                `SELECT COUNT(*) konten, COALESCE(SUM(views),0) views, COALESCE(SUM(likes+comments+shares+saves),0) interactions
                 FROM insight_contents ${clause}`, args);
            const [contents] = await db.query(
                `SELECT id, platform, judul, content_type, published_at, views, reach, likes, comments, shares, saves, followers_gained
                 FROM insight_contents ${clause} ORDER BY views DESC LIMIT ?`, [...args, limit]);
            return jsonText({ summary, contents });
        }
    );

    server.registerTool(
        'list_users',
        {
            title: 'List Users',
            description: 'Daftar user dan perannya. Tidak pernah mengirim password atau remember token.',
            inputSchema: { role: z.enum(['owner', 'manager', 'it', 'admin', 'staff']).optional() },
        },
        async ({ role }) => {
            const [rows] = role
                ? await db.query(`SELECT id, name, email, role, created_at FROM users WHERE role=? ORDER BY name`, [role])
                : await db.query(`SELECT id, name, email, role, created_at FROM users ORDER BY name`);
            return jsonText({ count: rows.length, users: rows });
        }
    );

    server.registerTool(
        'list_absences',
        {
            title: 'List Absences',
            description: 'Daftar pengajuan absensi/cuti beserta user dan status persetujuan.',
            inputSchema: { status: z.enum(['menunggu', 'disetujui', 'ditolak']).optional(), limit: LIMIT },
        },
        async ({ status, limit }) => {
            const where = status ? 'WHERE a.status=?' : '';
            const args = status ? [status, limit] : [limit];
            const [rows] = await db.query(
                `SELECT a.id, a.user_id, u.name AS user_name, a.type, a.start_date, a.end_date, a.reason, a.status
                 FROM absences a JOIN users u ON u.id=a.user_id ${where} ORDER BY a.id DESC LIMIT ?`, args);
            return jsonText({ count: rows.length, absences: rows });
        }
    );

    server.registerTool(
        'list_mindmaps',
        {
            title: 'List Mindmaps',
            description: 'Daftar mindmap. Isi data JSON opsional karena dapat berukuran besar.',
            inputSchema: { include_data: z.boolean().default(false), limit: LIMIT },
        },
        async ({ include_data, limit }) => {
            const fields = include_data ? ', m.data' : '';
            const [rows] = await db.query(
                `SELECT m.id, m.title, m.user_id, u.name AS user_name, m.updated_at${fields}
                 FROM mindmaps m LEFT JOIN users u ON u.id=m.user_id ORDER BY m.updated_at DESC LIMIT ?`, [limit]);
            return jsonText({ count: rows.length, mindmaps: rows });
        }
    );

    server.registerTool(
        'list_okr',
        {
            title: 'List OKR',
            description: 'Objective dan Key Result satu kuartal beserta realisasi otomatis/manual/Kanban, target omzet, dan prioritas.',
            inputSchema: {
                year: z.number().int().min(2000).max(2100).optional(),
                quarter: z.number().int().min(1).max(4).optional(),
            },
        },
        async ({ year, quarter }) => {
            const current = currentQuarter();
            year ??= current.year;
            quarter ??= current.quarter;
            const metrics = await okrRealisasi(year, quarter);
            const [objectives] = await db.query(
                `SELECT o.id, o.title, o.description, o.position, o.priority,
                        o.omset_target, o.created_by,
                        COALESCE((SELECT SUM(amount_idr) FROM transactions WHERE type='pemasukan' AND date BETWEEN ? AND ?),0) AS omset_actual,
                        u.name AS omset_owner_name
                 FROM objectives o
                 LEFT JOIN users u ON u.id=o.omset_owner_id
                 WHERE o.year=? AND o.quarter=?
                 ORDER BY o.position, o.id`,
                [...quarterRange(year, quarter), year, quarter]);
            for (const objective of objectives) {
                const [krs] = await db.query(
                    `SELECT kr.id, kr.title, kr.source, kr.metric, kr.target, kr.actual_manual,
                            kr.unit, kr.board_key, kr.priority, kr.owner_id, u.name AS owner_name
                     FROM key_results kr
                     LEFT JOIN users u ON u.id=kr.owner_id
                     WHERE kr.objective_id=? ORDER BY kr.position, kr.id`, [objective.id]);
                for (const kr of krs) {
                    let actual = Number(kr.actual_manual || 0);
                    if (kr.source === 'auto') actual = Number(metrics[kr.metric] || 0);
                    if (kr.source === 'kartu') {
                        const [[done]] = await db.query(`SELECT COUNT(*) n FROM pipelines WHERE key_result_id=? AND completed_at IS NOT NULL`, [kr.id]);
                        actual = Number(done.n);
                    }
                    kr.actual = actual;
                    kr.percent = pct(actual, kr.target);
                }
                objective.key_results = krs;
                objective.omset_percent = pct(Number(objective.omset_actual), Number(objective.omset_target));
                const scored = krs.map((kr) => kr.percent).filter((v) => v !== null).map((v) => Math.min(100, v));
                if (objective.omset_percent !== null) scored.push(Math.min(100, objective.omset_percent));
                objective.progress = scored.length ? Math.round(scored.reduce((a, b) => a + b, 0) / scored.length * 10) / 10 : null;
            }
            return jsonText({ year, quarter, metrics, objectives });
        }
    );

    server.registerTool(
        'list_kpi',
        {
            title: 'List KPI',
            description: 'Target dan realisasi penyelesaian kartu per board pada satu kuartal.',
            inputSchema: {
                year: z.number().int().min(2000).max(2100).optional(),
                quarter: z.number().int().min(1).max(4).optional(),
            },
        },
        async ({ year, quarter }) => {
            const current = currentQuarter();
            year ??= current.year;
            quarter ??= current.quarter;
            const [start, end] = quarterRange(year, quarter);
            const [rows] = await db.query(
                `SELECT c.\`key\` board_key, c.name,
                        COALESCE(t.target_done,0) target,
                        COUNT(p.id) total,
                        SUM(p.completed_at IS NOT NULL) selesai
                 FROM categories c
                 LEFT JOIN board_quarter_targets t ON t.board_key=c.\`key\` AND t.year=? AND t.quarter=?
                 LEFT JOIN pipelines p ON p.category=c.\`key\` AND p.deadline BETWEEN ? AND ? AND p.archived_at IS NULL
                 WHERE c.type='kanban'
                 GROUP BY c.\`key\`, c.name, t.target_done ORDER BY c.name`, [year, quarter, start, end]);
            return jsonText({ year, quarter, boards: rows.map((row) => ({ ...row, percent: pct(Number(row.selesai), Number(row.target)) })) });
        }
    );

    server.registerTool(
        'list_tracking',
        {
            title: 'List Tracking',
            description: 'Tracking deadline dan ketepatan card Kanban, termasuk PIC.',
            inputSchema: { start: z.string().optional(), end: z.string().optional(), limit: LIMIT },
        },
        async ({ start, end, limit }) => {
            const where = [`c.type='kanban'`, 'p.archived_at IS NULL'], args = [];
            if (start) { where.push('p.deadline>=?'); args.push(start); }
            if (end) { where.push('p.deadline<=?'); args.push(end); }
            const [rows] = await db.query(
                `SELECT p.id, p.endorse AS title, c.name AS board, p.progress, p.deadline, p.completed_at,
                        u.name AS assignee,
                        CASE WHEN p.deadline IS NULL THEN NULL
                             WHEN p.completed_at IS NOT NULL AND DATE(p.completed_at)<=p.deadline THEN 'tepat'
                             WHEN p.completed_at IS NOT NULL THEN 'terlambat'
                             WHEN p.deadline<CURDATE() THEN 'lewat' ELSE NULL END AS ketepatan
                 FROM pipelines p JOIN categories c ON c.\`key\`=p.category
                 LEFT JOIN users u ON u.id=p.assigned_to
                 WHERE ${where.join(' AND ')} ORDER BY p.deadline IS NULL, p.deadline, p.id LIMIT ?`, [...args, limit]);
            return jsonText({ count: rows.length, cards: rows });
        }
    );

    server.registerTool(
        'list_access',
        { title: 'List Access', description: 'Matriks hak akses menu per role dari konfigurasi database.', inputSchema: {} },
        async () => {
            const [rows] = await db.query(`SELECT role, menu, can_manage FROM role_menu_access ORDER BY role, menu`);
            return jsonText({ access: rows });
        }
    );

    server.registerTool(
        'list_inventory',
        {
            title: 'List Inventory',
            description: 'Daftar inventaris Pembukuan dan nilai total tiap item.',
            inputSchema: { limit: LIMIT },
        },
        async ({ limit }) => {
            const [rows] = await db.query(
                `SELECT id, name, qty, unit_value_idr, month, qty*unit_value_idr AS total_value_idr
                 FROM inventories ORDER BY month DESC, id DESC LIMIT ?`, [limit]);
            return jsonText({ count: rows.length, inventory: rows });
        }
    );

    server.registerTool(
        'get_upload_status',
        { title: 'Upload Status', description: 'Status data terakhir yang masuk ke modul Insight melalui upload/ingest.', inputSchema: {} },
        async () => {
            const [[contents]] = await db.query(`SELECT COUNT(*) total, MAX(updated_at) last_updated FROM insight_contents`);
            const [[accounts]] = await db.query(`SELECT COUNT(*) total, MAX(updated_at) last_updated FROM insight_accounts`);
            return jsonText({ insight_contents: contents, insight_accounts: accounts });
        }
    );

    server.registerTool(
        'list_audit_logs',
        {
            title: 'List Audit Logs',
            description: 'Riwayat audit semua aksi mutasi di sistem. Filter: user, aksi, model, rentang tanggal.',
            inputSchema: {
                user_id: z.number().int().positive().optional(),
                action: z.enum(['create', 'update', 'delete', 'archive', 'restore', 'progress', 'approve', 'reject']).optional(),
                model_type: z.string().optional().describe('Nama model, mis. Pipeline, Objective, User'),
                start: z.string().optional().describe('YYYY-MM-DD'),
                end: z.string().optional().describe('YYYY-MM-DD'),
                limit: LIMIT,
            },
        },
        async ({ user_id, action, model_type, start, end, limit }) => {
            const where = [], args = [];
            if (user_id) { where.push('a.user_id=?'); args.push(user_id); }
            if (action) { where.push('a.action=?'); args.push(action); }
            if (model_type) { where.push('a.model_type=?'); args.push(model_type); }
            if (start) { where.push('DATE(a.created_at)>=?'); args.push(start); }
            if (end) { where.push('DATE(a.created_at)<=?'); args.push(end); }
            const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
            const [rows] = await db.query(
                `SELECT a.id, a.user_id, u.name AS user_name, a.user_role, a.action, a.model_type, a.model_id, a.model_name, a.ip_address, a.created_at
                 FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
                 ${clause} ORDER BY a.id DESC LIMIT ?`, [...args, limit]);
            return jsonText({ count: rows.length, logs: rows });
        }
    );
}

function registerSystemWriteTools(server) {
    server.registerTool(
        'create_objective',
        {
            title: 'Create Objective',
            description: 'Buat Objective perusahaan untuk satu kuartal dengan target omzet opsional.',
            inputSchema: {
                year: z.number().int().min(2000).max(2100),
                quarter: z.number().int().min(1).max(4),
                title: z.string().min(1).max(255),
                description: z.string().max(2000).optional(),
                omset_target: z.number().nonnegative().optional().describe('Target omzet kuartal (Rp)'),
                omset_owner_id: z.number().int().positive().optional().describe('User ID PIC target omzet'),
                priority: z.string().max(50).optional().describe('Nama prioritas: Urgent atau Penting'),
            },
        },
        async (a) => {
            const creator = await ownerId();
            const [[position]] = await db.query(
                `SELECT COALESCE(MAX(position),0)+1 n FROM objectives WHERE year=? AND quarter=?`, [a.year, a.quarter]);
            const priority = a.priority ? await prioritySnapshot(a.priority) : null;
            const [result] = await db.query(
                `INSERT INTO objectives (year, quarter, title, description, omset_target, omset_owner_id, priority, position, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
                [a.year, a.quarter, a.title, a.description || null,
                 a.omset_target || null, a.omset_owner_id || null,
                 priority ? JSON.stringify(priority) : null,
                 position.n, creator]);
            return jsonText({ ok: true, objective: { id: result.insertId, year: a.year, quarter: a.quarter, title: a.title } });
        }
    );

    server.registerTool(
        'update_objective',
        {
            title: 'Update Objective',
            description: 'Ubah Objective yang sudah ada. Isi minimal satu field selain id.',
            inputSchema: {
                id: z.number().int().positive(),
                title: z.string().min(1).max(255).optional(),
                description: z.string().max(2000).optional(),
                omset_target: z.number().nonnegative().optional(),
                omset_owner_id: z.number().int().positive().optional(),
                priority: z.string().max(50).optional(),
            },
        },
        async (a) => {
            const [[obj]] = await db.query(`SELECT id FROM objectives WHERE id=?`, [a.id]);
            if (!obj) return errText(`Objective id ${a.id} tidak ditemukan.`);
            const sets = [], vals = [];
            if (a.title !== undefined) { sets.push('title=?'); vals.push(a.title); }
            if (a.description !== undefined) { sets.push('description=?'); vals.push(a.description); }
            if (a.omset_target !== undefined) { sets.push('omset_target=?'); vals.push(a.omset_target); }
            if (a.omset_owner_id !== undefined) { sets.push('omset_owner_id=?'); vals.push(a.omset_owner_id); }
            if (a.priority !== undefined) {
                const p = await prioritySnapshot(a.priority);
                sets.push('priority=?'); vals.push(p ? JSON.stringify(p) : null);
            }
            if (!sets.length) return errText('Tidak ada field yang diubah.');
            sets.push('updated_at=NOW()');
            await db.query(`UPDATE objectives SET ${sets.join(', ')} WHERE id=?`, [...vals, a.id]);
            return jsonText({ ok: true, objective_id: a.id });
        }
    );

    server.registerTool(
        'delete_objective',
        {
            title: 'Delete Objective',
            description: 'Hapus Objective beserta seluruh Key Result di dalamnya.',
            inputSchema: { id: z.number().int().positive() },
        },
        async (a) => {
            const [[obj]] = await db.query(`SELECT id, title FROM objectives WHERE id=?`, [a.id]);
            if (!obj) return errText(`Objective id ${a.id} tidak ditemukan.`);
            await db.query(`DELETE FROM objectives WHERE id=?`, [a.id]);
            return jsonText({ ok: true, deleted: obj.title });
        }
    );

    server.registerTool(
        'create_key_result',
        {
            title: 'Create Key Result',
            description: 'Tambah Key Result ke Objective. auto memakai metric view/subscriber; manual memakai actual_manual; kartu dihitung dari card selesai.',
            inputSchema: {
                objective_id: z.number().int().positive(),
                title: z.string().min(1).max(255),
                source: z.enum(OKR_SOURCES),
                metric: z.enum(OKR_METRICS).nullable().optional(),
                board_key: z.string().nullable().optional(),
                target: z.number().nonnegative(),
                unit: z.enum(OKR_UNITS),
                owner_id: z.number().int().positive().optional().describe('User ID penanggung jawab KR'),
                priority_name: z.string().max(50).optional().describe('Nama prioritas: Urgent atau Penting'),
            },
        },
        async (a) => {
            const [[objective]] = await db.query(`SELECT id FROM objectives WHERE id=?`, [a.objective_id]);
            if (!objective) return errText(`Objective id ${a.objective_id} tidak ditemukan.`);
            if (a.source === 'auto' && !a.metric) return errText('KR otomatis wajib memiliki metric.');
            if (a.source === 'kartu' && !a.board_key) return errText('KR Kanban wajib memiliki board_key.');
            if (a.board_key) {
                const [[board]] = await db.query(`SELECT \`key\` FROM categories WHERE \`key\`=? AND type='kanban'`, [a.board_key]);
                if (!board) return errText(`Board Kanban "${a.board_key}" tidak ditemukan.`);
            }
            const creator = await ownerId();
            const [[position]] = await db.query(
                `SELECT COALESCE(MAX(position),0)+1 n FROM key_results WHERE objective_id=?`, [a.objective_id]);
            const metric = a.source === 'auto' ? a.metric : null;
            const boardKey = a.source === 'kartu' ? a.board_key : null;
            const unit = a.source === 'auto' ? (a.metric === 'omset' ? 'rupiah' : 'angka') : (a.source === 'kartu' ? 'angka' : a.unit);
            const priority = a.priority_name ? await prioritySnapshot(a.priority_name) : null;
            const owner = a.owner_id || creator;
            const [result] = await db.query(
                `INSERT INTO key_results
                    (objective_id, title, source, board_key, metric, target, unit, priority, owner_id, position, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
                [a.objective_id, a.title, a.source, boardKey, metric, a.target, unit,
                 priority ? JSON.stringify(priority) : null, owner, position.n, creator]);
            return jsonText({ ok: true, key_result: { id: result.insertId, ...a, metric, board_key: boardKey, unit } });
        }
    );

    server.registerTool(
        'update_key_result',
        {
            title: 'Update Key Result',
            description: 'Ubah Key Result. Isi minimal satu field selain id.',
            inputSchema: {
                id: z.number().int().positive(),
                title: z.string().min(1).max(255).optional(),
                target: z.number().nonnegative().optional(),
                unit: z.enum(OKR_UNITS).optional(),
                owner_id: z.number().int().positive().optional(),
                priority_name: z.string().max(50).optional(),
                board_key: z.string().nullable().optional(),
            },
        },
        async (a) => {
            const [[kr]] = await db.query(`SELECT id FROM key_results WHERE id=?`, [a.id]);
            if (!kr) return errText(`Key Result id ${a.id} tidak ditemukan.`);
            const sets = [], vals = [];
            if (a.title !== undefined) { sets.push('title=?'); vals.push(a.title); }
            if (a.target !== undefined) { sets.push('target=?'); vals.push(a.target); }
            if (a.unit !== undefined) { sets.push('unit=?'); vals.push(a.unit); }
            if (a.owner_id !== undefined) { sets.push('owner_id=?'); vals.push(a.owner_id); }
            if (a.board_key !== undefined) {
                if (a.board_key) {
                    const [[b]] = await db.query(`SELECT \`key\` FROM categories WHERE \`key\`=? AND type='kanban'`, [a.board_key]);
                    if (!b) return errText(`Board "${a.board_key}" tidak ditemukan.`);
                }
                sets.push('board_key=?'); vals.push(a.board_key);
            }
            if (a.priority_name !== undefined) {
                const p = await prioritySnapshot(a.priority_name);
                sets.push('priority=?'); vals.push(p ? JSON.stringify(p) : null);
            }
            if (!sets.length) return errText('Tidak ada field yang diubah.');
            sets.push('updated_at=NOW()');
            await db.query(`UPDATE key_results SET ${sets.join(', ')} WHERE id=?`, [...vals, a.id]);
            return jsonText({ ok: true, key_result_id: a.id });
        }
    );

    server.registerTool(
        'delete_key_result',
        {
            title: 'Delete Key Result',
            description: 'Hapus Key Result. Kartu yang tertaut tidak ikut terhapus.',
            inputSchema: { id: z.number().int().positive() },
        },
        async (a) => {
            const [[kr]] = await db.query(`SELECT id, title FROM key_results WHERE id=?`, [a.id]);
            if (!kr) return errText(`Key Result id ${a.id} tidak ditemukan.`);
            await db.query(`DELETE FROM key_results WHERE id=?`, [a.id]);
            return jsonText({ ok: true, deleted: kr.title });
        }
    );

    server.registerTool(
        'link_task_to_kr',
        {
            title: 'Link Task to Key Result',
            description: 'Tautkan card Kanban ke Key Result bersumber kartu.',
            inputSchema: {
                task_id: z.number().int().positive(),
                key_result_id: z.number().int().positive(),
            },
        },
        async (a) => {
            const [[kr]] = await db.query(`SELECT id, source, board_key FROM key_results WHERE id=?`, [a.key_result_id]);
            if (!kr) return errText(`Key Result id ${a.key_result_id} tidak ditemukan.`);
            if (kr.source !== 'kartu') return errText('Hanya Key Result bersumber Kanban yang dapat menerima card.');
            const [[task]] = await db.query(
                `SELECT p.id, p.category FROM pipelines p JOIN categories c ON c.\`key\`=p.category
                 WHERE p.id=? AND c.type='kanban' AND p.archived_at IS NULL`, [a.task_id]);
            if (!task) return errText(`Card Kanban id ${a.task_id} tidak ditemukan.`);
            if (kr.board_key && task.category !== kr.board_key) {
                return errText(`Card harus berasal dari board "${kr.board_key}".`);
            }
            await db.query(`UPDATE pipelines SET key_result_id=?, updated_at=NOW() WHERE id=?`, [a.key_result_id, a.task_id]);
            return jsonText({ ok: true, task_id: a.task_id, key_result_id: a.key_result_id });
        }
    );

    server.registerTool(
        'create_transaction',
        {
            title: 'Create Transaction',
            description: 'Tambah transaksi Pembukuan. Omzet OKR otomatis ikut berubah untuk transaksi pemasukan.',
            inputSchema: {
                type: z.enum(['pemasukan', 'pengeluaran']),
                category: z.string().min(1).max(255),
                description: z.string().max(255).optional(),
                amount_idr: z.number().nonnegative(),
                date: z.string().describe('YYYY-MM-DD'),
            },
        },
        async (a) => {
            const [result] = await db.query(
                `INSERT INTO transactions (type, category, description, amount_idr, date, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())`,
                [a.type, a.category, a.description || null, a.amount_idr, a.date]);
            return jsonText({ ok: true, transaction: { id: result.insertId, ...a } });
        }
    );

    server.registerTool(
        'create_order',
        {
            title: 'Create Order',
            description: 'Buat Order baru. Bukti pembayaran dan output dapat dilengkapi dari aplikasi.',
            inputSchema: {
                nama_customer: z.string().min(1).max(255),
                tipe_order: z.enum(['coaching_1on1', 'coaching_perusahaan', 'endorse', 'speaker', 'agency']),
                account: z.enum(['fk', 'ai_preneur']).default('fk'),
                tanggal_deadline: z.string().nullable().optional(),
                telepon: z.string().max(50).optional(),
                email: z.string().email().optional(),
                kota: z.string().max(255).optional(),
                alamat: z.string().optional(),
                tipe_pembayaran: z.enum(['full', 'dp']).default('full'),
                tanggal_bayar: z.string().nullable().optional(),
                total_idr: z.number().nonnegative().default(0),
                total_usd: z.number().nonnegative().default(0),
            },
        },
        async (a) => {
            const [result] = await db.query(
                `INSERT INTO orders
                    (nama_customer, tipe_order, account, tanggal_deadline, telepon, email, kota, alamat,
                     tipe_pembayaran, tanggal_bayar, total_idr, total_usd, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
                [a.nama_customer, a.tipe_order, a.account, a.tanggal_deadline || null, a.telepon || null,
                 a.email || null, a.kota || null, a.alamat || null, a.tipe_pembayaran,
                 a.tanggal_bayar || null, a.total_idr, a.total_usd]);
            return jsonText({ ok: true, order: { id: result.insertId, ...a } });
        }
    );

    server.registerTool(
        'create_script',
        {
            title: 'Create Script',
            description: 'Simpan naskah baru ke modul Script.',
            inputSchema: {
                brand: z.enum(['raveloux', 'rave_tailor', 'fk']),
                title: z.string().min(1).max(255),
                body: z.string().min(1),
                generated_for: z.string().describe('YYYY-MM-DD'),
            },
        },
        async (a) => {
            const [result] = await db.query(
                `INSERT INTO scripts (brand, title, body, generated_for, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())`,
                [a.brand, a.title, a.body, a.generated_for]);
            return jsonText({ ok: true, script: { id: result.insertId, brand: a.brand, title: a.title, generated_for: a.generated_for } });
        }
    );

    server.registerTool(
        'create_content',
        {
            title: 'Create Content',
            description: 'Buat item baru dalam kalender produksi Content.',
            inputSchema: {
                comp: z.string().max(255).optional(),
                jenis_postingan: z.string().max(255).optional(),
                kategori: z.string().max(255).optional(),
                inti_pesan: z.string().optional(),
                hook_material: z.string().optional(),
                editor: z.string().max(255).optional(),
                progress: z.enum(['draft', 'script', 'editing', 'review', 'scheduled', 'published']).default('draft'),
                tanggal_upload: z.string().nullable().optional(),
                caption: z.string().optional(),
            },
        },
        async (a) => {
            const [result] = await db.query(
                `INSERT INTO contents
                    (comp, jenis_postingan, kategori, inti_pesan, hook_material, editor, progress, tanggal_upload, caption, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
                [a.comp || null, a.jenis_postingan || null, a.kategori || null, a.inti_pesan || null,
                 a.hook_material || null, a.editor || null, a.progress, a.tanggal_upload || null, a.caption || null]);
            return jsonText({ ok: true, content: { id: result.insertId, ...a } });
        }
    );

    server.registerTool(
        'create_absence',
        {
            title: 'Create Absence',
            description: 'Ajukan cuti, sakit, atau izin untuk user.',
            inputSchema: {
                user_id: z.number().int().positive(),
                type: z.enum(['cuti', 'sakit', 'izin']),
                start_date: z.string(),
                end_date: z.string().nullable().optional(),
                reason: z.string().optional(),
            },
        },
        async (a) => {
            const [[user]] = await db.query(`SELECT id FROM users WHERE id=?`, [a.user_id]);
            if (!user) return errText(`User id ${a.user_id} tidak ditemukan.`);
            const [result] = await db.query(
                `INSERT INTO absences (user_id, type, start_date, end_date, reason, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'menunggu', NOW(), NOW())`,
                [a.user_id, a.type, a.start_date, a.end_date || null, a.reason || null]);
            return jsonText({ ok: true, absence: { id: result.insertId, status: 'menunggu', ...a } });
        }
    );
}

// Bangun instance MCP server + daftarkan tools (fresh per request, mode stateless)
function buildServer() {
    const server = new McpServer({ name: 'system-aipreneur', version: '0.3.0' });
    registerSystemReadTools(server);
    registerSystemWriteTools(server);

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

    // 2) Semua board lengkap dalam satu panggilan. Ini jalur utama untuk
    // manager yang perlu melihat keseluruhan Kanban tanpa menebak board key
    // atau memanggil get_kanban_board satu per satu.
    server.registerTool(
        'list_all_kanban',
        {
            title: 'List All Kanban',
            description: 'Ambil SEMUA board Kanban sekaligus, lengkap dengan seluruh kolom dalam urutan asli (termasuk kolom kosong) dan task aktif pada tiap kolom.',
            inputSchema: {},
        },
        async () => {
            const [rows] = await db.query(
                `SELECT \`key\` FROM categories WHERE type='kanban' ORDER BY name, id`
            );
            const boards = [];
            for (const row of rows) {
                const board = await kanbanBoard(row.key);
                if (board) boards.push(board);
            }
            return jsonText({ board_count: boards.length, boards });
        }
    );

    // 3) Board lengkap: seluruh kolom (termasuk kosong) + task per kolom.
    server.registerTool(
        'get_kanban_board',
        {
            title: 'Get Kanban Board',
            description: 'Ambil satu board Kanban lengkap: seluruh kolom dalam urutan asli, termasuk kolom kosong, beserta task pada tiap kolom. Gunakan ini saat perlu melihat struktur board.',
            inputSchema: { board: z.string().describe('board key dari list_boards') },
        },
        async ({ board }) => {
            const result = await kanbanBoard(board);
            return result ? jsonText(result) : errText(`Board kanban "${board}" tidak ditemukan.`);
        }
    );

    // 4) Daftar task dalam satu board. columns ikut dikirim agar klien lama yang
    // masih memakai list_tasks juga tidak kehilangan kolom kosong.
    server.registerTool(
        'list_tasks',
        {
            title: 'List Tasks',
            description: 'Daftar task aktif dalam satu board kanban (pakai board key dari list_boards).',
            inputSchema: { board: z.string().describe('board key, mis. "hrd"') },
        },
        async ({ board }) => {
            const result = await kanbanBoard(board);
            if (!result) return errText(`Board kanban "${board}" tidak ditemukan.`);
            return jsonText({
                board,
                columns: result.columns.map(({ tasks, ...column }) => column),
                tasks: result.columns.flatMap((column) => column.tasks),
            });
        }
    );

    // 5) Buat task baru di board
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

    // 6) Ubah task yang sudah ada (by id): deadline, column, done, atau title
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
