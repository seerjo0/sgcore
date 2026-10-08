<?php

namespace Tests\Feature\Themes;

use App\Modules\Themes\Services\ThemeDiscovery;
use App\Modules\Themes\Services\ThemeImporter;
use App\Modules\Themes\Services\ThemeImportException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ThemeImportTest extends TestCase
{
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $this->storagePath = sys_get_temp_dir().'/sgcore-import-'.uniqid('', true);
        File::makeDirectory($this->storagePath, 0755, true);

        config(['cms.themes.storage_path' => $this->storagePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    /**
     * Build a ZIP file with the given entry => content map.
     *
     * @param  array<string, string>  $entries
     */
    private function zip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'themezip').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return new UploadedFile($path, 'meu-tema.zip', 'application/zip', null, true);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function entries(array $extra = []): array
    {
        return array_merge([
            'theme.json' => json_encode([
                'name' => 'Tema Importado',
                'version' => '2.0.0',
                'description' => 'Tema usado nos testes de importação.',
                'templates' => ['home' => 'index.html', 'page' => 'page.html', '404' => '404.html'],
                'slots' => ['site.name' => ['type' => 'text', 'scope' => 'global']],
                'menus' => ['primary' => 'Principal'],
            ], JSON_UNESCAPED_SLASHES),
            'index.html' => '<html><body>home</body></html>',
            'page.html' => '<html><body>page</body></html>',
            '404.html' => '<html><body>404</body></html>',
            'styles.css' => 'body{}',
        ], $extra);
    }

    public function test_valid_zip_is_extracted_and_discovered(): void
    {
        $theme = app(ThemeImporter::class)->import($this->zip($this->entries()));

        $this->assertSame('meu-tema', $theme->slug);
        $this->assertTrue($theme->valid);
        $this->assertFalse($theme->builtin);
        $this->assertDirectoryExists($this->storagePath.'/meu-tema');
        $this->assertFileExists($this->storagePath.'/meu-tema/theme.json');

        $this->assertTrue(app(ThemeDiscovery::class)->find('meu-tema')?->valid);
    }

    public function test_zip_wrapped_in_a_single_folder_is_accepted(): void
    {
        $wrapped = [];

        foreach ($this->entries() as $name => $content) {
            $wrapped['pasta-baixada/'.$name] = $content;
        }

        $theme = app(ThemeImporter::class)->import($this->zip($wrapped));

        $this->assertSame('meu-tema', $theme->slug);
        $this->assertFileExists($this->storagePath.'/meu-tema/theme.json');
        $this->assertSame('', (string) $theme->error);
    }

    public function test_zip_without_theme_json_is_rejected(): void
    {
        $this->expectException(ThemeImportException::class);
        $this->expectExceptionMessage('theme.json');

        app(ThemeImporter::class)->import($this->zip(['index.html' => '<html></html>']));
    }

    public function test_zip_with_invalid_manifest_is_rejected_and_cleaned_up(): void
    {
        try {
            app(ThemeImporter::class)->import($this->zip([
                'theme.json' => json_encode(['name' => 'Sem templates']),
            ]));

            $this->fail('A importação deveria ter falhado.');
        } catch (ThemeImportException $exception) {
            $this->assertStringContainsString('templates', $exception->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->storagePath.'/meu-tema');
        $this->assertSame([], glob($this->storagePath.'/.import-*') ?: []);
    }

    public function test_zip_slip_entries_are_rejected(): void
    {
        $this->expectException(ThemeImportException::class);
        $this->expectExceptionMessage('inseguros');

        app(ThemeImporter::class)->import($this->zip($this->entries([
            '../evil.php' => '<?php echo "boom";',
        ])));
    }

    public function test_existing_theme_slug_is_rejected(): void
    {
        app(ThemeImporter::class)->import($this->zip($this->entries()));

        $this->expectException(ThemeImportException::class);
        $this->expectExceptionMessage('Já existe');

        app(ThemeImporter::class)->import($this->zip($this->entries()));
    }

    public function test_trashed_zip_is_rejected(): void
    {
        $path = $this->storagePath.'/sumiu.zip';
        file_put_contents($path, 'PK'); // exists at construction…

        $file = new UploadedFile($path, 'meu-tema.zip', 'application/zip', null, true);

        unlink($path); // …and disappears before the import runs

        $this->expectException(ThemeImportException::class);

        app(ThemeImporter::class)->import($file);
    }
}
