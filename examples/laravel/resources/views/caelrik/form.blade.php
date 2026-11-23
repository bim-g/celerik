@extends('caelrik.layout')

@section('title', 'Form')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <x-caelrik :component="$form" />
@endsection
