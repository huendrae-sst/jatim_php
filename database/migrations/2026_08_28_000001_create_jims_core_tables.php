<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Organizations (Kantor Pusat, Cabang Utama, Capem, Gudang)
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['HEAD_OFFICE', 'MAIN_BRANCH', 'SUB_BRANCH', 'WAREHOUSE'])->default('SUB_BRANCH');
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('city')->default('Surabaya');
            $table->string('phone')->nullable();
            $table->string('cost_center_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Warehouses (Gudang Logistik Pusat, Tempat Penyimpanan Cabang)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['CENTRAL_LOGISTICS', 'BRANCH_STORAGE'])->default('BRANCH_STORAGE');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Update Users Table with organization, warehouse, role, approval limit
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('organization_id')->constrained('warehouses')->nullOnDelete();
            $table->string('nip')->nullable()->unique()->after('email');
            $table->string('role')->default('REQUESTER_CABANG')->after('nip');
            $table->decimal('approval_limit', 15, 2)->default(0)->after('role');
            $table->string('phone')->nullable()->after('approval_limit');
            $table->boolean('is_active')->default(true)->after('phone');
        });

        // 3. Categories (ATK, Cetakan/Formulir, Perlengkapan IT, Promosi & Souvenir, Khazanah & Keamanan)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 4. Master Items
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('uom')->default('PCS'); // RIM, BOX, UNIT, PCS, PACK, SET
            $table->text('specification')->nullable();
            $table->integer('min_stock')->default(10);
            $table->integer('max_stock')->default(500);
            $table->integer('safety_stock')->default(20);
            $table->integer('reorder_point')->default(30);
            $table->integer('lead_time_days')->default(5);
            $table->decimal('estimated_unit_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Vendors & Couriers
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->integer('sla_days')->default(7);
            $table->string('payment_terms')->default('TOP 30 Hari');
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('service_types')->nullable(); // ["REGULER", "EXPRESS", "CARGO", "INTERNAL"]
            $table->integer('sla_days')->default(2);
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Budgets (Pagu Anggaran per Unit & Cost Center)
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('cost_center_code');
            $table->year('year');
            $table->decimal('allocated_amount', 15, 2)->default(0); // Pagu
            $table->decimal('committed_amount', 15, 2)->default(0); // Komitmen
            $table->decimal('realized_amount', 15, 2)->default(0);  // Realisasi
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Stock Balances (Single source of truth per warehouse & item)
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('allocated')->default(0);
            $table->integer('in_transit')->default(0);
            $table->integer('hold')->default(0);
            $table->integer('damaged')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'item_id']);
        });

        // 8. Stock Ledgers (Immutable Ledger History)
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('transaction_type'); // PROCUREMENT_RECEIPT, GOODS_ISSUE, TRANSFER_OUT, TRANSFER_IN, SWITCHING_STOCK, GOODS_RECEIPT_UNIT, STOCK_ADJUSTMENT, STOCK_OPNAME, DAMAGED_HOLD, RETURN, REVERSAL
            $table->string('reference_number')->nullable();
            $table->integer('qty_in')->default(0);
            $table->integer('qty_out')->default(0);
            $table->integer('balance_after')->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 9. Purchase Requests (PR) & Items
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('procurement_method')->default('DIRECT'); // DIRECT, TENDER, E_CATALOG
            $table->text('purpose');
            $table->decimal('estimated_total_cost', 15, 2)->default(0);
            $table->string('budget_status')->default('VALIDATED'); // VALIDATED, INSUFFICIENT
            $table->string('status')->default('DRAFT'); // DRAFT, SUBMITTED, BUDGET_VALIDATED, WAITING_APPROVAL, APPROVED, PARTIALLY_ORDERED, FULLY_ORDERED, REJECTED, CLOSED
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_requested')->default(1);
            $table->integer('qty_approved')->default(0);
            $table->integer('qty_ordered')->default(0); // Count ordered into POs
            $table->decimal('estimated_unit_price', 15, 2)->default(0);
            $table->decimal('estimated_subtotal', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 10. Purchase Orders (PO) & Items (Multi-PR Consolidation)
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete(); // Destination central logistics
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0); // PPN 11%
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('DRAFT'); // DRAFT, GENERATED_FROM_PR, ISSUED, VENDOR_PROCESS, IN_DELIVERY, PARTIAL_RECEIVED, RECEIVED, COMPLETED, CANCELLED
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->constrained('purchase_request_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_ordered')->default(1);
            $table->integer('qty_received')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });

        // 11. Goods Receipts (GRN) & Items from Vendor
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('vendor_delivery_note_number')->nullable();
            $table->date('receipt_date');
            $table->string('status')->default('RECEIVED'); // RECEIVED, PARTIAL, DISCREPANCY
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_received')->default(0);
            $table->integer('qty_accepted')->default(0);
            $table->integer('qty_rejected')->default(0);
            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });

        // 12. Branch Orders & Items (Workflow 2)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('requesting_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requesting_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('NORMAL'); // NORMAL, HIGH, URGENT
            $table->date('required_date')->nullable();
            $table->integer('total_items')->default(0);
            $table->decimal('total_estimated_value', 15, 2)->default(0);
            $table->string('status')->default('DRAFT'); // DRAFT, SUBMITTED, WAITING_APPROVAL, APPROVED, ALLOCATED, PICKING, PACKING, READY_TO_SHIP, IN_TRANSIT, RECEIVED, COMPLETED, REJECTED, CANCELLED
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty_requested')->default(1);
            $table->integer('qty_approved')->default(0);
            $table->integer('qty_allocated')->default(0);
            $table->integer('qty_picked')->default(0);
            $table->integer('qty_packed')->default(0);
            $table->integer('qty_shipped')->default(0);
            $table->integer('qty_received')->default(0);
            $table->decimal('unit_price_ref', 15, 2)->default(0);
            $table->decimal('subtotal_ref', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 13. Switching Stocks (Inter-branch fulfillment when main warehouse stock is low)
        Schema::create('switching_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('source_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->integer('qty_requested')->default(1);
            $table->foreignId('proposed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('PROPOSED'); // PROPOSED, WAITING_APPROVAL, APPROVED, RESERVED, TRANSFERRED, RECEIVED, COMPLETED, REJECTED, CANCELLED
            $table->text('recommendation_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('order_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->integer('qty_allocated')->default(0);
            $table->string('allocation_type')->default('DIRECT_WAREHOUSE'); // DIRECT_WAREHOUSE, SWITCHING_STOCK
            $table->foreignId('switching_stock_id')->nullable()->constrained('switching_stocks')->nullOnDelete();
            $table->string('status')->default('RESERVED'); // RESERVED, PICKED, PACKED, SHIPPED, RECEIVED, CANCELLED
            $table->timestamps();
        });

        // 14. Warehouse Picking & Packing
        Schema::create('warehouse_pickings', function (Blueprint $table) {
            $table->id();
            $table->string('picking_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('picked_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('ASSIGNED'); // ASSIGNED, IN_PROGRESS, COMPLETED
            $table->timestamp('picked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_packings', function (Blueprint $table) {
            $table->id();
            $table->string('packing_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('packed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('koli_count')->default(1);
            $table->decimal('total_weight_kg', 8, 2)->default(1.0);
            $table->string('dimensions_cm')->nullable(); // e.g. "30x20x15"
            $table->string('status')->default('PACKED'); // PACKED, VERIFIED
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();
        });

        // 15. Shipments / Manifests & Tracking
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('origin_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->string('service_type')->default('REGULER');
            $table->string('tracking_number')->nullable(); // No Resi/AWB
            $table->foreignId('dispatched_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('koli_count')->default(1);
            $table->decimal('total_weight_kg', 8, 2)->default(1.0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->date('eta_date')->nullable();
            $table->string('status')->default('CREATED'); // CREATED, READY_TO_SHIP, DISPATCHED, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, CLOSED, DELIVERY_FAILED, RETURNED, PARTIAL_RECEIVED
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // 16. Receiving & Discrepancies
        Schema::create('receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_number')->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('receipt_date');
            $table->string('status')->default('RECEIVED_FULL'); // RECEIVED_FULL, RECEIVED_PARTIAL, DISCREPANCY
            $table->string('pod_signature')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_id')->constrained('receivings')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('discrepancy_type')->default('DAMAGED'); // MISSING, DAMAGED, WRONG_ITEM, EXCESS
            $table->integer('qty_expected')->default(0);
            $table->integer('qty_actual')->default(0);
            $table->integer('qty_damaged')->default(0);
            $table->string('resolution_status')->default('REPORTED'); // REPORTED, UNDER_REVIEW, RESOLVED, CLAIMED
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        // 17. Inter-unit Financial Settlements
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('debit_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('credit_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('debit_cost_center');
            $table->string('credit_cost_center');
            $table->decimal('item_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('DRAFT'); // DRAFT, VALIDATED, WAITING_APPROVAL, APPROVED, POSTED, COMPLETED, REJECTED, REVERSED
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // 18. Notifications (ACTION_REQUIRED, ALERT, INFORMATION)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('target_role')->nullable();
            $table->foreignId('target_organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('type')->default('INFORMATION'); // ACTION_REQUIRED, ALERT, INFORMATION
            $table->string('priority')->default('INFO'); // INFO, WARNING, HIGH, CRITICAL
            $table->string('title');
            $table->text('message');
            $table->string('reference_transaction_type')->nullable(); // PR, PO, ORDER, SWITCHING_STOCK, SHIPMENT, RECEIVING, SETTLEMENT
            $table->unsignedBigInteger('reference_transaction_id')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 19. Audit Logs (Maker-checker and transaction trail)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // CREATE, UPDATE, DELETE, SUBMIT, APPROVE, REJECT, CONSOLIDATE, RECEIVE, POST, SWITCH
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('discrepancies');
        Schema::dropIfExists('receivings');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('warehouse_packings');
        Schema::dropIfExists('warehouse_pickings');
        Schema::dropIfExists('order_allocations');
        Schema::dropIfExists('switching_stocks');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('stock_ledgers');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('couriers');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['organization_id', 'warehouse_id', 'nip', 'role', 'approval_limit', 'phone', 'is_active']);
        });
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('organizations');
    }
};
