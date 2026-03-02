# MOHI LD HUB v2.0
## Learning & Development — Tech & Data Platform

---

## Quick Start (XAMPP)
1. Copy the `mohi_ld_hub/` folder into `C:/xampp/htdocs/`
2. Start Apache in XAMPP Control Panel
3. Visit: `http://localhost/mohi_ld_hub/`

Your existing database (`data/ld_hub.db`) is included with all 1,897 staff records intact.

---

## Hub Structure

```
MOHI LD HUB
├── 🏠  Hub Home          index.php
│
├── 1. Objectives         obj_index.php
│   └── 1.1 KPIs         obj_kpi.php
│       └── 1.1.1 RACI   obj_raci.php
│
├── 2. Inventory          inv_assets.php
│   └── 2.1 Assets       (included in above)
│
├── 3. Staff Training Data
│   ├── Dashboard         train_dashboard.php
│   ├── Staff             train_staff.php
│   ├── Trainings         train_trainings.php
│   ├── Results           train_results.php
│   └── Reports           train_reports.php
│
└── ⚙  Manage Modules     modules.php  (add custom tiles to hub)
```

---

## Key Facts

- **Email** is the single unique identifier for all staff
- Existing staff data (1,897 records) and training data fully preserved
- MOHI brand: Navy #002F66 | Blue #26A9E0 | Green #8BC53F

---

## Adding Custom Modules (Extensibility)

Go to **Manage Modules** → **New Module** and fill in:
- Title & Description
- Icon (Bootstrap icon name, e.g. `bi-calendar3`)
- Color (blue / green / gold / orange / navy / teal)
- Link URL (internal page or external https:// link)

The module immediately appears as a card on Hub Home and in the sidebar.

---

## File Structure

```
mohi_ld_hub/
├── config.php            — DB + all schemas (SQLite)
├── header.php            — Shared sidebar/topbar
├── footer.php            — Shared scripts
├── assets/style.css      — MOHI brand CSS
├── data/ld_hub.db        — Your SQLite database
│
├── index.php             — Hub portal home
├── obj_index.php         — Objectives (Module 1)
├── obj_kpi.php           — KPIs (1.1)
├── obj_raci.php          — RACI (1.1.1)
├── inv_assets.php        — Assets/Inventory (Module 2)
├── train_dashboard.php   — Training dashboard (Module 3)
├── train_staff.php       — Staff management
├── train_trainings.php   — Training programmes
├── train_results.php     — Results entry/batch upload
├── train_reports.php     — 5 report types
├── train_export.php      — CSV export handler
└── modules.php           — Custom module manager
```

---

## Backup
Your data lives in `data/ld_hub.db` — copy this file to back up everything.
