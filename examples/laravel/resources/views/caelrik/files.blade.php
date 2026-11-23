@extends('caelrik.layout')

@section('title', 'File manager')

@section('content')
    <x-caelrik :component="$fileManager" />
@endsection
