<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EmbossFile;
use App\Models\EmbossRecord;
use App\Models\Item;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmbossImportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $orgSby;

    protected Organization $orgMlg;

    protected Item $itemAtmInstant;

    protected Item $itemAtmNamed;

    protected Item $itemNoPrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
        ]);

        $this->orgSby = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Utama Surabaya',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-KC-SBY',
            'is_active' => true,
        ]);

        $this->orgMlg = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-KC-MLG',
            'is_active' => true,
        ]);

        Warehouse::create([
            'code' => 'GD-RKT',
            'name' => 'Gudang Logistik Pusat SIER Rungkut',
            'organization_id' => $this->orgSby->id,
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $catAtm = Category::create([
            'code' => 'CAT-ATM',
            'name' => 'Kartu ATM & Debet',
            'description' => 'Persediaan kartu ATM',
        ]);

        $this->itemAtmInstant = Item::create([
            'category_id' => $catAtm->id,
            'sku' => 'ATM-INST-001',
            'barcode' => '8991001021',
            'name' => 'Kartu ATM Instan Chip GPN Bank Jatim',
            'uom' => 'PCS',
            'estimated_unit_price' => 15000,
            'is_active' => true,
        ]);

        $this->itemAtmNamed = Item::create([
            'category_id' => $catAtm->id,
            'sku' => 'ATM-NAME-001',
            'barcode' => '8991001022',
            'name' => 'Kartu ATM Bernama Mastercard Platinum',
            'uom' => 'PCS',
            'estimated_unit_price' => 18500,
            'is_active' => true,
        ]);

        $this->itemNoPrice = Item::create([
            'category_id' => $catAtm->id,
            'sku' => 'ATM-NOPRICE-001',
            'barcode' => '8991001099',
            'name' => 'Kartu ATM Tanpa Harga Pengadaan',
            'uom' => 'PCS',
            'estimated_unit_price' => 0, // Missing procurement price (POC-27)
            'is_active' => true,
        ]);
    }

    public function test_valid_emboss_file_import_creates_valid_records(): void
    {
        // CSV with 2 valid records (POC-20)
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,cif_number,account_number,qty\n".
            "REF-001,KC-SBY,ATM-INST-001,INSTANT,NASABAH INSTAN,6013021122334401,CIF001,0011223301,1\n".
            "REF-002,KC-MLG,ATM-NAME-001,NAMED,BUDI SANTOSO,6013021122334402,CIF002,0011223302,2\n";

        $file = UploadedFile::fake()->createWithContent('valid_emboss_batch.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file,
            'source' => 'CARD_CORE_SYSTEM',
            'notes' => 'Batch uji intake valid',
        ]);

        $embossFile = EmbossFile::latest()->first();
        $this->assertNotNull($embossFile);
        $response->assertRedirect(route('emboss.show', $embossFile->id));

        $this->assertEquals(2, $embossFile->total_records);
        $this->assertEquals(2, $embossFile->success_records);
        $this->assertEquals(0, $embossFile->reject_records);
        $this->assertEquals(0, $embossFile->duplicate_records);
        $this->assertEquals('COMPLETED', $embossFile->status);
        $this->assertNotNull($embossFile->duration_seconds);
    }

    public function test_emboss_data_mapping_and_price_enrichment(): void
    {
        // POC-21 & POC-27
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,cif_number,account_number,qty\n".
            "REF-PRICE-01,KC-SBY,ATM-NAME-001,NAMED,AHMAD SYAFII,6013025566778899,CIF999,099887766,3\n";

        $file = UploadedFile::fake()->createWithContent('mapping_test.csv', $csvContent);

        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file,
            'source' => 'BRANCH_PORTAL',
        ]);

        $record = EmbossRecord::where('external_reference_id', 'REF-PRICE-01')->first();
        $this->assertNotNull($record);
        $this->assertEquals('KC-SBY', $record->branch_code);
        $this->assertEquals($this->orgSby->id, $record->organization_id);
        $this->assertEquals('ATM-NAME-001', $record->product_code);
        $this->assertEquals($this->itemAtmNamed->id, $record->item_id);
        $this->assertEquals(3, $record->qty);
        // Price enrichment from Item master (18.500 * 3 = 55.500)
        $this->assertEquals(18500, (float) $record->unit_price);
        $this->assertEquals(55500, (float) $record->total_price);
        $this->assertEquals('VALID', $record->status);
    }

    public function test_invalid_records_routed_to_reject_queue_with_specific_errors(): void
    {
        // Mixed file: invalid branch, invalid product, missing cardholder on NAMED, missing price (POC-22, POC-27)
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,qty\n".
            "REF-ERR-01,KC-INVALID,ATM-INST-001,INSTANT,NASABAH,6013000000000001,1\n".
            "REF-ERR-02,KC-SBY,SKU-NOT-EXIST,INSTANT,NASABAH,6013000000000002,1\n".
            "REF-ERR-03,KC-SBY,ATM-NAME-001,NAMED,,6013000000000003,1\n".
            "REF-ERR-04,KC-SBY,ATM-NOPRICE-001,INSTANT,NASABAH,6013000000000004,1\n".
            "REF-OK-01,KC-SBY,ATM-INST-001,INSTANT,NASABAH VALID,6013000000000005,1\n";

        $file = UploadedFile::fake()->createWithContent('reject_queue_test.csv', $csvContent);

        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file,
            'source' => 'MANUAL_UPLOAD',
        ]);

        $embossFile = EmbossFile::latest()->first();
        $this->assertEquals(5, $embossFile->total_records);
        $this->assertEquals(1, $embossFile->success_records);
        $this->assertEquals(4, $embossFile->reject_records);
        $this->assertEquals('PARTIAL_SUCCESS', $embossFile->status);

        // Verify specific error reasons in reject queue
        $err1 = EmbossRecord::where('external_reference_id', 'REF-ERR-01')->first();
        $this->assertEquals('INVALID', $err1->status);
        $this->assertStringContainsString('Kode cabang', $err1->error_message);

        $err2 = EmbossRecord::where('external_reference_id', 'REF-ERR-02')->first();
        $this->assertEquals('INVALID', $err2->status);
        $this->assertStringContainsString('SKU barang', $err2->error_message);

        $err3 = EmbossRecord::where('external_reference_id', 'REF-ERR-03')->first();
        $this->assertEquals('INVALID', $err3->status);
        $this->assertStringContainsString('Nama pemegang kartu wajib diisi', $err3->error_message);

        $err4 = EmbossRecord::where('external_reference_id', 'REF-ERR-04')->first();
        $this->assertEquals('INVALID', $err4->status);
        $this->assertStringContainsString('Harga pengadaan', $err4->error_message);
    }

    public function test_rejected_record_can_be_corrected_and_reprocessed(): void
    {
        // POC-22: edit record in reject queue and reprocess
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,qty\n".
            "REF-FIX-01,KC-WRONG,ATM-INST-001,INSTANT,NASABAH,6013000000000010,2\n";

        $file = UploadedFile::fake()->createWithContent('fix_test.csv', $csvContent);
        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file,
            'source' => 'ATM_CENTER',
        ]);

        $embossFile = EmbossFile::latest()->first();
        $record = EmbossRecord::where('external_reference_id', 'REF-FIX-01')->first();
        $this->assertEquals('INVALID', $record->status);

        // Correct the branch code to KC-SBY
        $response = $this->actingAs($this->admin)->post(route('emboss.records.reprocess', $record->id), [
            'branch_code' => 'KC-SBY',
            'product_code' => 'ATM-INST-001',
            'cardholder_name' => 'NASABAH KOREKSI',
            'qty' => 2,
        ]);

        $response->assertSessionHas('success');
        $record->refresh();
        $this->assertEquals('VALID', $record->status);
        $this->assertNull($record->error_message);
        $this->assertEquals($this->orgSby->id, $record->organization_id);
        $this->assertEquals(30000, (float) $record->total_price);

        // Parent file counts updated
        $embossFile->refresh();
        $this->assertEquals(1, $embossFile->success_records);
        $this->assertEquals(0, $embossFile->reject_records);
        $this->assertEquals('COMPLETED', $embossFile->status);
    }

    public function test_duplicate_file_and_duplicate_reference_idempotency(): void
    {
        // POC-23: Idempotency
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,qty\n".
            "REF-IDEM-01,KC-SBY,ATM-INST-001,INSTANT,NASABAH A,6013000000000021,1\n".
            "REF-IDEM-02,KC-SBY,ATM-INST-001,INSTANT,NASABAH B,6013000000000022,1\n";

        $file1 = UploadedFile::fake()->createWithContent('idempotency_1.csv', $csvContent);
        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file1,
            'source' => 'CARD_CORE_SYSTEM',
        ]);

        // Attempting to re-upload exact identical file must be rejected (Level 1)
        $file2 = UploadedFile::fake()->createWithContent('idempotency_duplicate.csv', $csvContent);
        $response = $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file2,
            'source' => 'CARD_CORE_SYSTEM',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, EmbossFile::count(), 'Check 1: EmbossFile count after duplicate upload');

        // Upload new file containing one existing reference ID (Level 2)
        $csvContentPart2 = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,qty\n".
            "REF-IDEM-01,KC-SBY,ATM-INST-001,INSTANT,NASABAH REPEAT,6013000000000021,1\n".
            "REF-IDEM-03,KC-MLG,ATM-INST-001,INSTANT,NASABAH C,6013000000000023,1\n";

        $file3 = UploadedFile::fake()->createWithContent('idempotency_part2.csv', $csvContentPart2);
        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file3,
            'source' => 'CARD_CORE_SYSTEM',
        ]);

        $file3Model = EmbossFile::latest('id')->first();
        $this->assertEquals(2, $file3Model->total_records, 'Check 2: total_records');
        $this->assertEquals(1, $file3Model->success_records, 'Check 3: success_records');
        $this->assertEquals(1, $file3Model->duplicate_records, 'Check 4: duplicate_records');

        $dupRecord = EmbossRecord::where('emboss_file_id', $file3Model->id)
            ->where('external_reference_id', 'REF-IDEM-01')
            ->first();
        $this->assertEquals('DUPLICATE', $dupRecord->status, 'Check 5: status is DUPLICATE');
        $this->assertStringContainsString('Duplikat sistem', $dupRecord->error_message, 'Check 6: error_message');
    }

    public function test_emboss_records_can_be_grouped_into_orders(): void
    {
        // POC-29: Grouping by branch and card type
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,qty\n".
            "REF-GRP-01,KC-SBY,ATM-INST-001,INSTANT,NASABAH 1,6013000000000031,1\n".
            "REF-GRP-02,KC-SBY,ATM-INST-001,INSTANT,NASABAH 2,6013000000000032,1\n".
            "REF-GRP-03,KC-SBY,ATM-NAME-001,NAMED,NASABAH 3,6013000000000033,1\n".
            "REF-GRP-04,KC-MLG,ATM-INST-001,INSTANT,NASABAH 4,6013000000000034,1\n";

        $file = UploadedFile::fake()->createWithContent('grouping_order_test.csv', $csvContent);
        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file,
            'source' => 'BRANCH_PORTAL',
        ]);

        $embossFile = EmbossFile::latest()->first();

        // Generate orders
        $response = $this->actingAs($this->admin)->post(route('emboss.generate_orders', $embossFile->id), [
            'delivery_method' => 'COURIER',
        ]);

        $response->assertRedirect(route('orders.index'));

        // 3 Orders should be generated: (KC-SBY INSTANT, KC-SBY NAMED, KC-MLG INSTANT)
        $orders = Order::where('emboss_file_id', $embossFile->id)->get();
        $this->assertCount(3, $orders);

        // Verify KC-SBY INSTANT order has 2 cards
        $sbyInstantOrder = $orders->first(fn ($o) => $o->requesting_organization_id === $this->orgSby->id && str_contains($o->notes, 'INSTANT'));
        $this->assertNotNull($sbyInstantOrder);
        $this->assertEquals(30000, (float) $sbyInstantOrder->total_estimated_value);
        $this->assertEquals(2, $sbyInstantOrder->items->first()->qty_requested);

        // Emboss records must be flagged as PROCESSED_TO_ORDER
        $this->assertEquals(4, EmbossRecord::where('emboss_file_id', $embossFile->id)->where('status', 'PROCESSED_TO_ORDER')->count());
    }

    public function test_pickup_at_kp_requires_nip_name_and_position(): void
    {
        // POC-28: Pickup at KP mandatory fields validation
        $csvContent = "reference_id,branch_code,product_code,card_type,cardholder_name,card_number,qty\n".
            "REF-PKP-01,KC-SBY,ATM-INST-001,INSTANT,NASABAH PICKUP,6013000000000041,1\n";

        $file = UploadedFile::fake()->createWithContent('pickup_kp_test.csv', $csvContent);
        $this->actingAs($this->admin)->post(route('emboss.store'), [
            'emboss_file' => $file,
            'source' => 'MANUAL_UPLOAD',
        ]);

        $embossFile = EmbossFile::latest()->first();

        // 1. Missing NIP/Name/Position must fail validation
        $failResponse = $this->actingAs($this->admin)->post(route('emboss.generate_orders', $embossFile->id), [
            'delivery_method' => 'PICKUP_KP',
            'pickup_nip' => '',
            'pickup_name' => '',
            'pickup_position' => '',
        ]);

        $failResponse->assertSessionHasErrors(['pickup_nip', 'pickup_name', 'pickup_position']);

        // 2. Complete data succeeds
        $successResponse = $this->actingAs($this->admin)->post(route('emboss.generate_orders', $embossFile->id), [
            'delivery_method' => 'PICKUP_KP',
            'pickup_nip' => '199001012015031001',
            'pickup_name' => 'Hendro Wijaya',
            'pickup_position' => 'Staff Operasional Kantor Cabang',
            'pickup_notes' => 'Pengambilan langsung kartu ATM di KP',
        ]);

        $successResponse->assertRedirect(route('orders.index'));

        $order = Order::where('emboss_file_id', $embossFile->id)->first();
        $this->assertEquals('PICKUP_KP', $order->delivery_method);
        $this->assertEquals('199001012015031001', $order->pickup_pic_nip);
        $this->assertEquals('Hendro Wijaya', $order->pickup_pic_name);
        $this->assertEquals('Staff Operasional Kantor Cabang', $order->pickup_pic_position);

        // Verify timeline reflects Ambil di KP
        $timeline = $order->getWorkflowTimeline();
        $step5 = collect($timeline)->firstWhere('step', 5);
        $this->assertNotNull($step5);
        $this->assertStringContainsString('Ambil di KP', $step5['label']);
    }

    public function test_sensitive_card_data_is_masked_in_presentation(): void
    {
        // POC-54: Sensitive Data Masking
        $embossFile = EmbossFile::create([
            'file_id' => 'EMB/202609/9999',
            'file_name' => 'masking_test.csv',
            'file_hash' => 'dummy_hash_masking',
            'source' => 'MANUAL_UPLOAD',
            'status' => 'COMPLETED',
            'uploaded_by_user_id' => $this->admin->id,
        ]);

        $record = EmbossRecord::create([
            'emboss_file_id' => $embossFile->id,
            'external_reference_id' => 'REF-MASK-01',
            'branch_code' => 'KC-SBY',
            'product_code' => 'ATM-INST-001',
            'card_type' => 'INSTANT',
            'cardholder_name' => 'MUHAMMAD RIZKY PRATAMA',
            'card_number' => '6013028899001234',
            'qty' => 1,
            'unit_price' => 15000,
            'total_price' => 15000,
            'status' => 'VALID',
        ]);

        // Card number must show 6013-02**-****-1234
        $this->assertEquals('6013-02**-****-1234', $record->masked_card_number);

        // Name must mask parts of the name
        $this->assertStringStartsWith('MUHAMMAD', $record->masked_cardholder_name);
        $this->assertStringContainsString('*', $record->masked_cardholder_name);
    }
}
