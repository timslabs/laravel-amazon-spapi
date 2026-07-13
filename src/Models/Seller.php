<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    protected $table = 'spapi_sellers';

    protected $fillable = [
        'name',
    ];

    public function credentials(): HasMany
    {
        return $this->hasMany(Credentials::class);
    }
}
