<?php

namespace Dotlib\BrunoDocs\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * User stored only for the package HTTP tests.
 */
class User extends Authenticatable
{
    /**
     * @var list<string>
     */
    protected $guarded = [];
}
