<?php

namespace Nawasara\News\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Membersihkan HTML artikel WordPress sebelum disimpan, ditampilkan, atau
 * dikirim ke aplikasi mobile.
 *
 * ⚠️ Audit keamanan 15 Oktober 2026 (S9): `content` dulu disimpan dan
 * dirender mentah (`{!! $artikel->content !!}`) serta diteruskan apa adanya
 * lewat ArticleDetailResource. Asumsi lamanya "penyunting WordPress milik
 * organisasi ini sendiri" tidak menahan apa pun begitu satu situs WP OPD
 * disusupi: skrip di artikel berjalan di sesi admin panel, dan di WebView
 * aplikasi warga.
 *
 * Daftar-izin, bukan daftar-larang: hanya tag format artikel biasa yang
 * lolos, dan tautan/gambar hanya ber-skema http/https. Dibersihkan di DUA
 * tempat: saat sinkron (data baru tersimpan bersih) dan saat dibaca (baris
 * lama yang tersimpan sebelum perbaikan ini, dan yang sudah tidak lagi
 * termasuk N artikel terbaru sehingga tidak disinkron ulang). HTMLPurifier
 * idempoten, jadi membersihkan dua kali tidak mengubah hasil.
 */
class ArticleHtmlSanitizer
{
    private const ALLOWED = 'p,br,hr,'
        .'a[href|title],'
        .'img[src|alt|title|width|height],'
        .'ul,ol,li,'
        .'h1,h2,h3,h4,h5,h6,'
        .'table,thead,tbody,tfoot,tr,th[colspan|rowspan],td[colspan|rowspan],caption,'
        .'strong,b,em,i,u,s,sub,sup,'
        .'blockquote,figure,figcaption,'
        .'pre,code,span';

    /** Satu instance per proses; membangun definisinya yang mahal. */
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        // Tidak menulis cache definisi ke direktori vendor: di kontainer prod
        // direktori itu tidak dapat ditulis. Instance di atas sudah memo.
        $config->set('Cache.DefinitionImpl', null);
        $config->set('Core.Encoding', 'UTF-8');

        // figure/figcaption adalah tag HTML5 yang tidak dikenal HTMLPurifier
        // bawaan; didaftarkan supaya gambar ber-keterangan dari WordPress
        // (blok Gutenberg) tidak kehilangan keterangannya.
        $def = $config->getHTMLDefinition(true);
        $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
        $def->addElement('figcaption', 'Inline', 'Flow', 'Common');

        return self::$purifier = new HTMLPurifier($config);
    }
}
