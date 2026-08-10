<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Builder;
use Tests\TestCase;

class SchemaStringLengthTest extends TestCase
{
    public function test_default_string_length_is_191_for_mysql_index_compatibility(): void
    {
        $this->assertSame(191, Builder::$defaultStringLength);
    }
}
