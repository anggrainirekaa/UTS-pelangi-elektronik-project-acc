<?php

namespace App\Models;

use App\Models\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barang extends Model
{
    use HasFactory, LogsAudit, SoftDeletes;

    /** Saldo stok dicatat di kartu stok (stock_movements), bukan di audit atribut. */
    protected static function auditNever(): array
    {
        return ['updated_at', 'created_at', 'deleted_at', 'stock'];
    }

    protected $fillable = [
        'category_id',
        'supplier_id',
        'code',
        'barcode',
        'name',
        'description',
        'unit',
        'purchase_price',
        'selling_price',
        'stock',
        'minimum_stock',
        'image',
        'is_active',
        'tracks_expiry',
        'expiry_warning_days',
    ];

    protected $casts = [
        'purchase_price' => 'integer',
        'selling_price' => 'integer',
        'stock' => 'integer',
        'minimum_stock' => 'integer',
        'is_active' => 'boolean',
        'tracks_expiry' => 'boolean',
        'expiry_warning_days' => 'integer',
    ];

    /**
     * Auto-generate kode barang. withTrashed() agar kode barang yang sudah di-soft-delete
     * tidak terpakai ulang (kolom code bersifat unik di database).
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Barang $barang) {
            if (empty($barang->barcode)) {
                $barang->barcode = null; // string kosong dari form -> NULL agar unik tidak bentrok
            }

            if (empty($barang->code)) {
                $barang->code = 'BRG-'.str_pad((string) (static::withTrashed()->max('id') + 1), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /** Catat riwayat harga hanya untuk perubahan yang dilakukan lewat model (edit manual). */
    protected static function booted(): void
    {
        static::created(function (Barang $b) {
            PriceHistory::create([
                'barang_id' => $b->id, 'user_id' => \Illuminate\Support\Facades\Auth::id(),
                'new_purchase_price' => $b->purchase_price, 'new_selling_price' => $b->selling_price, 'source' => 'awal',
            ]);
        });

        static::updated(function (Barang $b) {
            if ($b->wasChanged(['purchase_price', 'selling_price'])) {
                PriceHistory::create([
                    'barang_id' => $b->id, 'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'old_purchase_price' => $b->getOriginal('purchase_price'), 'new_purchase_price' => $b->purchase_price,
                    'old_selling_price' => $b->getOriginal('selling_price'), 'new_selling_price' => $b->selling_price,
                    'source' => 'manual',
                ]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockOuts(): HasMany
    {
        return $this->hasMany(StockOut::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->latest();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /** Produk yang tampil di katalog pelanggan. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'minimum_stock');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    /** Pencarian pintar: nama, SKU, barcode, kategori, atau supplier. Tiap kata harus cocok di salah satu kolom. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $words = preg_split('/\s+/', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $q) use ($like) {
                $q->where('barangs.name', 'like', $like)
                    ->orWhere('barangs.code', 'like', $like)
                    ->orWhere('barangs.barcode', 'like', $like)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /** Barang yang sudah dipakai di transaksi tidak boleh di-hard delete. */
    public function hasTransactions(): bool
    {
        return $this->purchaseItems()->exists()
            || $this->saleItems()->exists()
            || $this->stockOuts()->exists()
            || StockTransfer::where('barang_id', $this->id)->exists();
    }

    /** Kode barang = SKU (kolom `code`). Alias agar istilah SKU konsisten di seluruh aplikasi. */
    public function getSkuAttribute(): string
    {
        return (string) $this->code;
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image) {
            return asset('images/no-image.png');
        }

        // Gambar bawaan (demo) berada di public/images/..., unggahan pengguna di storage/.
        return str_starts_with($this->image, 'images/') ? asset($this->image) : asset('storage/'.$this->image);
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    /** ok | low | out */
    public function stockStatus(): string
    {
        return $this->isOutOfStock() ? 'out' : ($this->isLowStock() ? 'low' : 'ok');
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->minimum_stock;
    }
}
