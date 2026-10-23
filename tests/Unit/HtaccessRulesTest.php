<?php

namespace Tests\Unit;

use App\Modules\Core\Services\HtaccessRules;
use PHPUnit\Framework\TestCase;

class HtaccessRulesTest extends TestCase
{
    private const CPANEL = '# php -- BEGIN cPanel-generated handler, do not edit
# Set the "ea-php85" package as the default "PHP" programming language.
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php85___lsphp .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit';

    public function test_replaces_only_the_marked_block(): void
    {
        $existing = self::CPANEL."\n\n# BEGIN sgcore\n# old rules\nRewriteRule ^ old [L]\n# END sgcore\n\n# my redirect\nRedirect 301 /old /new\n";
        $incoming = HtaccessRules::wrap("# new rules\nRewriteRule ^ index.php [L]");

        $merged = HtaccessRules::merge($existing, $incoming);

        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php85___lsphp', $merged);
        $this->assertStringContainsString('# my redirect'."\n".'Redirect 301 /old /new', $merged);
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $merged);
        $this->assertStringNotContainsString('RewriteRule ^ old [L]', $merged);
        $this->assertSame(1, substr_count($merged, HtaccessRules::BEGIN));
        $this->assertSame(1, substr_count($merged, '# php -- BEGIN cPanel-generated handler'));
    }

    public function test_keeps_custom_directives_after_the_block(): void
    {
        $existing = "# BEGIN sgcore\nold\n# END sgcore\nphp_value upload_max_filesize 64M\n";
        $merged = HtaccessRules::merge($existing, HtaccessRules::wrap('new'));

        $this->assertStringContainsString("new\n# END sgcore", $merged);
        $this->assertStringContainsString('php_value upload_max_filesize 64M', $merged);
        $this->assertStringNotContainsString('old', $merged);
    }

    public function test_migrates_a_pre_marker_file_and_restores_the_handler(): void
    {
        $existing = "# sgcore shared-hosting rules (Apache / LiteSpeed).\n"
            ."Options -Indexes -MultiViews\n"
            ."RewriteRule ^ index.php [L]\n\n"
            .self::CPANEL."\n";

        $merged = HtaccessRules::merge($existing, HtaccessRules::wrap('fresh rules'));

        $this->assertStringNotContainsString('sgcore shared-hosting rules', $merged);
        $this->assertStringNotContainsString('Options -Indexes -MultiViews', $merged);
        $this->assertStringContainsString('fresh rules', $merged);
        $this->assertSame(1, substr_count($merged, '# php -- BEGIN cPanel-generated handler'));
        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php85___lsphp', $merged);
    }

    public function test_keeps_user_rules_that_precede_the_legacy_block(): void
    {
        $existing = self::CPANEL."\n\n# sgcore package rules\nRewriteRule ^ index.php [L]\n";

        $merged = HtaccessRules::merge($existing, HtaccessRules::wrap('fresh'));

        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php85___lsphp', $merged);
        $this->assertStringContainsString('fresh', $merged);
        $this->assertStringNotContainsString('# sgcore package rules', $merged);
    }

    public function test_appends_the_block_to_a_foreign_file_without_losing_anything(): void
    {
        $existing = "AddHandler application/x-httpd-alt-php85___lsphp .php\nRewriteEngine On\n";

        $merged = HtaccessRules::merge($existing, HtaccessRules::wrap('fresh'));

        $this->assertStringContainsString('AddHandler application/x-httpd-alt-php85___lsphp .php', $merged);
        $this->assertStringContainsString('RewriteEngine On', $merged);
        $this->assertStringContainsString('fresh', $merged);
        $this->assertSame(1, substr_count($merged, 'AddHandler'));
    }

    public function test_merging_twice_is_idempotent(): void
    {
        $incoming = HtaccessRules::wrap('rules');
        $once = HtaccessRules::merge(self::CPANEL."\n", $incoming);
        $twice = HtaccessRules::merge($once, $incoming);

        $this->assertSame($once, $twice);
    }

    public function test_wraps_rules_that_arrive_without_markers(): void
    {
        $merged = HtaccessRules::merge('', "# legacy\nRewriteRule ^ index.php [L]");

        $this->assertStringStartsWith(HtaccessRules::BEGIN, $merged);
        $this->assertStringEndsWith(HtaccessRules::END."\n", $merged);
    }

    public function test_detects_a_bare_addhandler_line(): void
    {
        $handler = HtaccessRules::handler("AddHandler application/x-httpd-alt-php84___lsphp .php\n# other\n");

        $this->assertSame('AddHandler application/x-httpd-alt-php84___lsphp .php', $handler);
    }
}
