@extends('caelrik.layout')

@section('title', 'Scheduler')

@section('content')
    <p class="text-body-secondary small">
        Drag or resize a meeting to move it, select a time range to create one, click a meeting to delete it.
    </p>

    <x-caelrik :component="$scheduler" />
@endsection
