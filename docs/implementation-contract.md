# MyKasir implementation contract

Local Laravel 13 + AdminLTE 4. Indonesian UI. Custom JS under public/scripts grouped by view. All mutations AJAX JSON, DataTables server-side for lists, SweetAlert2 confirmations/feedback. No automated production demo data. Business services are reusable by a future mobile API; token authentication and mobile API routes are not part of this implementation.

## Domain schema and service interface

Money input values are rupiah numbers or decimal strings (max 2 decimal places); persisted money is returned as decimal strings. Quantities are integers; trade quantities refer to the selected unit and stock uses the product's base unit. Database monetary totals use decimal(18,2), average cost decimal(18,6). Services use BCMath/integer arithmetic to avoid float drift and snapshot sold cost and price. Dates ISO YYYY-MM-DD; timestamps use the configured app timezone, Asia/Jakarta by default.

- Product: id, sku unique, name, category nullable, base_unit, stock integer default 0, minimum_stock integer, average_cost, active boolean. units relation: ProductUnit(id,product_id,name,multiplier positive integer,selling_price); always base unit multiplier 1. Changing conversion with stock or historical transactions forbidden; add units allowed.
- Supplier: id,name,phone nullable,address nullable.
- Customer: id,name,phone nullable,address nullable. Computed outstanding via sales balance.
- Purchase: id,number,supplier_id,date,total,notes nullable,request_id unique UUID. items: product_id,unit_id,product_name,unit_name,multiplier,quantity,unit_price,subtotal. All received and paid; no draft feature this iteration.
- Sale: id,number,customer_id nullable,date,total,cost_total,paid_amount,balance,payment_method (cash/transfer/qris),notes nullable,request_id unique UUID. items same snapshots as purchase plus cost_total. No discounts in this iteration. Paid_amount capped at total; received_amount from request can exceed total only cash; expose change_amount. Sale relation payments for initial payment and subsequent repayment.
- Payment: id,sale_id,amount,date,method,notes nullable,request_id unique UUID. initial sale payment tracked to compute cash received by payment date.
- StockMovement: id,product_id,type (purchase/sale/adjustment),quantity signed,balance,unit_cost,cost_total,reference,notes,date,request_id nullable,request_hash nullable.
- Expense: id,date,category,description,amount,payment_method.

Services: ProductService::save(array $data, ?Product $product=null): Product; PurchaseService::create(array $data): Purchase; SaleService::create(array $data): Sale; ReceivableService::pay(Sale $sale,array $data): Payment; InventoryService::adjust(array $data): StockMovement; ReportService::summary(string $from,string $to): array. Services business validations throw ValidationException with Indonesian messages. Services own transactions + lock products in sorted order and customer for debt check. Reject new sale balance >0 when customer has old balance>0; fully paid sales allowed. Require customer for partial/unpaid sale. Prevent negative stock and overpayment of debt. Replays same request_id do not double-post; different payload same key must reject.

Purchase, sale, stock adjustment and payment dates cannot be in the future. Each stock movement must be on or after that product's latest movement date; same-day entries follow posting order. Earlier stock postings are rejected atomically because weighted-average costs are captured when posted, without recalculating later documents. Payment dates must also be on or after the associated sale date.

Idempotency is scoped by operation model and backed by the operation_requests unique reservation inside the same transaction as the document and stock changes. Purchase, sale and payment documents store request_hash as well as request_id. A successful replay returns the original document before checking current price, active status or stock availability.

Report summary: sales_total,cost_total,gross_profit,expenses_total,net_profit,cash_received,purchases_total,outstanding_total,stock_value,low_stock_count,product_count,sale_count; daily array {date,total}; recent_sales array, low_stock array. Period sales by sale date; receipts by payment date; outstanding and stock value are current overall values. Stock adjustment losses separately reduce net profit (stock_loss_total). Gross profit = sales_total - cost_total; net profit = gross_profit - expenses_total - stock_loss_total. Stock purchases do not count again as operating expenses.

## HTTP endpoints (all /ajax, session auth + CSRF)

routes/web.php requires routes/pos.php inside the auth group. AJAX endpoints use PosDataController, CatalogController and TransactionController. Return mutations {message,data}; validation standard {message,errors}. DataTable envelope {draw,recordsTotal,recordsFiltered,data}. All entity values user controlled must be escaped by JS rendering.

