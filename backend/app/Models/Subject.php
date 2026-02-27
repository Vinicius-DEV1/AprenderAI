<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'type'];

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => mb_strtoupper(trim($value), 'UTF-8'),
        );
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class);
    }
}
