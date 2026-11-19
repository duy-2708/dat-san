@extends('errors.khung')
@section('ma', '403')
@section('tieu_de', 'Không có quyền truy cập')
@section('mo_ta', in_array($exception->getMessage(), ['', 'This action is unauthorized.', 'Forbidden'], true) ? 'Bạn không có quyền xem trang này.' : $exception->getMessage())
