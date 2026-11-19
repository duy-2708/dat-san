@extends('errors.khung')
@section('ma', '429')
@section('tieu_de', 'Thao tác quá nhanh')
@section('mo_ta', $exception->getMessage() ?: 'Bạn gửi quá nhiều yêu cầu. Đợi một lát rồi thử lại.')
