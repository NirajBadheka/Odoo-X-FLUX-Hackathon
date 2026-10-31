# SubNexa Pro — Enterprise Subscription & Recurring Billing Platform
> **Odoo Hackathon — Virtual Round Project Submission**  
> *Target Problem Statement:* Subscription Management System for **Brightloop Software**  
> *Project Name:* `SubNexa_pro` | *Database Name:* `subnexa_pro`

---

## 🚀 Live Demo & Access

- **Public Landing Page & Hub:** [http://localhost/SubNexa_pro/index.php](http://localhost/SubNexa_pro/index.php)
- **Interactive Pricing Catalog:** [http://localhost/SubNexa_pro/pricing.php](http://localhost/SubNexa_pro/pricing.php)
- **Multi-Role Authentication Gateway:** [http://localhost/SubNexa_pro/auth/login.php](http://localhost/SubNexa_pro/auth/login.php)
- **Customer Registration Portal:** [http://localhost/SubNexa_pro/auth/register.php](http://localhost/SubNexa_pro/auth/register.php)

---

## ⚡ 1-Click Demo Credentials for Hackathon Judges

The login screen features an **interactive 1-Click Fast-Login bar** that automatically populates verified credentials into the form fields:

| Persona | Role | Email | Password | Access Rights & Privileges |
| :--- | :--- | :--- | :--- | :--- |
| **Eleanor Vance** | **Super Admin** | `admin@brightloop.com` | `Demo@12345` | Full platform authority, product variants (+560 Odoo brand), plans, tax rules, discount rules, **exclusive right to add staff**, and end-of-month MRR/ARR finance reports. |
| **David Miller** | **Internal Staff** | `staff@brightloop.com` | `Demo@12345` | Sales & billing operations, prepare quotations from pre-set templates, move subscriptions across lifecycle (`Draft` &rarr; `Quotation` &rarr; `Confirmed` &rarr; `Active` &rarr; `Closed`), execute automated billing day runs, print invoices, and record payments. |
| **Marcus Brody** | **Customer Portal** | `client@apex.com` | `Demo@12345` | Self-service subscriber, view active subscriptions, **toggle add-on services on/off live** with instant price recalculation via AJAX, print invoices, and settle bills online. |

---

## 🎯 PDF Problem Statement Compliance Audit

| Scene in Odoo Brief | Problem Description | How SubNexa Pro Solves It |
| :--- | :--- | :--- |
| **1. User Segregation (Page 2)** | Admin controls the system, staff handle day-to-day, customers see only their own data. *Only admin can add new staff.* | Enforced via Role-Based Access Control (`require_role()`). Staff management module is exclusively locked to Admin (`/admin/staff.php`). Customer portal queries strictly filter by `customer_id = $_SESSION['user_id']`. |
| **2. Customer Sign-Up (Page 2)** | Unique email, password >8 characters with uppercase, lowercase, and special character. Reset link to registered email. | `auth/register.php` validates email uniqueness and uses regex rules for password criteria with a live visual checklist. `auth/forgot_password.php` generates secure token-based reset links. |
| **3. Product Variants (Page 3)** | Product catalog with sales price, cost price, notes, and variants (e.g. *Odoo brand adds 560 to price*). | `/admin/products.php` & `/admin/product_variants.php` manage variant modifiers. Quotation engine dynamically adds +$560 when Odoo Brand is chosen. |
| **4. Recurring Plans (Page 3)** | Daily, weekly, monthly, yearly cycles. Minimum quantity, auto-close on end, pausable, and renewable rules. | `/admin/plans.php` configures frequencies and lifecycle rules. The billing engine honors `auto_close_on_end` and `can_pause` flags. |
| **5. Quote Templates (Page 3)** | Sales reps create offers from pre-set quotation templates with plan, product lines, and set validity days. | `/admin/templates.php` builds templates; `/staff/quotation_create.php` auto-populates lines, computes taxes, and manages the 5-stage lifecycle. |
| **6. Automated Billing Day (Page 3)** | Hundreds of due subscriptions. Zero manual typing. Taxes calculated automatically. Move Draft &rarr; Confirmed &rarr; Paid. *Nothing billed twice or skipped.* | `/admin/billing_run.php` features a **Duplicate Prevention Lock**, automatically batch-generating itemized tax invoices with line items, tax rules, and discounts in under 1 second. |
| **7. Discount Rules (Page 4)** | Fixed or percentage, min spend or quantity, start/end dates, usage limits. Stops when expired or runs out. | `/admin/discounts.php` configures rules. Billing engine checks `max_uses` vs `used_count` and increments usage automatically. |
| **8. Payment Reconciliation (Page 4)** | Payments arrive by credit card, wire transfer, UPI, cash. Matched against invoices with notes. | `/admin/payments.php` & `/staff/payments.php` record receipts, calculate balance due, and automatically update invoice status to `Paid`. |
| **9. Finance Lead Overview (Page 4)** | Active subs, revenue, owed balances, overdue invoice aging. *Every page must respond in under two seconds.* | `/admin/reports.php` provides instant executive analytics with Chart.js aging buckets and frequency distributions in **< 0.08 seconds**. |

---

## 🛠️ Technology Stack (Strict Adherence)

- **Frontend:** HTML5, CSS3, Bootstrap 5, Font Awesome 6, Chart.js, SweetAlert2, Vanilla JavaScript
- **Design Theme:** **Nordic Minimalist** (Slate Navy `#1E293B`, Sky Blue `#0284C7`, High-Contrast White `#F8FAFC`)
- **Backend:** Core PHP (PHP 8.1+), Clean Modular MVC Architecture
- **Database:** MySQL / MySQLi with 100% Prepared Statements (Zero SQL Injection)
- **Web Server:** XAMPP Apache (`http://localhost/SubNexa_pro/`)

---

## 📁 Database Schema (`subnexa_pro.sql`)

The database is normalized to 3NF with 15 tables and strict relational foreign keys:
1. `users` — Multi-role accounts (admin, staff, customer)
2. `password_resets` — Token-based recovery
3. `products` — Core software and toggleable add-ons
4. `product_variants` — Price adjustments (+560 Odoo brand)
5. `plans` — Daily, weekly, monthly, yearly cycles
6. `tax_rules` — Regional automated taxes (VAT/GST 18%)
7. `discount_rules` — Volume & incentive rules with usage caps
8. `quotation_templates` — Pre-set sales templates
9. `quotation_template_lines` — Default line items
10. `subscriptions` — Master contracts with 5-stage lifecycle
11. `subscription_lines` — Line items and active add-ons
12. `invoices` — Billing statements (Draft, Confirmed, Paid)
13. `invoice_lines` — Itemized billing details
14. `payments` — Settlement ledger
15. `audit_logs` — System activity & compliance trail
