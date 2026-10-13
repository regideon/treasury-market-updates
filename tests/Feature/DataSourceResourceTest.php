<?php

use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the data sources admin page renders and lists sources', function () {
    $source = DataSource::factory()->create(['name' => 'BSP - USD/PHP Reference Rate']);

    $response = $this->actingAs(User::factory()->create())->get('/rpanel/data-sources');

    $response->assertOk();
    $response->assertSee('BSP - USD/PHP Reference Rate');
});
