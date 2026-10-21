<?php

namespace Tests\Feature\Support;

use App\Support\ErrorPresenter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        $router = $this->app['router'];
        $router->get('/__boom', function (): void {
            throw new RuntimeException('kaboom-presenter');
        });
        $router->get('/__missing', function (): void {
            throw new NotFoundHttpException;
        });
    }

    public function test_status_mapping(): void
    {
        $pairs = [
            [new NotFoundHttpException, 404],
            [new ModelNotFoundException, 404],
            [new AuthenticationException, 401],
            [new AccessDeniedHttpException, 403],
            [new TokenMismatchException, 419],
            [new HttpException(429, 'slow down'), 429],
            [new RuntimeException, 500],
        ];

        foreach ($pairs as [$exception, $expected]) {
            $this->assertSame($expected, ErrorPresenter::status($exception));
        }
    }

    public function test_debug_page_shows_exception_details(): void
    {
        config(['app.debug' => true]);

        $this->withExceptionHandling()
            ->get('/__boom')
            ->assertStatus(500)
            ->assertSee('500', false)
            ->assertSee('sgcore', false)
            ->assertSee('kaboom-presenter', false)
            ->assertSee('RuntimeException', false)
            ->assertSee('ErrorPageTest.php', false);
    }

    public function test_production_page_hides_technical_details(): void
    {
        config(['app.debug' => false]);

        $this->withExceptionHandling()
            ->get('/__boom')
            ->assertStatus(500)
            ->assertSee('sgcore', false)
            ->assertSee('500', false)
            ->assertDontSee('kaboom-presenter', false)
            ->assertDontSee('RuntimeException', false)
            ->assertDontSee('ErrorPageTest.php', false);
    }

    public function test_not_found_page_is_rendered(): void
    {
        config(['app.debug' => false]);

        $this->withExceptionHandling()
            ->get('/__missing')
            ->assertStatus(404)
            ->assertSee('404', false)
            ->assertSee('sgcore', false)
            ->assertDontSee('kaboom-presenter', false);
    }

    public function test_json_requests_are_not_replaced_by_the_html_page(): void
    {
        config(['app.debug' => true]);

        $this->withExceptionHandling()
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/__boom')
            ->assertStatus(500)
            ->assertJsonPath('message', 'kaboom-presenter');
    }
}
