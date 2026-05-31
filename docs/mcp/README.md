# NeNe Vault MCP Integration

NeNe Vault exposes its document archive through the
[Model Context Protocol (MCP)](https://modelcontextprotocol.io/), allowing
Claude and other MCP-compatible AI assistants to search, read, and update
received documents directly.

## Available tools

| Tool | Type | Purpose |
|---|---|---|
| `searchVaultDocuments` | read | Search by date / amount / counterparty (電帳法 §4) |
| `getVaultDocumentById` | read | Get document detail + SHA-256 |
| `getVaultDocumentHistory` | read | Full audit trail (versions + events) |
| `listVaultAuditEvents` | read | Browse compliance log |
| `ocrSuggestVaultDocument` | read | OCR → suggest metadata (never auto-applied) |
| `exportVaultDocumentsCsv` | read | Export manifest CSV for tax audit |
| `updateVaultDocumentMetadata` | **write** | Update date / amount / counterparty / category |
| `voidVaultDocument` | **write** | Void with mandatory reason (audit-logged) |
| `restoreVaultDocument` | **write** | Restore a voided document |

Write tools require `NENE2_LOCAL_JWT_SECRET` to be set in the server environment.

---

## Prerequisites

1. NeNe Vault running at a reachable URL (e.g. `http://localhost:8600`)
2. A bearer token — generate one with:

```sh
php vendor/hideyukimori/nene2/tools/issue-jwt.php \
  --secret "$NENE2_LOCAL_JWT_SECRET" \
  --sub mcp \
  --role admin \
  --org-id 1 \
  --ttl 315360000
```

---

## Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json`
(macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "nene-vault": {
      "command": "php",
      "args": ["/absolute/path/to/nene-vault/tools/local-mcp-server.php"],
      "env": {
        "NENE2_LOCAL_API_BASE_URL": "http://localhost:8600",
        "NENE2_LOCAL_JWT_SECRET": "your-vault-jwt-secret"
      }
    }
  }
}
```

Restart Claude Desktop. The vault tools appear in the tool selector.

---

## Claude Code

Add `.mcp.json` to the project root (already present in NeNe Vault):

```json
{
  "mcpServers": {
    "nene-vault": {
      "command": "php",
      "args": ["tools/local-mcp-server.php"],
      "env": {
        "NENE2_LOCAL_API_BASE_URL": "http://localhost:8600",
        "NENE2_LOCAL_JWT_SECRET": "your-vault-jwt-secret"
      }
    }
  }
}
```

Claude Code picks up `.mcp.json` automatically when you open the project.

---

## Smoke test

```sh
# List available tools
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  | NENE2_LOCAL_API_BASE_URL=http://localhost:8600 \
    NENE2_LOCAL_JWT_SECRET=your-secret \
    php tools/local-mcp-server.php

# Search documents (read-only, no JWT secret required for read tools)
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"searchVaultDocuments","arguments":{"counterparty_name":"ACME","limit":5}}}' \
  | NENE2_LOCAL_API_BASE_URL=http://localhost:8600 \
    NENE2_LOCAL_JWT_SECRET=your-secret \
    php tools/local-mcp-server.php
```

---

## Example prompts

Once connected, you can ask Claude:

- 「2026年5月の請求書をすべて検索して、合計金額を教えて」
- 「書類ID `01JXXX...` の変更履歴を見せて」
- "Search for documents from ACME Corp in Q1 2026 and export as CSV"
- "The document `01JXXX...` was registered by mistake — void it with reason 'Duplicate entry'"
- "Run OCR on document `01JXXX...` and suggest the correct transaction date and amount"

---

## Security notes

- The MCP server issues a JWT derived from `NENE2_LOCAL_JWT_SECRET`. Keep this
  value confidential and do not commit it to the repository.
- Read tools work without authentication (but the server still needs the secret
  to proxy requests to the authenticated API). Write tools explicitly require it.
- The server runs locally; no external network access is made beyond the
  configured `NENE2_LOCAL_API_BASE_URL`.
- Storage paths are never exposed through MCP tool responses (enforced at the
  API layer — `file_path` is excluded from all API responses).
