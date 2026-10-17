<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Every slug with Arabic in database/seeders/data/ar keeps the shape of the English: the same
// code blocks byte for byte, as many headings and list items, no empty field, and no English
// outside `inline code` and ~term~ markers (see the README, "Content languages").
class ArabicSeedTextTest extends TestCase
{
    // A marker starts and ends with a non-space character, as in the frontend (splitTerms).
    private const MARKER = '/~[^~\s](?:[^~\n]*[^~\s])?~/';

    public static function arabicTexts(): array
    {
        $data = __DIR__.'/../../database/seeders/data';
        $bodies = require "$data/lesson_bodies.php";
        $arLessons = require "$data/ar/lessons.php";
        $arProblems = require "$data/ar/problems.php";
        $cases = [];
        foreach (require "$data/lessons.php" as $module) {
            foreach ($module['lessons'] as $lesson) {
                $slug = $lesson['slug'];
                if (isset($arLessons[$slug])) {
                    $cases["lesson $slug"] = [$bodies[$slug], $arLessons[$slug]['title'] ?? '', $arLessons[$slug]['body'] ?? ''];
                }
                if (isset($arProblems[$slug])) {
                    $cases["problem $slug"] = [$lesson['problem']['description'], $arProblems[$slug]['title'] ?? '', $arProblems[$slug]['description'] ?? ''];
                }
            }
        }

        return $cases;
    }

    // Code blocks (runs of 4-space lines, blank lines inside kept), headings and list items, in
    // the order the frontend parser (src/lessonText.ts) reads them.
    private function shape(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $shape = ['code' => [], 'headings' => 0, 'items' => 0];
        for ($i = 0; $i < count($lines); $i++) {
            if (str_starts_with($lines[$i], '    ')) {
                $code = [];
                for (; $i < count($lines); $i++) {
                    $next = $i;
                    while ($next < count($lines) && trim($lines[$next]) === '') {
                        $next++;
                    }
                    if ($next === count($lines) || ! str_starts_with($lines[$next], '    ')) {
                        break;
                    }
                    array_push($code, ...array_slice($lines, $i, $next - $i + 1));
                    $i = $next;
                }
                $shape['code'][] = implode("\n", $code);
                $i--;
            } elseif (str_starts_with($lines[$i], '## ')) {
                $shape['headings']++;
            } elseif (str_starts_with($lines[$i], '- ')) {
                $shape['items']++;
            }
        }

        return $shape;
    }

    #[DataProvider('arabicTexts')]
    public function test_the_arabic_keeps_the_shape_of_the_english(string $english, string $title, string $arabic): void
    {
        $this->assertNotSame('', trim($title), 'empty Arabic title');
        $this->assertNotSame('', trim($arabic), 'empty Arabic text');
        $this->assertSame($this->shape($english), $this->shape($arabic));

        $prose = implode("\n", array_filter(preg_split('/\r?\n/', $arabic), fn ($line) => ! str_starts_with($line, '    ')));
        $prose = preg_replace('/`[^`\n]+`/', ' ', $prose);
        $outsideMarkers = preg_replace(self::MARKER, ' ', $prose);
        $this->assertStringNotContainsString('~', $outsideMarkers, 'unbalanced or empty ~ marker');
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $outsideMarkers, 'Latin letters outside inline code and markers');
    }

    public function test_every_arabic_module_title_is_filled(): void
    {
        foreach (require database_path('seeders/data/ar/modules.php') as $slug => $module) {
            $this->assertNotSame('', trim($module['title'] ?? ''), $slug);
        }
    }
}
