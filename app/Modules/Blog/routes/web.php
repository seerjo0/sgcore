<?php

use App\Modules\Blog\Http\Controllers\BlogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public blog
|--------------------------------------------------------------------------
|
| Concrete routes registered before the Themes fallback (module route files
| load alphabetically: "Blog" precedes "Themes"), so /blog always resolves to
| the archive instead of the page-by-slug fallback. "blog" is a reserved page
| slug (see Page::RESERVED_SLUGS) so the two never collide.
|
*/

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');

Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('blog.show');
