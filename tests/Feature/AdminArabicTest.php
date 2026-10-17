<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArabicTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    private Lesson $lesson;

    private Problem $problem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'title_ar' => 'الأساسيات', 'track' => 'basics', 'sort_order' => 1]);
        $this->lesson = $this->module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'title_ar' => 'الإحماء', 'body' => 'English body', 'body_ar' => 'نص عربي', 'sort_order' => 1]);
        $this->problem = $this->lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'title_ar' => 'ضاعف', 'description' => 'Double it', 'description_ar' => 'ضاعفه', 'difficulty' => 'easy', 'points' => 10]);
    }

    public function test_admin_responses_include_both_languages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson("/api/admin/lessons/{$this->lesson->id}")
            ->assertJsonPath('title_ar', 'الإحماء')
            ->assertJsonPath('body', 'English body')
            ->assertJsonPath('body_ar', 'نص عربي');
        // An admin's language never changes what the admin API returns.
        $this->actingAs($admin)->getJson("/api/admin/problems/{$this->problem->id}", ['Accept-Language' => 'ar'])
            ->assertJsonPath('title', 'Double')
            ->assertJsonPath('title_ar', 'ضاعف')
            ->assertJsonPath('description_ar', 'ضاعفه');
        $this->actingAs($admin)->getJson('/api/admin/modules')->assertJsonPath('0.title_ar', 'الأساسيات');
    }

    public function test_admin_saves_arabic_fields_and_stores_empty_ones_as_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lesson = ['module_id' => $this->module->id, 'title' => 'Warm-up', 'slug' => 'warm-up', 'body' => 'English body', 'sort_order' => 1];

        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['title_ar' => 'عنوان', 'body_ar' => 'نص جديد'])
            ->assertOk()->assertJsonPath('title_ar', 'عنوان')->assertJsonPath('body_ar', 'نص جديد');
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['title_ar' => '', 'body_ar' => '  '])
            ->assertOk()->assertJsonPath('title_ar', null)->assertJsonPath('body_ar', null);
        $this->assertNull($this->lesson->fresh()->body_ar);

        $created = $this->actingAs($admin)->postJson('/api/admin/problems', ['title' => 'New', 'title_ar' => 'جديد', 'slug' => 'new', 'description' => 'Text', 'description_ar' => '', 'difficulty' => 'easy'])
            ->assertCreated()->assertJsonPath('title_ar', 'جديد');
        $this->assertNull(Problem::find($created->json('id'))->description_ar);

        $this->actingAs($admin)->putJson("/api/admin/modules/{$this->module->id}", ['title' => 'Basics', 'title_ar' => 'البداية', 'slug' => 'basics', 'sort_order' => 1])
            ->assertOk()->assertJsonPath('title_ar', 'البداية');
    }

    public function test_admin_validates_arabic_fields_like_english_ones_including_bytes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lesson = ['module_id' => $this->module->id, 'title' => 'Warm-up', 'slug' => 'warm-up', 'sort_order' => 1];
        $problem = ['lesson_id' => $this->lesson->id, 'title' => 'Double', 'slug' => 'warm-up', 'description' => 'Double it', 'difficulty' => 'easy'];

        // 255 Arabic letters fit a VARCHAR(255) (characters, not bytes); 256 do not.
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['title_ar' => str_repeat('ب', 255)])->assertOk();
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['title_ar' => str_repeat('ب', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors('title_ar');
        // 20,000 Arabic letters are 40,000 bytes: allowed. 16,400 four-byte characters are
        // 65,600 bytes: over the TEXT limit, though under the character limit.
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['body_ar' => str_repeat('ب', 20000)])->assertOk();
        $this->assertSame(40000, strlen($this->lesson->fresh()->body_ar));
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['body_ar' => str_repeat('ب', 20001)])
            ->assertUnprocessable()->assertJsonValidationErrors('body_ar');
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['body_ar' => str_repeat('😀', 16400)])
            ->assertUnprocessable()->assertJsonValidationErrors(['body_ar' => 'The Arabic body may not be larger than 65,535 bytes.']);
        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", $lesson + ['title_ar' => ['not', 'a', 'string']])
            ->assertUnprocessable()->assertJsonValidationErrors('title_ar');

        $this->actingAs($admin)->putJson("/api/admin/problems/{$this->problem->id}", $problem + ['description_ar' => str_repeat('ب', 5000)])->assertOk();
        $this->actingAs($admin)->putJson("/api/admin/problems/{$this->problem->id}", $problem + ['description_ar' => str_repeat('ب', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors('description_ar');
        $this->actingAs($admin)->putJson("/api/admin/problems/{$this->problem->id}", $problem + ['title_ar' => str_repeat('ب', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors('title_ar');
        $this->actingAs($admin)->putJson("/api/admin/modules/{$this->module->id}", ['title' => 'Basics', 'title_ar' => str_repeat('ب', 256), 'slug' => 'basics', 'sort_order' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('title_ar');
    }

    public function test_the_audit_log_records_arabic_changes_like_english_ones(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $longTitle = str_repeat('ع', 250);

        $this->actingAs($admin)->putJson("/api/admin/lessons/{$this->lesson->id}", [
            'module_id' => $this->module->id, 'title' => 'Warm-up', 'slug' => 'warm-up', 'body' => 'English body', 'sort_order' => 1,
            'title_ar' => $longTitle, 'body_ar' => 'نص سري جديد',
        ])->assertOk();
        $this->actingAs($admin)->putJson("/api/admin/problems/{$this->problem->id}", [
            'lesson_id' => $this->lesson->id, 'title' => 'Double', 'slug' => 'warm-up', 'description' => 'Double it', 'difficulty' => 'easy',
            'description_ar' => 'وصف جديد',
        ])->assertOk();

        [$lessonRow, $problemRow] = AdminAuditLog::orderBy('id')->get()->all();
        $this->assertSame('lesson.update', $lessonRow->action);
        $this->assertSame(['old' => 'الإحماء', 'new' => mb_substr($longTitle, 0, 200)], $lessonRow->details['changes']['title_ar']);
        $this->assertSame('changed', $lessonRow->details['changes']['body_ar']);
        $this->assertStringNotContainsString('نص سري جديد', json_encode($lessonRow->details, JSON_UNESCAPED_UNICODE));
        $this->assertSame(['old' => 'ضاعفه', 'new' => 'وصف جديد'], $problemRow->details['changes']['description_ar']);
    }
}
