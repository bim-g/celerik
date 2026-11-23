@extends('caelrik.layout')

@section('title', 'Theme builder')

@section('content')
    <x-caelrik :component="$builder" />
@endsection
