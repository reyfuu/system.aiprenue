#!/usr/bin/env node
// MCP server: teruskan "buat tugas" dari Claude / Gemini CLI ke System AI Preneur
// (endpoint Laravel /api/tasks). Transport stdio — dijalankan LOKAL oleh klien,
// jadi server produksi (shared hosting, tanpa Node) tak perlu menjalankan apa pun.
//
// Konfigurasi lewat environment:
//   SYSTEM_URL         URL app, mis. https://system.aipreneur.co.id (default localhost:8000)
//   TASK_AGENT_TOKEN   token yang sama dgn .env aplikasi
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const BASE = (process.env.SYSTEM_URL || "http://localhost:8000").replace(/\/+$/, "");
const TOKEN = process.env.TASK_AGENT_TOKEN || "";

// Pemanggil API tipis: selalu sertakan bearer token, uraikan JSON, lempar error jelas.
async function api(path, options = {}) {
  const res = await fetch(`${BASE}/api${path}`, {
    ...options,
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(options.headers || {}),
    },
  });
  const text = await res.text();
  let body;
  try { body = JSON.parse(text); } catch { body = text; }
  if (!res.ok) {
    const msg = typeof body === "string" ? body : (body.message || JSON.stringify(body));
    throw new Error(`HTTP ${res.status}: ${msg}`);
  }
  return body;
}

const server = new McpServer({ name: "aipreneur-tasks", version: "1.0.0" });

server.tool(
  "list_boards",
  "Lihat board Kanban, kolomnya, dan daftar user — untuk memilih board/kolom/assignee yang valid.",
  {},
  async () => {
    const data = await api("/boards");
    return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
  }
);

server.tool(
  "create_task",
  "Buat kartu tugas baru di Kanban System AI Preneur.",
  {
    title: z.string().describe("Judul tugas (wajib)."),
    description: z.string().optional().describe("Detail tugas (opsional)."),
    board: z.string().optional().describe("Key board Kanban (mis. 'kerja'). Kosong = board default."),
    column: z.string().optional().describe("Key kolom (mis. 'todo'). Kosong = kolom 'todo'/pertama."),
    assignee: z.string().optional().describe("Nama atau email penerima tugas (opsional)."),
  },
  async (args) => {
    const data = await api("/tasks", { method: "POST", body: JSON.stringify(args) });
    return {
      content: [{
        type: "text",
        text: `Tugas dibuat (#${data.id}): "${data.title}" di board ${data.board} / kolom ${data.column}` +
              (data.assigned_to ? ` untuk user #${data.assigned_to}` : "") + ".",
      }],
    };
  }
);

await server.connect(new StdioServerTransport());
