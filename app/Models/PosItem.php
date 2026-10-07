<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosItem extends Model
{
    protected $table = 'pos_items';

    protected $fillable = [
        'name', 'category', 'price',
        'stock_quantity', 'is_tracked', 'reorder_level',
        'kitchen_hours_only', 'is_available', 'sort_order',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'stock_quantity'      => 'integer',
        'is_tracked'          => 'boolean',
        'reorder_level'       => 'integer',
        'kitchen_hours_only'  => 'boolean',
        'is_available'        => 'boolean',
    ];

    protected static ?PosItem $cachedEgg = null;

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Determine if this item is the Master Egg item (Egg (Fried/Boiled)).
     */
    public function isEggItem(): bool
    {
        $name = strtolower(trim($this->name ?? ''));
        return $name === 'egg (fried/boiled)'
            || $name === 'egg'
            || str_starts_with($name, 'egg (')
            || (str_contains($name, 'egg') && strtolower(trim($this->category ?? '')) === 'kitchen extras');
    }

    /**
     * Determine if this item is a breakfast meal (Silog or Breakfast category).
     * Each breakfast item now has its OWN independent stock (not pooled with egg).
     * However, ordering one still triggers a relational egg deduction via requiresEgg().
     */
    public function isBreakfastItem(): bool
    {
        if ($this->isEggItem()) {
            return false;
        }

        $category = strtolower(trim($this->category ?? ''));
        $name = strtolower(trim($this->name ?? ''));

        return $category === 'breakfast' || str_contains($name, 'silog');
    }

    /**
     * Determine if ordering this dish also deducts from the raw egg pantry stock.
     * Covers: all silog/breakfast meals, Calamares (egg batter), Sizzling Sisig w/ Egg.
     */
    public function requiresEgg(): bool
    {
        if ($this->isEggItem()) {
            return false;
        }

        $name = strtolower(trim($this->name ?? ''));

        if (str_contains($name, 'silog') || strtolower(trim($this->category ?? '')) === 'breakfast') {
            return true;
        }

        if (str_contains($name, 'w/ egg') || str_contains($name, 'w/egg') || str_contains($name, 'with egg')) {
            return true;
        }

        if (str_contains($name, 'calamares')) {
            return true;
        }

        return false;
    }

    /**
     * Number of raw eggs consumed per serving of this dish (default: 1).
     */
    public function getEggRequirementQty(): int
    {
        return 1;
    }

    /**
     * Retrieve the master egg item used for relational deductions.
     */
    public static function getMasterEggItem(): ?self
    {
        return static::where('name', 'Egg (Fried/Boiled)')
            ->orWhere('name', 'like', '%Egg (Fried/Boiled)%')
            ->orWhere(function ($q) {
                $q->where('name', 'like', '%Egg%')->where('category', 'Kitchen Extras');
            })
            ->first();
    }

    /**
     * Clear master egg cached instance.
     */
    public static function clearEggCache(): void
    {
        static::$cachedEgg = null;
    }

    /**
     * Dynamically resolve item availability.
     * For egg-dependent dishes: unavailable if the dish itself is out OR eggs are exhausted.
     * Each dish has its own is_available flag independent of other dishes.
     */
    public function getIsAvailableAttribute($value): bool
    {
        if (!(bool) $value) {
            return false;
        }

        if ($this->requiresEgg()) {
            $egg = static::getMasterEggItem();
            if ($egg && $egg->id !== $this->id) {
                $eggStock = (int) $egg->getRawOriginal('stock_quantity', 0);
                $eggAvailable = (bool) $egg->getRawOriginal('is_available', true);
                if ($eggStock <= 0 || !$eggAvailable) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if this item can be ordered right now based on kitchen hours.
     */
    public function isOrderable(): bool
    {
        if (!$this->is_available) return false;
        if (!$this->kitchen_hours_only) return true;

        $hour = (int) now()->format('G');
        return $hour >= 6 && $hour < 22;
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->orderBy('sort_order');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}