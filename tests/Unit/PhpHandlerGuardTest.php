<?php

namespace Tests\Unit;

use App\Modules\Core\Services\HtaccessRules;
use App\Modules\Core\Services\PhpHandlerGuard;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PhpHandlerGuardTest extends TestCase
{
    private string $root;

    private const CPANEL = '# php -- BEGIN cPanel-generated handler, do not edit'."\n"
        .'AddHandler application/x-httpd-ea-php85___lsphp .php'."\n"
        .'# php -- END cPanel-generated handler, do not edit';

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/sgcore-guard-'.bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);

        File::delete($this->store());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::delete($this->store());

        parent::tearDown();
    }

    public function test_restores_the_handler_after_a_manual_extraction_overwrites_htaccess(): void
    {
        file_put_contents($this->root.'/.htaccess', self::CPANEL."\n\n# user rule\nRedirect 301 /a /b\n");

        $guard = new PhpHandlerGuard;
        $guard->remember($this->root);

        // File Manager extracts the package over the site: markers only, no host block.
        file_put_contents($this->root.'/.htaccess', HtaccessRules::wrap('RewriteRule ^ index.php [L]'));

        $this->assertSame(['.htaccess'], $guard->missing($this->root));
        $this->assertSame(['.htaccess'], $guard->restore($this->root));

        $htaccess = (string) file_get_contents($this->root.'/.htaccess');

        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php85___lsphp .php', $htaccess);
        $this->assertStringContainsString(HtaccessRules::BEGIN, $htaccess);
        $this->assertSame(1, substr_count($htaccess, 'AddHandler'));
        $this->assertSame([], $guard->missing($this->root));
    }

    public function test_remember_does_not_erase_a_known_handler_when_the_file_is_already_broken(): void
    {
        file_put_contents($this->root.'/.htaccess', self::CPANEL."\n");

        $guard = new PhpHandlerGuard;
        $guard->remember($this->root);

        file_put_contents($this->root.'/.htaccess', HtaccessRules::wrap('rules'));
        $guard->remember($this->root);

        $this->assertSame(['.htaccess'], $guard->restore($this->root));
        $this->assertStringContainsString('cPanel-generated handler', (string) file_get_contents($this->root.'/.htaccess'));
    }

    public function test_does_nothing_when_the_file_still_has_the_handler(): void
    {
        file_put_contents($this->root.'/.htaccess', self::CPANEL."\n");

        $guard = new PhpHandlerGuard;
        $guard->remember($this->root);

        $this->assertSame([], $guard->restore($this->root));
        $this->assertSame([], $guard->missing($this->root));
    }

    public function test_ignores_files_that_do_not_exist(): void
    {
        mkdir($this->root.'/public', 0777, true);
        file_put_contents($this->root.'/public/.htaccess', "AddHandler application/x-httpd-alt-php85___lsphp .php\n");

        $guard = new PhpHandlerGuard;
        $guard->remember($this->root);

        $this->assertSame([], $guard->restore($this->root));
    }

    private function store(): string
    {
        return storage_path('framework/php-handlers.json');
    }
}
