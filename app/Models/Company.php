<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'abeta_id',
        'status',
        'address',
    ];

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
