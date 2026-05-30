# Shopify Custom Product Options App

> A Shopify embedded app that lets merchants add unlimited custom input fields — text, dropdowns, checkboxes, colour pickers, date pickers — to any product, with optional price modifiers and storefront rendering via a Theme App Extension.

Built with **Remix + Shopify CLI 3 + Polaris v12 + Prisma (SQLite)**.

---

## Features

- **6 option types:** Text, Textarea, Dropdown, Checkbox, Colour picker, Date picker
- **Option sets:** Group multiple options and assign them to any product
- **Price modifiers:** Add a price delta per option (e.g. +$5 for engraving)
- **Required fields:** Mark any option as required before add-to-cart
- **Theme App Extension:** Renders options on the product page without theme code edits
- **Line item properties:** Customer inputs flow through to order details automatically
- **GDPR compliant:** Full webhook handling for customer/shop redact + data request
- **Polaris v12 UI:** Fully embedded, App Bridge v4, no legacy SDK

---

## File structure

```
shopify-custom-product-options/
├── app/
│   ├── routes/
│   │   ├── app.jsx                        ← Auth shell + nav
│   │   ├── app._index.jsx                 ← Dashboard
│   │   ├── app.option-sets.jsx            ← List option sets
│   │   ├── app.option-sets.$id.jsx        ← Create / edit option set
│   │   ├── app.products.jsx               ← Assign sets to products
│   │   ├── api.options.$productId.jsx     ← Public API for storefront widget
│   │   └── webhooks.jsx                   ← app/uninstalled + GDPR
│   ├── root.jsx
│   └── shopify.server.js                  ← Auth + session + Prisma export
├── extensions/
│   └── custom-options-widget/
│       ├── blocks/
│       │   └── options.liquid             ← Theme app extension block
│       └── shopify.extension.toml
├── prisma/
│   └── schema.prisma                      ← Session + OptionSet + Option + ProductOptionSet
├── shopify.app.toml                       ← App config + scopes + webhooks
├── vite.config.js
├── package.json
├── .env.example
└── .gitignore
```

---

## Local development

### Prerequisites

- Node.js ≥ 18.20.0
- A [Shopify Partner account](https://partners.shopify.com)
- A development store

### 1. Clone & install

```bash
git clone https://github.com/rakib78/Shopify-Custom-Product-Options-App.git
cd Shopify-Custom-Product-Options-App
npm install
```

### 2. Configure

```bash
cp .env.example .env
```

Fill in `.env` with your app credentials from the Partner Dashboard.

### 3. Set up the database

```bash
npx prisma migrate dev --name init
```

### 4. Start dev server

```bash
npm run dev
# or: shopify app dev
```

Shopify CLI opens a tunnel and installs the app on your dev store automatically.

### 5. Add the theme block

In your dev store:
1. **Online Store → Themes → Customize**
2. Navigate to a **Product** template
3. Click **Add block** → **Apps** → **Custom Product Options**
4. Save

---

## Deployment (production)

### Option A — Fly.io (recommended)

```bash
fly launch
fly secrets set SHOPIFY_API_KEY=xxx SHOPIFY_API_SECRET=xxx SHOPIFY_APP_URL=https://your-app.fly.dev DATABASE_URL=file:/data/prod.db SCOPES=read_products,write_products,...
fly deploy
```

### Option B — Railway / Render

Set the same env vars via the platform dashboard, set build command to:
```bash
npm run build && npx prisma migrate deploy
```
and start command to:
```bash
npm run start
```

### Option C — Vercel (limited — SQLite not supported)

Use `DATABASE_URL=postgresql://...` with a Neon or Supabase Postgres instance and update `prisma/schema.prisma` provider to `postgresql`.

---

## App Store submission checklist

- [ ] All 4 mandatory webhooks registered and handled (`app/uninstalled`, `shop/redact`, `customers/redact`, `customers/data_request`)
- [ ] Privacy policy URL set in Partner Dashboard
- [ ] App URL is public HTTPS (not localhost)
- [ ] Tested on a non-development store
- [ ] Polaris components used throughout (no custom CSS overriding Shopify UI)
- [ ] App Bridge embedded correctly (`embedded = true` in `shopify.app.toml`)
- [ ] GDPR webhooks return 200 within 5 seconds
- [ ] No `console.log` in production build
- [ ] Billing API implemented if charging merchants (see roadmap)

---

## Roadmap

- [ ] Shopify Billing API — subscription plans
- [ ] Conditional logic — show/hide fields based on other field values
- [ ] Bulk product assignment — apply one set to many products at once
- [ ] Order view metafield display — show customer inputs in admin order detail
- [ ] Export option responses to CSV
- [ ] File upload option type

---

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Remix v2 |
| UI | Shopify Polaris v12 + App Bridge v4 |
| Auth | `@shopify/shopify-app-remix` v3 |
| Database | Prisma + SQLite (dev) / PostgreSQL (prod) |
| Session storage | `@shopify/shopify-app-session-storage-prisma` |
| Storefront | Theme App Extension (Liquid + vanilla JS) |
| Build | Vite + Shopify CLI 3 |

---

## License

GPL-3.0 — see [LICENSE](LICENSE)
