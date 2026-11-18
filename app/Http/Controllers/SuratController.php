<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\Surat;
use App\Models\User;
use App\Notifications\SuratMasuk;
use App\Notifications\SuratTerkirim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuratController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Surat::query()
            ->untukUser($user)
            ->withExists([
                'disposisis as diterima_saya' => fn ($query) => $query->where('ke_user_id', $user->id),
                'disposisis as dikirim_saya' => fn ($query) => $query->where('dari_user_id', $user->id),
            ])
            ->latest();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('nomor_agenda', 'like', "%{$q}%")
                    ->orWhere('nomor_surat', 'like', "%{$q}%")
                    ->orWhere('perihal', 'like', "%{$q}%")
                    ->orWhere('asal_surat', 'like', "%{$q}%");
            });
        }

        if ($request->filled('jenis')) {
            $query->where('jenis_surat', $request->string('jenis'));
        }

        $isAgenda = $request->routeIs('agenda.index');
        $agendaTerpilih = null;

        if ($isAgenda && $request->filled('agenda')) {
            $agendaTerpilih = (clone $query)->whereKey($request->integer('agenda'))->first();
        }

        $surats = $query->paginate(10)->withQueryString();

        if ($isAgenda && ! $request->filled('agenda')) {
            $agendaTerpilih = $surats->first();
        }

        $barisAgenda = collect();
        $tanggalDiterimaAgenda = collect();

        $jabatanAgenda = [
            'Direktur' => [[], 'direktur'],
            'Wadirkumad' => [['wadirkumad'], 'wadir'],
            'Irditkumad' => [['irditkumad'], null],
            'Kasubditbinum Ditkumad' => [['kasubditbinum'], 'kasubditbinum'],
            'Kasubditbincab Ditkumad' => [['kasubditbincab'], null],
            'Kasubditbindiklat Ditkumad' => [['kasubditbindiklat'], null],
            'Kasubditdukkum Ditkumad' => [['kasubditdukkum'], null],
            'Kasubditbankumpid Ditkumad' => [['kasubditbankumpid'], null],
            'Kasubditbankumperdatun Ditkumad' => [['kasubditbankumperdatun'], null],
            'Kasubditbinundang Ditkumad' => [['kasubditbinundang'], null],
            'Kainfolahta Ditkumad' => [['kainfolahta'], null],
            'Kabag' => [['kabag_tu', 'kabag_pam', 'kabag_pers', 'kabag_log', 'kabag_proggar', 'kabag_urdal', 'kabag_ter'], null],
            'Ketua' => [['ketua_sthm'], null],
        ];

        if ($isAgenda && $agendaTerpilih) {
            $agendaTerpilih->load(['disposisis.dari']);
            $penerimaAgenda = User::orderBy('id')->get()->keyBy('id');
            $disposisiAgenda = $agendaTerpilih->disposisis->sortBy('created_at');
            $disposisiDiterima = $disposisiAgenda->filter(fn ($disposisi) => $penerimaAgenda->has($disposisi->ke_user_id));
            $tanggalDiterimaAgenda = $disposisiDiterima
                ->groupBy('ke_user_id')
                ->map(fn ($disposisis) => $disposisis->first()->created_at);

            $kodeTercakup = collect($jabatanAgenda)->flatMap(fn ($baris) => $baris[0])->all();
            $roleTercakup = collect($jabatanAgenda)->pluck(1)->filter()->all();

            foreach ($jabatanAgenda as $jabatan => [$kodeJabatan, $role]) {
                $cocok = $disposisiDiterima->filter(fn ($disposisi) => in_array($disposisi->jabatan_tujuan, $kodeJabatan, true)
                    || ($disposisi->jabatan_tujuan === null && $role && $penerimaAgenda->get($disposisi->ke_user_id)?->role === $role));
                $disposisi = $cocok->first();

                $barisAgenda->push([
                    'label' => $jabatan,
                    'penerima' => $disposisi ? $penerimaAgenda->get($disposisi->ke_user_id) : ($role ? $penerimaAgenda->firstWhere('role', $role) : null),
                    'tanggal_diterima' => $disposisi?->created_at,
                    'dari' => $cocok->pluck('dari')->filter()->unique('id')->values(),
                ]);
            }

            $urutanJabatan = array_keys(Disposisi::jabatanList() + Disposisi::jabatanKasubditList());

            $disposisiTambahan = $disposisiAgenda
                ->reject(fn ($d) => in_array($d->jabatan_tujuan, $kodeTercakup, true)
                    || ($d->jabatan_tujuan === null && in_array($penerimaAgenda->get($d->ke_user_id)?->role, $roleTercakup, true)))
                ->groupBy(fn ($d) => $d->jabatan_tujuan ?? 'akun-'.$d->ke_user_id)
                ->sortBy(function ($grup, $kunci) use ($urutanJabatan) {
                    $posisi = array_search($kunci, $urutanJabatan, true);

                    return $posisi === false ? PHP_INT_MAX : $posisi;
                });

            foreach ($disposisiTambahan as $grup) {
                $diterima = $grup->filter(fn ($disposisi) => $penerimaAgenda->has($disposisi->ke_user_id));
                $disposisi = $diterima->first() ?? $grup->first();

                $barisAgenda->push([
                    'label' => $disposisi->jabatan_tujuan
                        ? Disposisi::label($disposisi->jabatan_tujuan, $disposisi->dari?->role)
                        : ($penerimaAgenda->get($disposisi->ke_user_id)?->name ?? 'Penerima Lain'),
                    'penerima' => $penerimaAgenda->get($disposisi->ke_user_id),
                    'tanggal_diterima' => $diterima->first()?->created_at,
                    'dari' => $diterima->pluck('dari')->filter()->unique('id')->values(),
                ]);
            }

            $barisAgenda->push(['label' => '', 'penerima' => null, 'tanggal_diterima' => null, 'dari' => collect()]);
        }

        if ($isAgenda && ! $agendaTerpilih && Surat::count() === 0) {
            foreach (array_keys($jabatanAgenda) as $jabatan) {
                $barisAgenda->push(['label' => $jabatan, 'penerima' => null, 'tanggal_diterima' => null, 'dari' => collect()]);
            }

            $barisAgenda->push(['label' => '', 'penerima' => null, 'tanggal_diterima' => null, 'dari' => collect()]);
        }

        return view('surat.index', compact('surats', 'agendaTerpilih', 'isAgenda', 'barisAgenda', 'tanggalDiterimaAgenda'));
    }

    public function create()
    {
        return view('surat.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal_surat' => ['required', 'date'],
            'asal_surat' => ['required', 'string', 'max:255'],
            'perihal' => ['required', 'string'],
            'catatan_tu' => ['nullable', 'string', 'max:1000'],
            'jenis_surat' => ['required', Rule::in(array_keys(Surat::jenisList()))],
            'file_pdf' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ], [
            'file_pdf.required' => 'File PDF surat wajib diunggah.',
            'file_pdf.mimes' => 'File surat harus berformat PDF.',
            'file_pdf.max' => 'Ukuran file PDF maksimal 5 MB.',
        ]);

        $path = $request->file('file_pdf')->store('surat', 'local');

        $tanggalTerima = now()->toDateString();
        $direktur = User::where('role', 'direktur')->first();

        $surat = DB::transaction(function () use ($data, $path, $request, $tanggalTerima, $direktur) {
            $agenda = Surat::buatNomorAgenda($data['jenis_surat'], $tanggalTerima);

            $surat = Surat::create([
                'no_urut' => $agenda['no_urut'],
                'tahun' => $agenda['tahun'],
                'nomor_agenda' => $agenda['nomor_agenda'],
                'jenis_surat' => $data['jenis_surat'],
                'nomor_surat' => $agenda['nomor_agenda'],
                'tanggal_surat' => $data['tanggal_surat'],
                'tanggal_terima' => $tanggalTerima,
                'asal_surat' => $data['asal_surat'],
                'perihal' => $data['perihal'],
                'catatan_tu' => $data['catatan_tu'] ?? null,
                'file_pdf' => $path,
                'status' => 'baru',
                'created_by' => $request->user()->id,
            ]);

            if ($direktur) {
                $surat->disposisis()->create([
                    'dari_user_id' => $request->user()->id,
                    'ke_user_id' => $direktur->id,
                    'jabatan_tujuan' => null,
                    'instruksi' => 'Diteruskan otomatis oleh TU ke Direktur.',
                ]);
            }

            return $surat;
        });

        if ($direktur) {
            $direktur->notify(new SuratMasuk($surat));
        }
        $request->user()->notify(new SuratTerkirim($surat));

        return redirect()->route('surat.show', $surat)
            ->with('success', "Surat berhasil diagendakan: {$surat->nomor_agenda}, dan notifikasi terkirim ke Direktur.");
    }

    public function pratinjauNomor(Request $request)
    {
        $data = $request->validate([
            'jenis_surat' => ['required', Rule::in(array_keys(Surat::jenisList()))],
        ]);

        return response()->json(Surat::buatNomorAgenda($data['jenis_surat'], now()->toDateString()));
    }

    public function show(Surat $surat)
    {
        $this->bolehAkses($surat);
        $surat->load(['disposisis.dari', 'disposisis.ke', 'pembuat']);

        return view('surat.show', compact('surat'));
    }

    public function destroy(Request $request, Surat $surat)
    {
        $this->bolehAkses($surat);

        $nomor = $surat->nomor_agenda;
        $file = $surat->file_pdf;

        DB::transaction(function () use ($surat) {
            DB::table('notifications')->where('data->surat_id', $surat->id)->delete();
            $surat->disposisis()->delete();
            $surat->delete();
        });

        if ($file) {
            Storage::disk('local')->delete($file);
        }

        return redirect()->route('surat.index')
            ->with('success', "Surat {$nomor} beserta disposisi dan notifikasinya dihapus.");
    }

    public function kosongkan(Request $request)
    {
        abort_unless(
            $request->user()->role === 'tu',
            403,
            'Hanya TU yang dapat mengosongkan seluruh data surat.'
        );

        $jumlahSurat = Surat::count();
        $jumlahDisposisi = Disposisi::count();
        $jumlahNotifikasi = DB::table('notifications')->count();

        DB::transaction(function () {
            DB::table('notifications')->delete();
            Disposisi::query()->delete();
            Surat::query()->delete();
        });

        Storage::disk('local')->deleteDirectory('surat');

        return back()->with('success', "Data surat dikosongkan: {$jumlahSurat} surat, {$jumlahDisposisi} disposisi, dan {$jumlahNotifikasi} notifikasi dihapus.");
    }

    public function file(Surat $surat)
    {
        $this->bolehAkses($surat);
        abort_unless($surat->file_pdf && Storage::disk('local')->exists($surat->file_pdf), 404, 'File PDF surat tidak tersedia.');

        return Storage::disk('local')->response($surat->file_pdf);
    }

    public function unduh(Surat $surat)
    {
        abort_unless($surat->file_pdf && Storage::disk('local')->exists($surat->file_pdf), 404, 'File PDF surat tidak tersedia.');

        return Storage::disk('local')->download(
            $surat->file_pdf,
            'Surat-'.str_replace('/', '-', $surat->nomor_agenda).'.pdf'
        );
    }

    public function cetak(Surat $surat)
    {
        $this->bolehAkses($surat);
        $surat->load(['disposisis.dari', 'disposisis.ke', 'pembuat']);

        $pdf = Pdf::loadView('pdf.lembar', compact('surat'))->setPaper('a4');

        return $pdf->download('Lembar-Agenda-'.str_replace('/', '-', $surat->nomor_agenda).'.pdf');
    }

    private function bolehAkses(Surat $surat): void
    {
        $user = auth()->user();
        $boleh = in_array($user->role, ['tu', 'direktur'], true)
            || $surat->created_by === $user->id
            || $surat->disposisis()
                ->where(fn ($query) => $query
                    ->where('ke_user_id', $user->id)
                    ->orWhere('dari_user_id', $user->id))
                ->exists();

        abort_unless($boleh, 403, 'Anda tidak memiliki akses ke surat ini.');
    }
}
