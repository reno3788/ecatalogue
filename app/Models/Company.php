<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Company extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'abeta_id',
        'status',
        'address',
        'phone',
        'contact_person',
        'taxid',
        'taxaddress',
        'punchout_enabled',
        'punchout_gateway',
        'punchout_url',
        'punchout_identity',
        'punchout_shared_secret',
    ];

    protected $casts = [
        'punchout_enabled' => 'boolean',
    ];

    /**
     * Normalize the status attribute: converts DB boolean/int to 'Active'/'Inactive'.
     * This ensures legacy boolean records display and edit correctly.
     */
    public function getStatusAttribute($value): string
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 'Active';
        }
        if ($value === false || $value === 0 || $value === '0') {
            return 'Inactive';
        }
        // Already a string like 'Active' or 'Inactive'
        return (string) $value;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function priceLists()
    {
        return $this->hasMany(ClientPriceList::class);
    }

    public function catalogVisibility()
    {
        return $this->hasMany(CatalogVisibility::class);
    }
}