- GET /ajax/summary?from=&to= -> {data: report summary}
- GET /ajax/lookups -> {data:{products:[products with units],customers:[customers with outstanding],suppliers:[]}}
- GET /ajax/{products,suppliers,customers,purchases,sales,receivables,movements,expenses} -> server-side DataTables, search[value],start,length,order + whitelist columns; from/to filters for dated resources. products include units; customers include outstanding; purchases supplier_name; sales customer_name and status paid/partial/unpaid; receivables is sales with positive balance plus customer_name; movements product_name; no pagination for lookups only.
- GET /ajax/{purchases,sales}/{id} -> {data: object with items, customer/supplier, payments where applicable}
- POST /ajax/{products,suppliers,customers,expenses} -> create
- PUT /ajax/{products,suppliers,customers}/{id} -> update
- POST /ajax/purchases -> {supplier_id,date,items:[{product_id,unit_id,quantity,unit_price}],notes,request_id}
- POST /ajax/sales -> {customer_id nullable,date,items:[{product_id,unit_id,quantity,expected_unit_price optional}],received_amount,payment_method,notes,request_id}; server uses current configured selling price. The web cashier sends expected_unit_price for every line. If it differs from the current price, reject the entire transaction with an item validation error so an outdated cart cannot silently create debt or change the amount due.
- POST /ajax/receivables/{sale}/payments -> {amount,date,method,notes,request_id}
- POST /ajax/stock-adjustments -> {product_id,quantity signed,unit_cost required for additions,notes,date,request_id}; request_id is required by the HTTP endpoint and protects against duplicate posting.

Product input: sku,name,category,base_unit,minimum_stock,active,units:[{id optional,name,multiplier,selling_price}]. No direct stock editing. Product creation starts zero stock; record purchase or adjustment.

## Page URLs and shared frontend API

/dashboard -> dashboard.index; /barang -> barang.index; /supplier -> supplier.index; /pelanggan -> pelanggan.index; /pembelian -> pembelian.index; /kasir -> kasir.index; /penjualan -> penjualan.index; /piutang -> piutang.index; /stok -> stok.index; /pengeluaran -> pengeluaran.index; /laporan -> laporan.index. Root page controller can simply return views. All pages @extends('layouts.app'), @section('title'), @section('page_title'), @section('content'), @push('js') external script tags only. @section('page_description') optional. Blade script src uses asset(). No inline JS or inline event handlers.

Root provides window.App:
- App.http(method,url,data) returns native Promise resolving JSON, rejects xhr-style {status,responseJSON}; shows SweetAlert on failure and renders field errors if caller passed form via fourth argument. No automatic success popup.
- App.money(value), App.number(value), App.escape(value), App.uuid(), App.today(), App.toast(message), App.confirm(title,text) returns Promise<boolean>, App.busy(button,bool), App.table(selector,options) constructs DataTable with defaults (serverSide true, responsive horizontal scrolling, Indonesian text); options ajax/columns/order etc override. App.http accepts relative /ajax paths; App.url('/ajax/...') resolves base path. App.formErrors(form,errors).
- App.date(ISO) localized date. App.showDetails(title,html) SweetAlert large modal for history detail.
- App.transactionRequest(scope,container) is provided by shared/transaction-request.js on purchase, cashier, receivable and stock pages. send(url,payload,form) generates a request UUID and preserves the exact pending payload in sessionStorage scoped to the signed-in user and operation. It prevents a new, different submission while a previous result is uncertain. The visible "Periksa & lanjutkan" button explicitly retries the same request. An initial definitive rejection clears the pending payload; an uncertain response followed by a rejection preserves it. This is response recovery, not an offline transaction queue.

Common CSS: page-intro, page-eyebrow, surface-card, section-heading, text-muted, stat-card, stat-label, stat-value, stat-note, icon-tile, btn-brand (green), btn-soft, badge-soft-success/warning/danger/neutral, empty-state, table-toolbar, form-label, form-hint, page-grid, receipt. Bootstrap 5 utility classes available. Responsive polished typography, light mint sidebar #DCFCE7, forest text #14532D, active #166534 white. No generic demo placeholder dashboard, no fake business data unless explicit DemoSeeder.

Root loads jQuery, Bootstrap bundle, OverlayScrollbars, AdminLTE, Select2, SweetAlert2, DataTables + Bootstrap adapter local assets before shared scripts and page stack.
