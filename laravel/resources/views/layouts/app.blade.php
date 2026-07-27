@extends('layouts.aktienki')

@vite(['resources/css/app.css', 'resources/js/app.js'])

@section('content')
    {{ $slot }}
@endsection