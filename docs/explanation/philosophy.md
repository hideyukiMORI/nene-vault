# Philosophy — NeNe Vault

**NeNe Vault** — *Store received documents. Find them when it matters.*

## 1. What we believe

### 1.1 Storage-only SaaS is a ripoff for SMBs

Bundled cloud platforms charge monthly for **document storage + search** operators would
happily self-host if compliance structure existed. Vault targets that slice —
honestly, without pretending to replace accounting.

### 1.2 Received ≠ issued

**Issued** billing documents belong in **NeNe Invoice**. **Received** vendor
evidence belongs in **NeNe Vault**. Mixing SSOT creates audit confusion.

### 1.3 Compliance is structure

電子帳簿保存法 rules live in [`received-document-compliance.md`](./received-document-compliance.md)
and [`scope-contract.md`](./scope-contract.md) — not in operator memory.

### 1.4 Human confirms, AI proposes

OCR may suggest metadata; operator confirms before authoritative fields are set.

### 1.5 Narrow scope is a feature

Vault refuses reconciliation, CSV engines, and expense workflows. One noun:
**受取書類の保存・検索**.

---

## 2. Portfolio position

```
Back office
  ├── NeNe Invoice  — issued billing documents
  ├── NeNe Clear    — reconciliation · dunning
  ├── NeNe Profile  — bank CSV normalization
  └── NeNe Vault    — received-document archive  ← this product
```

---

## Related

- ADR 0007: Product identity
- [`product-vision.md`](./product-vision.md)

Last updated: 2026-05-29
