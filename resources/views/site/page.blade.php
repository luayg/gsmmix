@php($translation=$page->translations->firstWhere('language.locale',app()->getLocale()) ?? $page->translations->first())
@extends('layouts.site')
@section('title',$translation?->seo_title ?: $translation?->title)
@section('description',$translation?->seo_description)
@section('content')<section class="pt-5 mt-5"><div class="container py-5"><div class="gsm-card p-4 p-lg-5"><div class="text-primary fw-bold small text-uppercase">{{ config('app.name') }}</div><h1 class="section-title display-5 mb-4">{{ $translation?->title }}</h1>@if($page->featured_image)<img class="img-fluid rounded-4 mb-4" src="{{ asset($page->featured_image) }}" alt="">@endif<article class="fs-6 lh-lg">{!! $translation?->content !!}</article></div></div></section>@endsection
