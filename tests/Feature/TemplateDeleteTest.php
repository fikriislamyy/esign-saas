<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Template;
use App\Models\TemplateSignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $this->organization = Organization::create(['name' => 'Acme']);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'owner',
        ]);
    }

    private function templateFor(Organization $organization, string $name = 'nda'): Template
    {
        $path = "templates/{$name}.pdf";

        Storage::disk('documents')->put($path, '%PDF-1.4 fake');

        return Template::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'file_path' => $path,
            'file_size' => 14,
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_deleting_a_template_removes_the_row_its_fields_and_its_file(): void
    {
        $template = $this->templateFor($this->organization);

        TemplateSignatureField::create([
            'template_id' => $template->id,
            'page' => 1, 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 40,
        ]);
        TemplateSignatureField::create([
            'template_id' => $template->id,
            'page' => 2, 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 40,
        ]);

        $response = $this->actingAs($this->user)->delete("/templates/{$template->id}");

        $response->assertRedirect('/templates');
        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
        $this->assertDatabaseCount('template_signature_fields', 0);
        Storage::disk('documents')->assertMissing('templates/nda.pdf');
    }

    public function test_a_template_from_another_organisation_cannot_be_deleted(): void
    {
        $other = Organization::create(['name' => 'Rival']);
        $template = $this->templateFor($other);

        $response = $this->actingAs($this->user)->delete("/templates/{$template->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
        Storage::disk('documents')->assertExists('templates/nda.pdf');
    }

    public function test_a_guest_cannot_delete_a_template(): void
    {
        $template = $this->templateFor($this->organization);

        $this->delete("/templates/{$template->id}")->assertRedirect('/login');

        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }
}
