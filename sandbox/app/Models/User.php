<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sandbox user model for conversation ownership.
 *
 * Minimal model — no auth or passwords. Used as the polymorphic
 * owner for Atlas conversations and memory scoping.
 */
class User extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'email',
    ];
}
