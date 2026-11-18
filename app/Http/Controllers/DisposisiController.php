<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\Surat;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Notifications\DisposisiDiteruskan;
use App\Notifications\DisposisiTerkirim;
use App\Notifications\ProgresDisposisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisposisiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $menunggu = Surat::untukUser($user)->where('status', 'baru')->latest()->get();

        $suratDisposisi = Surat::whereHas('disposisis', fn ($q) => $q->where('dari_user_id', $user->id))
            ->with(['disposisis.dari', 'disposisis.ke'])
            ->orderByDesc('updated_at')
            ->get();

        $diteruskan = Surat::whereHas('disposisis', fn ($q) => $q->where('ke_user_id', $user->id))
            ->with(['disposisis' => fn ($q) => $q->where('ke_user_id', $user->id)->with('dari')->latest()])
            ->orderByDesc('updated_at')
            ->get();

        $disposisiSaya = Disposisi::whereIn('surat_id', $suratDisposisi->pluck('id'))->whereNotNull('ke_user_id');

        $stats = [
            'menunggu' => $menunggu->count(),
            'diteruskan' => $diteruskan->count(),
            'surat' => $suratDisposisi->count(),
            'belum_dibaca' => (clone $disposisiSaya)->whereNull('dibaca_at')->count(),
            'selesai' => (clone $disposisiSaya)->whereNotNull('selesai_at')->count(),
        ];

        return view('disposisi.index', compact('menunggu', 'diteruskan', 'suratDisposisi', 'stats'));
    }

    public function store(Request $request, Surat $surat)
    {
        if ($request->user()->role === 'kasubditbinum') {
            abort_unless(
                $surat->disposisis()->where('ke_user_id', $request->user()->id)->exists(),
                403,
                'Anda harus menerima disposisi sebelum meneruskannya.'
            );
        }

        return $this->storeDariCeklis($request, $surat);
    }

    private function storeDariCeklis(Request $request, Surat $surat)
    {
        [$jabatanList, $perintahList] = match ($request->user()->role) {
            'direktur' => [Disposisi::jabatanDirekturList(), Disposisi::perintahList()],
            'wadir' => [Disposisi::jabatanWadirList(), Disposisi::perintahList()],
            default => [Disposisi::jabatanKasubditList(), Disposisi::perintahKasubditList()],
        };

        $data = $request->validate([
            'jabatan' => ['required', 'array', 'min:1', 'max:'.count($jabatanList)],
            'jabatan.*' => ['required', 'string', 'distinct', Rule::in(array_keys($jabatanList))],
            'perintah' => ['required', 'array', 'min:1', 'max:'.count($perintahList)],
            'perintah.*' => ['required', 'string', 'distinct', Rule::in(array_keys($perintahList))],
            'lain_lain' => ['nullable', 'string', 'max:2000'],
        ], [
            'jabatan.required' => 'Ceklis minimal satu jabatan tujuan.',
            'jabatan.*.in' => 'Jabatan tujuan tidak valid.',
            'perintah.required' => 'Ceklis minimal satu perintah disposisi.',
            'perintah.*.in' => 'Perintah disposisi tidak valid.',
            'lain_lain.max' => 'Catatan lain-lain maksimal 2000 karakter.',
        ]);

        $penerimaJabatan = Disposisi::penerimaJabatan();

        $instruksi = implode('; ', array_intersect_key($perintahList, array_flip($data['perintah'])));
        if (! empty($data['lain_lain'])) {
            $instruksi .= "\nLain-lain: ".$data['lain_lain'];
        }

        $diberitahu = 0;

        $pengirimSebelumnya = User::whereIn(
            'id',
            Disposisi::where('surat_id', $surat->id)
                ->where('ke_user_id', $request->user()->id)
                ->pluck('dari_user_id')
        )->where('id', '!=', $request->user()->id)->get();

        DB::transaction(function () use ($request, $surat, $penerimaJabatan, $instruksi, $data, &$diberitahu) {
            if ($request->user()->role === 'direktur') {
                $wadirList = User::where('role', 'wadir')->orderBy('id')->get();

                if ($wadirList->isEmpty()) {
                    $surat->disposisis()->create([
                        'dari_user_id' => $request->user()->id,
                        'ke_user_id' => null,
                        'jabatan_tujuan' => 'wadirkumad',
                        'target_jabatan' => $data['jabatan'],
                        'instruksi' => $instruksi,
                    ]);
                } else {
                    foreach ($wadirList as $akun) {
                        $disposisi = $surat->disposisis()->create([
                            'dari_user_id' => $request->user()->id,
                            'ke_user_id' => $akun->id,
                            'jabatan_tujuan' => 'wadirkumad',
                            'target_jabatan' => $data['jabatan'],
                            'instruksi' => $instruksi,
                        ]);

                        $akun->notify(new DisposisiBaru($disposisi));
                        $diberitahu++;
                    }
                }

                $surat->update(['status' => 'diproses']);

                return;
            }

            foreach ($data['jabatan'] as $jabatan) {
                $akunList = ($penerimaJabatan[$jabatan] ?? collect())
                    ->reject(fn ($akun) => $akun->id === $request->user()->id)
                    ->values();

                if ($akunList->isEmpty()) {
                    $surat->disposisis()->create([
                        'dari_user_id' => $request->user()->id,
                        'ke_user_id' => null,
                        'jabatan_tujuan' => $jabatan,
                        'instruksi' => $instruksi,
                    ]);

                    continue;
                }

                foreach ($akunList as $akun) {
                    $disposisi = $surat->disposisis()->create([
                        'dari_user_id' => $request->user()->id,
                        'ke_user_id' => $akun->id,
                        'jabatan_tujuan' => $jabatan,
                        'instruksi' => $instruksi,
                    ]);

                    $akun->notify(new DisposisiBaru($disposisi));
                    $diberitahu++;
                }
            }

            $surat->update(['status' => 'diproses']);
        });

        $tujuan = array_values(array_intersect_key($jabatanList, array_flip($data['jabatan'])));
        $request->user()->notify(new DisposisiTerkirim($surat, $tujuan));

        foreach ($pengirimSebelumnya as $pengirim) {
            $pengirim->notify(new DisposisiDiteruskan($surat, $request->user(), $tujuan));
        }

        if ($request->user()->role === 'direktur') {
            $pesan = 'Disposisi Dirkumad diteruskan kepada WADIRKUMAD untuk '.count($data['jabatan']).' jabatan tujuan.';
            $pesan .= $diberitahu > 0
                ? ' WADIRKUMAD telah diberi notifikasi.'
                : ' Akun WADIRKUMAD belum terdaftar, disposisi tercatat tanpa notifikasi.';
        } else {
            $pesan = 'Disposisi terkirim ke '.count($data['jabatan']).' jabatan tujuan.';
            $pesan .= $diberitahu > 0
                ? ' '.$diberitahu.' akun penerima telah diberi notifikasi.'
                : ' Jabatan tujuan belum memiliki akun, disposisi tercatat tanpa notifikasi.';
        }

        return back()->with('success', $pesan);
    }

    public function destroy(Request $request, Disposisi $disposisi)
    {
        $user = $request->user();
        abort_unless(
            $disposisi->dari_user_id === $user->id || $disposisi->ke_user_id === $user->id,
            403,
            'Hanya pengirim atau penerima disposisi yang dapat menghapus riwayat ini.'
        );

        $surat = $disposisi->surat;
        $disposisi->delete();
        $surat->segarkanStatus();

        return back()->with('success', 'Riwayat disposisi dihapus.');
    }

    public function checklist(Request $request, Disposisi $disposisi)
    {
        abort_unless($disposisi->ke_user_id === $request->user()->id, 403, 'Hanya penerima disposisi yang dapat mengubah checklist.');

        $data = $request->validate([
            'aksi' => ['required', Rule::in(['dibaca', 'tindak_lanjut', 'selesai'])],
            'catatan' => ['nullable', 'string'],
        ]);

        $kolom = $data['aksi'].'_at';
        $disposisi->update([
            $kolom => $disposisi->{$kolom} ?? now(),
            'catatan' => $data['catatan'] ?? $disposisi->catatan,
        ]);

        $surat = $disposisi->surat;
        if (! $surat->disposisis()->whereNotNull('ke_user_id')->whereNull('selesai_at')->exists()) {
            $surat->update(['status' => 'selesai']);
        }

        if (in_array($data['aksi'], ['tindak_lanjut', 'selesai'], true)
            && $disposisi->dari_user_id !== $request->user()->id
            && $disposisi->dari) {
            $disposisi->dari->notify(new ProgresDisposisi($disposisi, $data['aksi']));
        }

        return back()->with('success', 'Checklist disposisi diperbarui.');
    }
}
