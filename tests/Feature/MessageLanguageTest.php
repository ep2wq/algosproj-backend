<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// API messages follow Accept-Language like the course text: Arabic when the first language is
// ar, otherwise exactly the English the API returned before (lang/, README "Content languages").
class MessageLanguageTest extends TestCase
{
    use RefreshDatabase;

    private Problem $problem;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sandbox.url' => 'http://sandbox.test', 'services.sandbox.secret' => 'test-secret']);
        $module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'sort_order' => 1]);
        $this->problem = $lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'description' => 'Double it', 'difficulty' => 'easy', 'points' => 10]);
        $this->user = User::factory()->create(['email' => 'user@example.com', 'password' => 'password1']);
    }

    public static function englishHeaders(): array
    {
        return ['no header' => [[]], 'en' => [['Accept-Language' => 'en']], 'en-US' => [['Accept-Language' => 'en-US,ar;q=0.8']]];
    }

    #[DataProvider('englishHeaders')]
    public function test_messages_stay_english_without_arabic(array $headers): void
    {
        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'wrong'], $headers)
            ->assertStatus(401)->assertExactJson(['message' => 'Wrong email or password.']);
        $this->postJson('/api/register', [], $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'The name field is required. (and 2 more errors)')
            ->assertJsonPath('errors.password.0', 'The password field is required.');
        $this->getJson('/api/me', $headers)->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
        $this->actingAs($this->user)->postJson("/api/problems/{$this->problem->id}/submit", ['language' => 'cobol', 'code' => str_repeat('a', 65537)], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.language.0', 'The selected language is invalid.')
            ->assertJsonPath('errors.code.0', 'The code may not be larger than 64 KB.');
    }

    public function test_login_failure_is_arabic(): void
    {
        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'wrong'], ['Accept-Language' => 'ar'])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'])
            ->assertHeader('Vary', 'Accept-Language');
    }

    public function test_banned_login_is_arabic_and_keeps_the_reason_as_typed(): void
    {
        $this->user->forceFill(['banned_at' => now(), 'ban_reason' => 'Spam links'])->save();

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'password1'], ['Accept-Language' => 'ar-SA'])
            ->assertStatus(403)
            ->assertExactJson(['message' => 'هذا الحساب محظور.', 'reason' => 'Spam links']);
    }

    public function test_validation_errors_are_arabic(): void
    {
        $this->postJson('/api/register', ['name' => 'Sara', 'email' => 'user@example.com', 'password' => 'short'], ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'القيمة المدخلة في البريد الإلكتروني مستخدمة من قبل. (وخطأ آخر)')
            ->assertJsonPath('errors.email.0', 'القيمة المدخلة في البريد الإلكتروني مستخدمة من قبل.')
            ->assertJsonPath('errors.password.0', 'يجب ألا يقل طول كلمة المرور عن 8 من الأحرف.');
    }

    public function test_submit_validation_and_errors_are_arabic(): void
    {
        $arabic = ['Accept-Language' => 'ar'];
        $this->actingAs($this->user)->postJson("/api/problems/{$this->problem->id}/submit", ['language' => 'cobol', 'code' => str_repeat('a', 65537)], $arabic)
            ->assertStatus(422)
            ->assertJsonPath('errors.language.0', 'قيمة لغة البرمجة المحددة غير صالحة.')
            ->assertJsonPath('errors.code.0', 'يجب ألا يزيد حجم الكود على 64 KB.');

        Http::fake(fn () => throw new ConnectionException('down'));
        $this->actingAs($this->user)->postJson("/api/problems/{$this->problem->id}/submit", ['language' => 'python', 'code' => 'print(1)'], $arabic)
            ->assertStatus(503)
            ->assertExactJson(['message' => 'منفّذ الكود غير متاح الآن، حاول بعد قليل']);
    }

    public function test_framework_errors_are_arabic(): void
    {
        $arabic = ['Accept-Language' => 'ar'];
        $this->getJson('/api/me', $arabic)->assertStatus(401)->assertExactJson(['message' => 'يجب تسجيل الدخول أولًا.']);
        $this->getJson('/api/lessons/nope', $arabic)->assertStatus(404)->assertExactJson(['message' => 'لم يُعثر على المطلوب.']);
        $this->getJson('/api/nope', $arabic)->assertStatus(404)->assertHeader('Vary', 'Accept-Language');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x'], $arabic);
        }
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x'], $arabic)
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertExactJson(['message' => 'محاولات كثيرة جدًا، انتظر قليلًا ثم حاول مرة أخرى.']);
    }

    public function test_admin_errors_stay_english(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        // The admin area sends en; even an Arabic header gets the English admin message.
        foreach (['en', 'ar'] as $language) {
            $this->actingAs($admin)->postJson("/api/admin/users/{$admin->id}/ban", ['reason' => 'x'], ['Accept-Language' => $language])
                ->assertStatus(403)
                ->assertJsonPath('message', "You can't do this to your own account.");
        }
    }

    public function test_content_responses_vary_by_language(): void
    {
        foreach (['/api/lessons/warm-up', '/api/challenges/warm-up', '/api/modules'] as $url) {
            $this->getJson($url, ['Accept-Language' => 'ar'])->assertOk()->assertHeader('Vary', 'Accept-Language');
        }
    }
}
