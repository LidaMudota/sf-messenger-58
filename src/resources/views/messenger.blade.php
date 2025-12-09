@extends('layouts.app')

@section('content')
<div id="messenger-root" class="w-full h-screen">
</div>

{{-- Подключаем ES6 messenger.js --}}
@vite('resources/js/messenger.js')
@endsection
