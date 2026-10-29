<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/kategori', [
            'name' => 'Elektronik',
            'description' => 'Barang-barang elektronik',
        ]);

        $response->assertRedirect(route('kategori.index'));
        $this->assertDatabaseHas('categories', [
            'name' => 'Elektronik',
            'slug' => 'elektronik',
        ]);
    }

    public function test_category_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/kategori', [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Nama Lama']);

        $response = $this->actingAs($admin)->put("/kategori/{$category->id}", [
            'name' => 'Nama Baru',
            'description' => 'Deskripsi baru',
        ]);

        $response->assertRedirect(route('kategori.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Nama Baru',
        ]);
    }

    public function test_admin_can_delete_category_without_related_barang(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->delete("/kategori/{$category->id}");

        $response->assertRedirect(route('kategori.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}