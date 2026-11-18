{{-- Các ô chung khi admin tạo chủ sân / chủ sân tạo nhân viên --}}
<div class="mb-3">
    <label class="form-label" for="name">Họ tên</label>
    <input id="name" name="name" value="{{ old('name') }}" required class="form-control @error('name') is-invalid @enderror">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required
           class="form-control @error('email') is-invalid @enderror @error('email_chuan_hoa') is-invalid @enderror">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @error('email_chuan_hoa') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="so_dien_thoai">Số điện thoại</label>
    <input id="so_dien_thoai" type="tel" inputmode="numeric" name="so_dien_thoai" value="{{ old('so_dien_thoai') }}" required
           class="form-control @error('so_dien_thoai') is-invalid @enderror">
    @error('so_dien_thoai') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="password">Mật khẩu ban đầu</label>
    <input id="password" type="text" name="password" required autocomplete="off" class="form-control @error('password') is-invalid @enderror">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
