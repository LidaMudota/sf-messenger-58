@extends('layouts.app')

@section('content')
<div id="messenger-root" class="w-full h-screen">
    <!-- JS полностью заменит содержимое -->
</div>

{{-- Подключаем ES6 messenger.js --}}
@vite('resources/js/messenger.js')
@endsection
