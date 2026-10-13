@extends('blog::public.layout')

@section('content')
    <article class="post">
        <h1>{{ $post->title }}</h1>
        <p class="meta">
            {{ $post->published_at?->translatedFormat('j \d\e F \d\e Y') }}
            @if ($post->author?->name)
                · {{ $post->author->name }}
            @endif
            @if ($post->category)
                · <a href="{{ route('blog.index', ['categoria' => $post->category->slug]) }}">{{ $post->category->name }}</a>
            @endif
        </p>

        @if ($post->cover)
            <img class="cover" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: $post->title }}">
        @endif

        <div class="content">{!! $post->body() !!}</div>

        <p class="meta" style="margin-top:32px"><a href="{{ route('blog.index') }}">&larr; {{ __('blog.back_to_blog') }}</a></p>
    </article>
@endsection
