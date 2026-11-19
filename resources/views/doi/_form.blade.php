<div class="mb-3">
    <label class="form-label" for="ten">Tên đội</label>
    <input id="ten" name="ten" value="{{ old('ten', $doi?->ten) }}" required class="form-control @error('ten') is-invalid @enderror">
    @error('ten') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="logo">Logo <span class="text-muted small">(ảnh, tối đa 2MB)</span></label>
    <input id="logo" type="file" name="logo" accept="image/*" class="form-control @error('logo') is-invalid @enderror">
    @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="tinh_thanh_id">Khu vực</label>
    <select id="tinh_thanh_id" name="tinh_thanh_id" class="form-select">
        <option value="">—</option>
        @foreach (\App\Models\TinhThanh::orderBy('thu_tu')->get() as $t)
            <option value="{{ $t->id }}" @selected((int) old('tinh_thanh_id', $doi?->tinh_thanh_id) === $t->id)>{{ $t->ten }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label" for="mo_ta">Giới thiệu</label>
    <textarea id="mo_ta" name="mo_ta" rows="2" class="form-control">{{ old('mo_ta', $doi?->mo_ta) }}</textarea>
</div>
