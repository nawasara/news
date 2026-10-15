<?php

namespace Nawasara\News\Tests;

use Nawasara\News\Livewire\Source\Section\Table as SourceTable;
use Nawasara\News\Services\ArticleHtmlSanitizer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Audit keamanan 15 Oktober 2026 (S9): konten artikel WordPress dulu
 * dirender dan dikirim ke aplikasi mobile mentah. Uji ini mengunci bahwa
 * skrip dibuang dan format artikel biasa tetap utuh.
 */
class ArticleHtmlSanitizerTest extends TestCase
{
    public function test_skrip_dan_handler_dibuang(): void
    {
        $html = '<p onclick="x()">Hai</p><script>alert(1)</script>'
            .'<img src="https://a.test/i.jpg" onerror="alert(1)">'
            .'<iframe src="https://evil.test"></iframe><style>p{}</style>'
            .'<svg onload="alert(1)"></svg>';

        $out = ArticleHtmlSanitizer::clean($html);

        $this->assertStringNotContainsStringIgnoringCase('<script', $out);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $out);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $out);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $out);
        $this->assertStringNotContainsStringIgnoringCase('<style', $out);
        $this->assertStringNotContainsStringIgnoringCase('<svg', $out);
        $this->assertStringContainsString('<p>Hai</p>', $out);
    }

    public function test_hanya_skema_http_https(): void
    {
        $out = ArticleHtmlSanitizer::clean(
            '<a href="javascript:alert(1)">a</a><a href="data:text/html,x">b</a>'
            .'<a href="https://ponorogo.go.id/x?a=1&b=2">c</a><a href="http://x.test">d</a>'
        );

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $out);
        $this->assertStringNotContainsStringIgnoringCase('data:', $out);
        $this->assertStringContainsString('href="https://ponorogo.go.id/x?a=1&amp;b=2"', $out);
        $this->assertStringContainsString('href="http://x.test"', $out);
    }

    public function test_format_artikel_tetap_utuh(): void
    {
        $html = '<h2>Judul</h2><p><strong>Tebal</strong> <em>miring</em></p>'
            .'<ul><li>satu</li></ul><ol><li>dua</li></ol>'
            .'<blockquote>kutipan</blockquote>'
            .'<table><tbody><tr><td colspan="2">sel</td></tr></tbody></table>'
            .'<figure><img src="https://a.test/b.jpg" alt="b"><figcaption>Keterangan</figcaption></figure>';

        $out = ArticleHtmlSanitizer::clean($html);

        foreach (['<h2>Judul</h2>', '<strong>Tebal</strong>', '<em>miring</em>', '<ul><li>satu</li></ul>',
            '<ol><li>dua</li></ol>', '<blockquote>kutipan</blockquote>', '<td colspan="2">sel</td>',
            '<img src="https://a.test/b.jpg" alt="b">', '<figcaption>Keterangan</figcaption>'] as $potongan) {
            $this->assertStringContainsString($potongan, $out);
        }
    }

    public function test_idempoten_dan_null_aman(): void
    {
        $sekali = ArticleHtmlSanitizer::clean('<p>a <a href="https://x.test">b</a></p>');

        $this->assertSame($sekali, ArticleHtmlSanitizer::clean($sekali));
        $this->assertNull(ArticleHtmlSanitizer::clean(null));
        $this->assertSame('', ArticleHtmlSanitizer::clean(''));
    }

    /** S6: hapus sumber membuang artikelnya (cascade), wajib bersudo. */
    public function test_hapus_sumber_bersudo(): void
    {
        if (! class_exists(SourceTable::class)) {
            $this->markTestSkipped('Komponen tidak ter-autoload.');
        }

        $attrs = array_map(
            fn ($a) => $a->getName(),
            (new ReflectionMethod(SourceTable::class, 'delete'))->getAttributes(),
        );

        $this->assertContains(\Nawasara\AuthPrimitives\Attributes\RequiresSudo::class, $attrs);
    }
}
