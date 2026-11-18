@extends('layouts.app')

@section('title', 'Input Surat Masuk')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold"><i class="bi bi-plus-circle me-1"></i>Input Surat Masuk</div>
        <div class="card-body">
            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i>
                Nomor agenda dibuat <strong>otomatis</strong> (contoh: 001/B/XI/2026) dengan tanggal terima hari ini.
                Setelah disimpan, surat <strong>otomatis diteruskan ke Direktur</strong> dan notifikasi dikirim.
            </div>
            <form method="POST" action="{{ route('surat.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nomor Surat</label>
                        <input id="nomor_surat" class="form-control" value="" readonly
                               placeholder="Terisi otomatis setelah file PDF dipilih">
                        <div class="form-text">Nomor surat mengikuti nomor agenda, terisi otomatis setelah file PDF dipilih.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Asal Surat <span class="text-danger">*</span></label>
                        <input name="asal_surat" value="{{ old('asal_surat') }}" class="form-control" required
                               placeholder="Instansi/pengirim surat">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Surat <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_surat" value="{{ old('tanggal_surat') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Surat <span class="text-danger">*</span></label>
                        <select id="jenis_surat" name="jenis_surat" class="form-select" required>
                            <option value="">-- Pilih Jenis --</option>
                            @foreach (\App\Models\Surat::jenisList() as $kode => $label)
                                <option value="{{ $kode }}" @selected(old('jenis_surat') === $kode)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Perihal <span class="text-danger">*</span></label>
                        <textarea name="perihal" class="form-control" rows="2" required placeholder="Perihal/isi ringkas surat">{{ old('perihal') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan TU</label>
                        <textarea name="catatan_tu" class="form-control" rows="2" placeholder="Catatan TU untuk lembar disposisi (opsional)">{{ old('catatan_tu') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">File Surat (PDF) <span class="text-danger">*</span></label>
                        <input type="file" id="file_pdf" name="file_pdf" class="form-control" accept="application/pdf" required>
                        <div class="form-text">Maksimal 5 MB. PDF ini terhubung ke agenda, disposisi, dan monitoring.</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan &amp; Agendakan</button>
                        <a href="{{ route('surat.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const berkasPdf = document.getElementById('file_pdf');
        const jenisSurat = document.getElementById('jenis_surat');
        const nomorSurat = document.getElementById('nomor_surat');

        async function muatNomorSurat() {
            if (!berkasPdf.files.length || !jenisSurat.value) {
                nomorSurat.value = '';
                return;
            }

            const respon = await fetch('/surat/pratinjau-nomor?jenis_surat=' + encodeURIComponent(jenisSurat.value), {
                headers: { 'Accept': 'application/json' },
            });

            if (!respon.ok) {
                nomorSurat.value = '';
                return;
            }

            const agenda = await respon.json();
            nomorSurat.value = agenda.nomor_agenda;
        }

        [berkasPdf, jenisSurat].forEach((el) => el.addEventListener('change', muatNomorSurat));
    </script>
@endsection