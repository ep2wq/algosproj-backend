<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use App\Models\SecurityEvent;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

// POST /api/bug-reports (README "Bug reports"): logged out or logged in, multipart. Nothing from the
// client is trusted: every value is checked, cut to its limit and cleaned of secrets here, and the
// optional parts are only stored when the user's included flags say so. No IP address is stored.
class BugReportController extends Controller
{
    private const FIELDS = ['description', 'contact_email', 'page_path', 'page_title', 'app_version', 'language', 'theme',
        'viewport', 'timezone', 'error_context', 'failed_requests', 'console_errors', 'problem_context', 'included',
        'screenshot', SecurityEvent::HONEYPOT_FIELD];

    private const MAX_ITEMS = 10;

    private const MAX_MESSAGE = 300;

    private const MAX_CODE = 20000;

    // PNG, WebP and JPEG only, by the file's real content (getimagesize and finfo), never its name
    // or the mime type the client sent.
    private const IMAGE_TYPES = [
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
    ];

    private const MAX_SIDE = 4000;

    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function store(Request $request)
    {
        // A bot filled the hidden field: it gets what a real report gets, and nothing is saved.
        if (SecurityEvent::honeypotFilled($request)) {
            return response()->json(['id' => (string) Str::uuid7()], 201);
        }

        $unknown = array_diff(array_keys($request->all()), self::FIELDS);
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys(array_slice(array_values($unknown), 0, 5), __('messages.bug_report_unknown_field')));
        }

        $data = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'page_path' => ['required', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', Rule::in(['en', 'ar'])],
            'theme' => ['nullable', Rule::in(['dark', 'light'])],
            'viewport' => ['nullable', 'string', 'regex:/^\d{1,5}x\d{1,5}$/'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone:all'],
            // JSON text (multipart has no nested values); its shape is checked below.
            'error_context' => ['nullable', 'string', 'max:4000'],
            'failed_requests' => ['nullable', 'string', 'max:20000'],
            'console_errors' => ['nullable', 'string', 'max:10000'],
            'problem_context' => ['nullable', 'string', 'max:60000'],
            'included' => ['required', 'string', 'max:500'],
            'screenshot' => ['nullable', 'file', 'max:1536'],
        ]);

        $flags = $this->json($data, 'included') ?? [];
        $user = Auth::guard('web')->user();
        $included = [
            'account' => $user !== null && ($flags['account'] ?? false) === true,
            'diagnostics' => ($flags['diagnostics'] ?? false) === true,
            'screenshot' => ($flags['screenshot'] ?? false) === true && $request->hasFile('screenshot'),
            'problem' => ($flags['problem'] ?? false) === true,
        ];

        $errorContext = $this->json($data, 'error_context');
        $failed = $included['diagnostics'] ? $this->json($data, 'failed_requests') : null;
        $console = $included['diagnostics'] ? $this->json($data, 'console_errors') : null;
        $problem = $included['problem'] ? $this->json($data, 'problem_context') : null;

        $fields = [
            // Logged in: the account's own email (only with the account part); logged out: what the visitor typed.
            'user_id' => $included['account'] ? $user->id : null,
            'contact_email' => $user !== null
                ? ($included['account'] ? $user->email : null)
                : (isset($data['contact_email']) ? mb_substr($data['contact_email'], 0, 255) : null),
            'description' => self::clean($data['description'], 2000),
            'page_path' => self::path($data['page_path']),
            'page_title' => self::short($data['page_title'] ?? null, 255),
            'app_version' => self::short($data['app_version'] ?? null, 50),
            'language' => $data['language'] ?? null,
            'theme' => $data['theme'] ?? null,
            'user_agent' => $request->userAgent() === null ? null : SecurityEvent::text($request->userAgent(), 255),
            'viewport' => $data['viewport'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'error_context' => $errorContext === null ? null : self::errorContext($errorContext),
            'failed_requests' => $failed === null ? null : self::items($failed, [self::class, 'failedRequest']),
            'console_errors' => $console === null ? null : self::items($console, [self::class, 'consoleError']),
            'problem_context' => $problem === null ? null : self::problem($problem),
            'included' => $included,
        ];

        if ($included['screenshot']) {
            $fields['screenshot_path'] = $this->storeScreenshot($request->file('screenshot'));
        }

        try {
            $report = BugReport::create($fields);
        } catch (Throwable $e) {
            if (isset($fields['screenshot_path'])) {
                Storage::disk(BugReport::DISK)->delete($fields['screenshot_path']);
            }
            throw $e;
        }

        return response()->json(['id' => $report->id], 201);
    }

    // A JSON object or list sent as text, or a validation error.
    private function json(array $data, string $field): ?array
    {
        if (! isset($data[$field])) {
            return null;
        }
        $value = json_decode($data[$field], true, 8);
        if (! is_array($value)) {
            throw ValidationException::withMessages([$field => __('messages.bug_report_invalid_field')]);
        }

        return $value;
    }

    private function storeScreenshot(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $info = $path === false ? false : @getimagesize($path);
        $type = $info === false ? null : (self::IMAGE_TYPES[$info[2]] ?? null);
        $mime = $path === false ? null : (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($type === null || $mime !== $type[1] || $info[0] < 1 || $info[1] < 1 || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
            throw ValidationException::withMessages(['screenshot' => __('messages.screenshot_invalid')]);
        }

        // A random name with the real extension, on the private disk.
        return Storage::disk(BugReport::DISK)->putFileAs('bug-reports', $file, Str::uuid7().'.'.$type[0]);
    }

    // At most 10 objects, each reduced to its known keys; anything else in the list is dropped.
    private static function items(array $list, callable $item): array
    {
        $items = array_values(array_filter(array_is_list($list) ? $list : [], 'is_array'));

        return array_map($item, array_slice($items, -self::MAX_ITEMS));
    }

    // Only known keys are kept, so a key like password, token, cookie or authorization never is.
    private static function errorContext(array $value): array
    {
        return array_filter([
            'status' => self::status($value['status'] ?? null),
            'path' => is_string($value['path'] ?? null) ? self::path($value['path']) : null,
            'code' => self::short($value['code'] ?? null, 100),
            'message' => self::message($value['message'] ?? null),
        ], fn ($v) => $v !== null);
    }

    private static function failedRequest(array $value): array
    {
        $method = is_string($value['method'] ?? null) ? strtoupper($value['method']) : null;

        return array_filter([
            'method' => in_array($method, self::METHODS, true) ? $method : null,
            'path' => is_string($value['path'] ?? null) ? self::path($value['path']) : null,
            'status' => self::status($value['status'] ?? null),
            'time' => self::short($value['time'] ?? null, 40),
            'code' => self::short($value['code'] ?? null, 100),
            'message' => self::message($value['message'] ?? null),
        ], fn ($v) => $v !== null);
    }

    private static function consoleError(array $value): array
    {
        return array_filter([
            'message' => self::message($value['message'] ?? null),
            'time' => self::short($value['time'] ?? null, 40),
        ], fn ($v) => $v !== null);
    }

    private static function problem(array $value): array
    {
        $result = is_array($value['result'] ?? null) ? $value['result'] : [];
        $count = fn ($v) => is_int($v) && $v >= 0 && $v <= 1000 ? $v : null;

        return array_filter([
            'slug' => is_string($value['slug'] ?? null) && preg_match('/^[a-z0-9-]{1,255}$/', $value['slug']) ? $value['slug'] : null,
            'language' => self::short($value['language'] ?? null, 20),
            // The user's own code: cut to 20,000 characters; only Bearer values and JWTs are redacted,
            // so ordinary code like key=value stays as written.
            'code' => is_string($value['code'] ?? null) ? self::redact(mb_substr(mb_scrub($value['code'], 'UTF-8'), 0, self::MAX_CODE), true) : null,
            'result' => array_filter([
                'status' => self::short($result['status'] ?? null, 30),
                'passed_count' => $count($result['passed_count'] ?? null),
                'total_count' => $count($result['total_count'] ?? null),
                'message' => self::message($result['message'] ?? null),
            ], fn ($v) => $v !== null) ?: null,
        ], fn ($v) => $v !== null);
    }

    private static function status(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 && $value <= 599 ? $value : null;
    }

    private static function message(mixed $value): ?string
    {
        return self::short($value, self::MAX_MESSAGE);
    }

    private static function short(mixed $value, int $max): ?string
    {
        return is_string($value) && $value !== '' ? SecurityEvent::text(self::redact($value), $max) : null;
    }

    // Free text: valid UTF-8, line breaks and tabs kept, other control characters removed, secrets redacted.
    private static function clean(string $value, int $max): string
    {
        return mb_substr(self::redact((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', mb_scrub($value, 'UTF-8'))), 0, $max);
    }

    // The path only: no scheme, host, query string or fragment.
    private static function path(string $value): string
    {
        $value = (string) preg_replace('/[?#].*$/s', '', $value);
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        return SecurityEvent::text('/'.ltrim(self::redact($value), '/'), 255);
    }

    // Bearer values, JWTs, long token-like strings and the values of secret-named parameters.
    public static function redact(string $text, bool $code = false): string
    {
        $text = (string) preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', $text);
        $text = (string) preg_replace('/\beyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]*/', '[redacted]', $text);
        if ($code) {
            return $text;
        }
        $value = '("[^"]*"|\'[^\']*\'|[^\s&"\',;]+)';
        $text = (string) preg_replace('/\b(password|passwd|pwd|token|access_token|refresh_token|secret|api_?key|key|signature|session|cookie|authorization|csrf|xsrf)(\s*=\s*)'.$value.'/i', '$1$2[redacted]', $text);
        $text = (string) preg_replace('/\b(password|passwd|token|secret|api_?key|cookie|authorization|csrf|xsrf)(\s*:\s*)'.$value.'/i', '$1$2[redacted]', $text);

        // 32 or more token characters in a row; a UUID (the ids in API paths) is kept.
        return (string) preg_replace_callback('/(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{32,}(?![A-Za-z0-9_-])/', fn ($m) => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $m[0]) ? $m[0] : '[redacted]', $text);
    }
}
