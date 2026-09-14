<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'required_date' => 'date',
        'total_estimated_value' => 'decimal:2',
        'is_overbudget' => 'boolean',
        'projected_utilization' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function embossFile(): BelongsTo
    {
        return $this->belongsTo(EmbossFile::class);
    }

    public function requestingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'requesting_organization_id');
    }

    public function requestingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'requesting_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(OrderAllocation::class, OrderItem::class);
    }

    public function switchingStocks(): HasMany
    {
        return $this->hasMany(SwitchingStock::class);
    }

    public function pickings(): HasMany
    {
        return $this->hasMany(WarehousePicking::class);
    }

    public function picking(): HasOne
    {
        return $this->hasOne(WarehousePicking::class)->latestOfMany();
    }

    public function packings(): HasMany
    {
        return $this->hasMany(WarehousePacking::class);
    }

    public function packing(): HasOne
    {
        return $this->hasOne(WarehousePacking::class)->latestOfMany();
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(Receiving::class);
    }

    public function receiving(): HasOne
    {
        return $this->hasOne(Receiving::class)->latestOfMany();
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class)->latestOfMany();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')->where('auditable_type', self::class);
    }

    /**
     * Get the complete workflow timeline with status, date, processor actor, and description.
     */
    public function getWorkflowTimeline(): array
    {
        $this->loadMissing([
            'requester',
            'approver',
            'requestingOrganization',
            'picking.picker',
            'packing.packer',
            'shipment.dispatcher',
            'shipment.courier',
            'receiving.receiver',
            'settlement.creator',
            'auditLogs.user',
        ]);

        $isRejected = $this->status === 'REJECTED';
        $auditMap = $this->auditLogs->groupBy('action');

        $timeline = [];

        // 1. Pengajuan (SUBMITTED)
        $subDate = $this->submitted_at ?? $this->created_at;
        $timeline[] = [
            'step' => 1,
            'code' => 'SUBMITTED',
            'label' => 'Order Diajukan (Submitted)',
            'status_state' => 'DONE',
            'date_formatted' => $subDate ? $subDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $this->requester?->name ?? 'User Unit',
            'actor_role' => $this->requester?->role_display_name ?? 'Maker Cabang',
            'description' => $this->notes ?: ('Order diajukan oleh '.($this->requestingOrganization?->name ?? 'Unit Peminta')),
            'badge_label' => 'SELESAI',
            'badge_class' => 'text-bg-success',
        ];

        // If Rejected at approval stage
        if ($isRejected) {
            $rejectLog = $auditMap->get('REJECT_ORDER')?->last();
            $rejDate = $rejectLog ? $rejectLog->created_at : $this->updated_at;
            $rejUser = $rejectLog?->user ?? $this->approver;
            $rejReason = $rejectLog?->new_values['reason'] ?? ($this->notes ?: 'Order tidak disetujui');

            $timeline[] = [
                'step' => 2,
                'code' => 'REJECTED',
                'label' => 'Persetujuan Order (Ditolak / Rejected)',
                'status_state' => 'REJECTED',
                'date_formatted' => $rejDate ? $rejDate->format('d M Y, H:i').' WIB' : '-',
                'actor_name' => $rejUser?->name ?? 'Pejabat Pemutus',
                'actor_role' => $rejUser?->role_display_name ?? 'Order Approver',
                'description' => 'Ditolak: '.$rejReason,
                'badge_label' => 'DITOLAK',
                'badge_class' => 'text-bg-danger',
            ];

            return $timeline;
        }

        // 2. Persetujuan & Alokasi (APPROVED / ALLOCATED)
        $isApproved = in_array($this->status, ['APPROVED', 'ALLOCATED', 'PICKING', 'READY_TO_SHIP', 'IN_TRANSIT', 'RECEIVED', 'COMPLETED']);
        $isWaitingApproval = in_array($this->status, ['SUBMITTED', 'WAITING_APPROVAL']);
        $appLog = $auditMap->get('APPROVE_ORDER')?->last();
        $appDate = $this->approved_at ?? $appLog?->created_at;
        $appUser = $this->approver ?? $appLog?->user;

        $timeline[] = [
            'step' => 2,
            'code' => 'APPROVED',
            'label' => 'Persetujuan & Alokasi Stok (Approved)',
            'status_state' => $isApproved ? 'DONE' : ($isWaitingApproval ? 'CURRENT' : 'PENDING'),
            'date_formatted' => ($isApproved && $appDate) ? $appDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $isApproved ? ($appUser?->name ?? 'Pejabat Pemutus') : 'Menunggu Approval',
            'actor_role' => $isApproved ? ($appUser?->role_display_name ?? 'Order Approver') : 'Pejabat Berwenang',
            'description' => $isApproved
                ? 'Order telah disetujui & alokasi stok persediaan direservasi otomatis dari Gudang Logistik.'
                : 'Menunggu telaah dan otorisasi oleh Pejabat Pemutus (Approver).',
            'badge_label' => $isApproved ? 'DISETUJUI' : 'MENUNGGU APPROVAL',
            'badge_class' => $isApproved ? 'text-bg-success' : 'text-bg-warning',
        ];

        // 3. Pengambilan Barang (PICKING)
        $picking = $this->picking;
        $isPickingDone = in_array($this->status, ['PICKING', 'READY_TO_SHIP', 'IN_TRANSIT', 'RECEIVED', 'COMPLETED']) && $picking;
        $isPickingCurrent = ($this->status === 'ALLOCATED' || $this->status === 'PICKING') && ! $picking;
        $pickDate = $picking?->picked_at ?? $picking?->created_at;

        $timeline[] = [
            'step' => 3,
            'code' => 'PICKING',
            'label' => 'Pengambilan Fisik Gudang (Picking)',
            'status_state' => $isPickingDone ? 'DONE' : ($isPickingCurrent ? 'CURRENT' : 'PENDING'),
            'date_formatted' => $pickDate ? $pickDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $picking?->picker?->name ?? ($isPickingCurrent ? 'Petugas Gudang' : '-'),
            'actor_role' => 'Logistics Warehouse Officer',
            'description' => $picking
                ? ('Pengambilan fisik barang dari rak gudang selesai ('.$picking->picking_number.').')
                : ($isApproved ? 'Menunggu antrean pengambilan barang dari rak penyimpanan gudang logistik.' : '-'),
            'badge_label' => $isPickingDone ? 'SELESAI' : ($isPickingCurrent ? 'PROSES PICKING' : 'MENUNGGU'),
            'badge_class' => $isPickingDone ? 'text-bg-success' : ($isPickingCurrent ? 'text-bg-primary' : 'text-bg-secondary'),
        ];

        // 4. Pengepakan (PACKING / READY_TO_SHIP)
        $packing = $this->packing;
        $isPackingDone = in_array($this->status, ['READY_TO_SHIP', 'IN_TRANSIT', 'RECEIVED', 'COMPLETED']) && $packing;
        $isPackingCurrent = $this->status === 'READY_TO_SHIP' && ! $packing;
        $packDate = $packing?->packed_at ?? $packing?->created_at;

        $timeline[] = [
            'step' => 4,
            'code' => 'READY_TO_SHIP',
            'label' => 'Pengepakan Koli (Packing)',
            'status_state' => $isPackingDone ? 'DONE' : ($isPackingCurrent ? 'CURRENT' : 'PENDING'),
            'date_formatted' => $packDate ? $packDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $packing?->packer?->name ?? ($isPackingDone || $isPackingCurrent ? 'Petugas Packing' : '-'),
            'actor_role' => 'Logistics Packing Officer',
            'description' => $packing
                ? ('Barang dikemas rapi: '.$packing->koli_count.' Koli ('.number_format($packing->total_weight_kg, 1).' kg). Siap serah terima ekspedisi.')
                : ($isPickingDone ? 'Menunggu proses pengepakan koli dan penimbangan berat paket.' : '-'),
            'badge_label' => $isPackingDone ? 'SELESAI' : ($isPackingCurrent ? 'PROSES PACKING' : 'MENUNGGU'),
            'badge_class' => $isPackingDone ? 'text-bg-success' : ($isPackingCurrent ? 'text-bg-primary' : 'text-bg-secondary'),
        ];

        // 5. Pengiriman Ekspedisi (IN_TRANSIT) / Ambil di KP (POC-28)
        $shipment = $this->shipment;
        $isShipmentDone = in_array($this->status, ['IN_TRANSIT', 'RECEIVED', 'COMPLETED']) && $shipment;
        $isShipmentCurrent = $this->status === 'IN_TRANSIT' && $shipment;
        $shipDate = $shipment?->dispatched_at ?? $shipment?->created_at;

        $isPickupKp = ($this->delivery_method === 'PICKUP_KP') || ($shipment && $shipment->delivery_method === 'PICKUP_KP');
        $picName = $this->pickup_pic_name ?: ($shipment?->pickup_pic_name);
        $picNip = $this->pickup_pic_nip ?: ($shipment?->pickup_pic_nip);
        $picPos = $this->pickup_pic_position ?: ($shipment?->pickup_pic_position);

        $label = $isPickupKp ? 'Penyerahan / Ambil di KP (Pickup)' : 'Pengiriman & Manifest Ekspedisi (In-Transit)';
        $actorName = $isPickupKp
            ? ($picName ?: ($shipment?->dispatcher?->name ?? 'PIC Cabang'))
            : ($shipment?->dispatcher?->name ?? ($shipment?->courier?->name ?? '-'));
        $actorRole = $isPickupKp ? 'PIC Pengambil Unit (Ambil di KP)' : ($shipment?->courier?->name ? ('Kurir: '.$shipment->courier->name) : 'Dispatch Officer');

        $desc = '-';
        if ($isPickupKp) {
            $desc = $shipment
                ? ("Barang diserahkan via Ambil di KP kepada {$picName} (NIP: {$picNip}, Jabatan: {$picPos}).")
                : ($isPackingDone ? 'Barang selesai dikemas, menunggu pengambilan langsung oleh PIC Cabang di Kantor Pusat.' : '-');
        } else {
            $desc = $shipment
                ? ('Manifest '.$shipment->manifest_number.' diterbitkan. No. Resi: '.($shipment->tracking_number ?? '-').' dalam perjalanan.')
                : ($isPackingDone ? 'Menunggu penyerahan paket ke jasa ekspedisi/kurir dan cetak surat jalan.' : '-');
        }

        $timeline[] = [
            'step' => 5,
            'code' => 'IN_TRANSIT',
            'label' => $label,
            'status_state' => $isShipmentDone ? 'DONE' : 'PENDING',
            'date_formatted' => $shipDate ? $shipDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $actorName,
            'actor_role' => $actorRole,
            'description' => $desc,
            'badge_label' => $isShipmentCurrent ? ($isPickupKp ? 'SIAP AMBIL' : 'IN-TRANSIT') : ($isShipmentDone ? 'TERSERAHKAN' : 'MENUNGGU'),
            'badge_class' => $isShipmentCurrent ? 'text-bg-info' : ($isShipmentDone ? 'text-bg-success' : 'text-bg-secondary'),
        ];

        // 6. Penerimaan Cabang (RECEIVED)
        $receiving = $this->receiving;
        $isReceivedDone = in_array($this->status, ['RECEIVED', 'COMPLETED']) && $receiving;
        $recDate = $receiving?->receipt_date ? Carbon::parse($receiving->receipt_date) : $receiving?->created_at;

        $timeline[] = [
            'step' => 6,
            'code' => 'RECEIVED',
            'label' => 'Penerimaan Fisik di Cabang (Received)',
            'status_state' => $isReceivedDone ? 'DONE' : 'PENDING',
            'date_formatted' => $recDate ? $recDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $receiving?->receiver?->name ?? ($isShipmentDone ? 'Petugas Cabang' : '-'),
            'actor_role' => 'Branch Receiving Officer',
            'description' => $receiving
                ? ('Penerimaan fisik barang telah diverifikasi di cabang dengan Berita Acara: '.$receiving->receiving_number.'.')
                : ($isShipmentDone ? 'Paket dalam perjalanan menuju unit kerja peminta.' : '-'),
            'badge_label' => $isReceivedDone ? 'DITERIMA' : 'MENUNGGU',
            'badge_class' => $isReceivedDone ? 'text-bg-success' : 'text-bg-secondary',
        ];

        // 7. Settlement Finansial (COMPLETED)
        $settlement = $this->settlement;
        $isSettledDone = $this->status === 'COMPLETED' && $settlement;
        $settleDate = $settlement?->posted_at ?? $settlement?->created_at ?? $this->completed_at;

        $timeline[] = [
            'step' => 7,
            'code' => 'COMPLETED',
            'label' => 'Settlement Finansial Biaya (Completed)',
            'status_state' => $isSettledDone ? 'DONE' : 'PENDING',
            'date_formatted' => $settleDate ? $settleDate->format('d M Y, H:i').' WIB' : '-',
            'actor_name' => $settlement?->creator?->name ?? ($isReceivedDone ? 'Finance Officer' : '-'),
            'actor_role' => 'Financial Accounting Officer',
            'description' => $settlement
                ? ('Posting jurnal pembebanan anggaran antarunit selesai ('.$settlement->settlement_number.'). Seluruh alur tuntas.')
                : ($isReceivedDone ? 'Menunggu pembuatan dan posting jurnal pembebanan settlement keuangan.' : '-'),
            'badge_label' => $isSettledDone ? 'SELESAI' : 'MENUNGGU',
            'badge_class' => $isSettledDone ? 'text-bg-success' : 'text-bg-secondary',
        ];

        return $timeline;
    }
}
