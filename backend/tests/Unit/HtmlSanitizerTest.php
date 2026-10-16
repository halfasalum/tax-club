<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function dangerousMarkup(): array
    {
        return [
            'script element' => ['<p>a</p><script>alert(1)</script>', '<p>a</p>'],
            'event handler' => ['<p onclick="alert(1)">a</p>', '<p>a</p>'],
            'javascript link' => ['<a href="javascript:alert(1)">a</a>', '<a>a</a>'],
            'iframe' => ['<iframe src="https://evil.example"></iframe><p>a</p>', '<p>a</p>'],
            'style attribute' => ['<p style="background:url(x)">a</p>', '<p>a</p>'],
            'unknown element keeps text' => ['<section><p>a</p></section>', '<p>a</p>'],
            'http image' => ['<img src="http://example.com/x.png" alt="x">', '<img alt="x" loading="lazy">'],
        ];
    }

    #[DataProvider('dangerousMarkup')]
    public function test_it_removes_dangerous_markup(string $input, string $expected): void
    {
        $this->assertSame($expected, HtmlSanitizer::clean($input));
    }

    public function test_it_keeps_editor_formatting_and_safe_links(): void
    {
        $html = '<h2>VAT</h2><p><strong>18%</strong> <em>on</em> <a href="https://www.tra.go.tz">TRA</a></p><ul><li>One</li></ul>';

        $this->assertSame(
            '<h2>VAT</h2><p><strong>18%</strong> <em>on</em> <a href="https://www.tra.go.tz" rel="nofollow noopener">TRA</a></p><ul><li>One</li></ul>',
            HtmlSanitizer::clean($html),
        );
    }

    public function test_it_preserves_kiswahili_text(): void
    {
        $this->assertSame('<p>Dai risiti — kodi yetu</p>', HtmlSanitizer::clean('<p>Dai risiti — kodi yetu</p>'));
    }
}
