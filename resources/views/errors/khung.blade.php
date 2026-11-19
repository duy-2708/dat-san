{{-- Trang lỗi độc lập: không truy vấn CSDL, không lộ thông tin kỹ thuật --}}
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('ma') · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f7f5; color: #1f2d27; }
        .hop { text-align: center; padding: 2rem; max-width: 460px; }
        .bong { font-size: 3.5rem; }
        .ma { font-size: 3rem; font-weight: 800; color: #198754; margin: .25rem 0; }
        p { color: #5b6b63; line-height: 1.5; }
        a { display: inline-block; margin-top: 1rem; padding: .6rem 1.2rem; background: #198754; color: #fff; border-radius: .4rem; text-decoration: none; }
    </style>
</head>
<body>
<div class="hop">
    <div class="bong">⚽</div>
    <div class="ma">@yield('ma')</div>
    <h1 style="font-size:1.25rem">@yield('tieu_de')</h1>
    <p>@yield('mo_ta')</p>
    <a href="{{ url('/') }}">Về trang chủ</a>
</div>
</body>
</html>
