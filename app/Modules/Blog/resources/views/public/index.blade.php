@extends('blog::public.layout')

@section('content')
    <h1 class="page-title">{{ $category ? $category->name : __('blog.title') }}</h1>

    @if ($categories->isNotEmpty())
        <div class="filters">
            <a class="chip" href="{{ route('blog.index') }}" @if (! $category) aria-current="page" @endif>{{ __('blog.all_categories') }}</a>
            @foreach ($categories as $cat)
                <a class="chip" href="{{ route('blog.index', ['categoria' => $cat->slug]) }}" @if ($category && $category->id === $cat->id) aria-current="page" @endif>
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>
    @endif

    @forelse ($posts as $post)
        <article class="post-card">
            <h2><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
            <p class="meta">
                {{ $post->published_at?->translatedFormat('j \\d\\e F \\d\\e Y') }}
                @if ($post->category)
                    · {{ $post->category->name }}
                @endif
            </p>
            @if ($post->excerpt)
                <p class="excerpt">{{ $post->excerpt }}</p>
            @endif
        </article>
    @empty
        <p class="empty">{{ __('blog.no_posts') }}</p>
    @endforelse

    @if ($posts->hasPages())
        <nav class="pager">
            <span>
                @if ($posts->previousPageUrl())
                    <a href="{{ $posts->previousPageUrl() }}">{{ __('blog.newer') }}</a>
                @endif
            </span>
            <span>
                @if ($posts->nextPageUrl())
                    <a href="{{ $posts->nextPageUrl() }}">{{ __('blog.older') }}</a>
                @endif
            </span>
        </nav>
    @endif
@endsection
