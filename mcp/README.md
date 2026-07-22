# MCP / integrasi AI → System AI Preneur

Kirim tugas ke Kanban dari Claude, ChatGPT, atau Gemini. Semua menuju satu
endpoint bertoken di aplikasi:

- `POST /api/tasks` — buat kartu (`title` wajib; `description`, `board`, `column`, `assignee` opsional).
- `GET  /api/boards` — daftar board, kolom, dan user (agar AI memilih yang valid).

Gerbangnya **bearer token** `TASK_AGENT_TOKEN` (di `.env` aplikasi). Sudah dites
end-to-end; board/kolom default aman, assignee dicocokkan dari nama/email.

## 1. ChatGPT (Custom GPT Action) — paling gampang, tanpa Node
ChatGPT tidak menjalankan MCP lokal; ia memanggil API HTTP langsung.
1. ChatGPT → **Create a GPT** → tab **Configure** → **Actions** → **Create new action**.
2. **Import** / tempel isi [`openapi.yaml`](openapi.yaml). Ganti `servers.url` ke domain aplikasimu.
3. **Authentication** → **API Key** → Auth type **Bearer** → tempel `TASK_AGENT_TOKEN`.
4. Coba: *"buat tugas 'audi' di kanban"* → GPT memanggil `createTask`.

## 2. Claude Desktop / Claude Code — MCP stdio (butuh Node di laptop)
```bash
cd mcp && npm install
```
Tambahkan ke config MCP (Claude Desktop: `claude_desktop_config.json`; Claude Code: `~/.claude.json` atau `claude mcp add`):
```json
{
  "mcpServers": {
    "aipreneur-tasks": {
      "command": "node",
      "args": ["/ABSOLUTE/PATH/pipeline/mcp/task-server.mjs"],
      "env": {
        "SYSTEM_URL": "https://GANTI-KE-DOMAIN-ANDA",
        "TASK_AGENT_TOKEN": "isi-token-yang-sama"
      }
    }
  }
}
```
Restart klien → tool `create_task` & `list_boards` muncul.

## 3. Gemini CLI — MCP stdio (sama servernya)
Di `~/.gemini/settings.json`:
```json
{
  "mcpServers": {
    "aipreneur-tasks": {
      "command": "node",
      "args": ["/ABSOLUTE/PATH/pipeline/mcp/task-server.mjs"],
      "env": {
        "SYSTEM_URL": "https://GANTI-KE-DOMAIN-ANDA",
        "TASK_AGENT_TOKEN": "isi-token-yang-sama"
      }
    }
  }
}
```

## Catatan deploy
- Server MCP (Node) jalan **di laptop/klien**, bukan di shared hosting — jadi
  kendala "server tak jalan Node" tidak berlaku. Yang di server cuma endpoint PHP.
- Ganti `TASK_AGENT_TOKEN` berkala; jangan commit token asli.
