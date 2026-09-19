<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateLimitTest extends TestCase
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

    private function seedTemplates(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Template::create([
                'organization_id' => $this->organization->id,
                'name' => "template-{$i}",
                'file_path' => "templates/template-{$i}.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
            ]);
        }
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\n".str_repeat('a', 1000));
    }

    public function test_the_fifth_template_is_accepted(): void
    {
        $this->seedTemplates(4);

        $this->actingAs($this->user)
            ->post('/templates', ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('templates', 5);
    }

    public function test_the_sixth_template_is_rejected(): void
    {
        $this->seedTemplates(5);

        $response = $this->actingAs($this->user)->post('/templates', ['file' => $this->pdf()]);

        $response->assertSessionHasErrors([
            'file' => 'You have reached the limit of 5 templates. Delete one to upload another.',
        ]);
        $this->assertDatabaseCount('templates', 5);
        Storage::disk('documents')->assertMissing('templates/new.pdf');
    }

    public function test_deleting_one_makes_room_for_another(): void
    {
        $this->seedTemplates(5);
        $victim = Template::where('name', 'template-3')->first();

        $this->actingAs($this->user)->delete("/templates/{$victim->id}");

        $this->actingAs($this->user)
            ->post('/templates', ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('templates', 5);
    }

    public function test_the_limit_is_per_organisation(): void
    {
        $this->seedTemplates(5);

        $other = Organization::create(['name' => 'Rival']);
        $otherUser = User::factory()->create([
            'organization_id' => $other->id,
            'role' => 'owner',
        ]);

        $this->actingAs($otherUser)
            ->post('/templates', ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();
    }

    public function test_the_templates_page_reports_the_quota(): void
    {
        $this->seedTemplates(2);

        $this->actingAs($this->user)
            ->get('/templates')
            ->assertInertia(fn ($page) => $page
                ->where('templateQuota.used', 2)
                ->where('templateQuota.limit', 5)
            );
    }
}
