@extends('layouts.app')
@section('title', 'Tạo đội')

@section('content')
<div class="mx-auto" style="max-width: 520px">
    <h1 class="h4 mb-3">Tạo đội bóng</h1>
    @error('doi') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    <form method="POST" action="{{ route('khach.doi.store') }}" enctype="multipart/form-data" class="card card-body border-0 shadow-sm" novalidate>
        @csrf
        @include('doi._form', ['doi' => null])
        <button class="btn btn-success w-100">Tạo đội</button>
    </form>
</div>
@endsection
