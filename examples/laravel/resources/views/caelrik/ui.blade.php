@extends('caelrik.layout')

@section('title', 'Tabs, modal and notifications')

@section('content')
    <x-caelrik :component="$tabs" />

    <p class="mt-3">{!! $modal->trigger('Subscribe to the newsletter') !!}</p>
    <x-caelrik :component="$modal" />

    <x-caelrik :component="$notification" />
@endsection
