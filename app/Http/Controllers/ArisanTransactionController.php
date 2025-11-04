<?php

namespace App\Http\Controllers;

use App\Models\ArisanTransaction;
use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArisanTransactionController extends Controller
{
    /**
     * Helper function untuk mendapatkan data rekapitulasi Arisan (Digunakan oleh rekap dan exportRekap).
     */
    private function getRekapData(Request $request, $isPaginated = true)
    {
        // Validasi yang sama seperti di rekap()
        $request->validate([
            'periode_id' => 'nullable|exists:periode,id',
            'year' => 'nullable|digits:4',
            'page' => 'nullable|integer|min:1',
            'q' => 'nullable|string',
            'rt' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'min' => 'nullable|numeric|min:0',
            'max' => 'nullable|numeric|min:0',
        ]);

        // 🔹 Ambil tanggal awal & akhir dari periode atau year
        if ($request->filled('periode_id')) {
            $periode = Periode::find($request->periode_id);
            if (!$periode) {
                return [
                    'error' => true,
                    'message' => 'Periode tidak ditemukan.',
                    'code' => 404
                ];
            }
            $startDate = Carbon::parse($periode->start_date)->startOfDay();
            $endDate = Carbon::parse($periode->end_date)->endOfDay();
            $periodeNama = $periode->nama;
            $periodeId = $periode->id;
            // Asumsi ada kolom nominal_arisan di tabel periode
            $nominalArisan = $periode->nominal_arisan ?? 0;
        } elseif ($request->filled('year')) {
            $year = $request->year;
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();
            $periodeNama = "Tahun $year";
            $periodeId = null;
            $nominalArisan = 0; // Tidak bisa menentukan nominal tanpa periode spesifik
        } else {
            return [
                'error' => true,
                'message' => 'Harus mengirim periode_id atau year',
                'code' => 422
            ];
        }

        // 🗓️ Generate tanggal interval (14 hari)
        $dates = collect();
        $period = CarbonPeriod::create($startDate, '14 days', $endDate);
        foreach ($period as $date) {
            $dates->push($date->toDateString());
        }

        // 🔹 Ambil data warga dan status bayar per tanggal
        $rawData = DB::table('warga')
            ->select(
                'warga.id as warga_id',
                'warga.nama',
                'warga.rt',
                'arisan_transactions.tanggal',
                'arisan_transactions.status',
                'arisan_transactions.jumlah',
                'pw.status_arisan' // Asumsi join ke periode_warga untuk status pemenang
            )
            ->leftJoin('arisan_transactions', function ($join) use ($periodeId, $dates) {
                $join->on('warga.id', '=', 'arisan_transactions.warga_id')
                    ->whereIn('arisan_transactions.tanggal', $dates);
                if ($periodeId) {
                    $join->where('arisan_transactions.periode_id', $periodeId);
                }
            })
            // Join ke tabel periode_warga untuk mendapatkan status pemenang arisan
            ->leftJoin('periode_warga as pw', function ($join) use ($periodeId) {
                $join->on('warga.id', '=', 'pw.warga_id');
                // PENTING: Hanya join jika periodeId tersedia, jika tidak, status_arisan akan null
                if ($periodeId) {
                    $join->where('pw.periode_id', $periodeId);
                } else {
                    // Jika periode tidak spesifik, kita tidak bisa menentukan status arisan dari pw
                    $join->whereNull('pw.periode_id');
                }
            })
            ->when($request->q, fn($q) => $q->where('warga.nama', 'like', "%{$request->q}%"))
            ->when($request->rt, fn($q) => $q->where('warga.rt', $request->rt))
            ->when($request->min, fn($q) => $q->where('arisan_transactions.jumlah', '>=', $request->min))
            ->when($request->max, fn($q) => $q->where('arisan_transactions.jumlah', '<=', $request->max))
            ->when($request->from && $request->to, fn($q) => $q->whereBetween('arisan_transactions.tanggal', [$request->from, $request->to]))
            ->orderBy('warga.rt')
            ->orderBy('warga.nama');

        // Jika ada paginasi (untuk index/rekap)
        if ($isPaginated) {
            $data = $rawData->paginate(10);
        } else {
            $data = $rawData->get();
        }

        return [
            'error' => false,
            'data' => $data,
            'dates' => $dates,
            'periodeNama' => $periodeNama,
            'periodeId' => $periodeId,
            'nominalArisan' => $nominalArisan,
            'filters' => [
                'periode_id' => $periodeId,
                'year' => $request->year,
                'rt' => $request->rt,
                'q' => $request->q,
                'from' => $request->from,
                'to' => $request->to,
                'min' => $request->min,
                'max' => $request->max,
            ],
        ];
    }

    public function rekap(Request $request)
    {
        // Panggil getRekapData untuk mendapatkan data dan filter
        $result = $this->getRekapData($request, true);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        // Ambil data yang sudah dipaginasi dari hasil
        $paginatedData = $result['data'];

        // Re-format data agar sesuai dengan struktur yang diharapkan (grouping)
        // Karena data sudah dipaginasi, kita hanya memproses data di halaman ini.
        $groupedData = $paginatedData->getCollection()->groupBy('warga_id')->map(function ($transactions) use ($result) {

            // Perbaikan: Menggunakan first() method dengan tanda kurung
            $warga = $transactions->first();
            $totalSetoran = 0;
            $paymentStatus = [];

            foreach ($result['dates'] as $date) {
                // Perbaikan: Menggunakan firstWhere() method dengan tanda kurung
                $trx = $transactions->firstWhere('tanggal', $date);
                if ($trx) {
                    $totalSetoran += $trx->jumlah;
                    $paymentStatus[$date] = [
                        'status' => $trx->status,
                        'jumlah' => $trx->jumlah,
                    ];
                } else {
                    $paymentStatus[$date] = [
                        'status' => 'belum_bayar',
                        'jumlah' => 0,
                    ];
                }
            }

            // Ganti key 'id' dengan 'warga_id' agar konsisten
            $warga->warga_id = $warga->warga_id ?? $warga->id;

            return [
                'warga_id' => $warga->warga_id,
                'nama' => $warga->nama,
                'rt' => $warga->rt,
                'status_arisan' => $warga->status_arisan,
                'total_setoran' => $totalSetoran,
                'payment_status' => $paymentStatus,
            ];
        })->values();

        // Ganti collection di paginator dengan data yang sudah di-grouping
        $paginatedData->setCollection($groupedData);

        return response()->json([
            'message' => 'Rekap arisan berhasil diambil',
            'periode' => $result['periodeNama'],
            'nominal_arisan' => $result['nominalArisan'],
            'dates' => $result['dates'],
            'filters' => $result['filters'],
            'data' => $paginatedData,
        ]);
    }

    public function rekapSave(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periode,id',
            'updates' => 'required|array',
            'updates.*.warga_id' => 'required|exists:warga,id',
            'updates.*.tanggal' => 'required|date',
            'updates.*.status' => 'required|in:sudah_bayar,belum_bayar',
            // Tambahkan validasi jumlah, asumsikan nominal setoran arisan
            'updates.*.jumlah' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->updates as $item) {
                ArisanTransaction::updateOrCreate(
                    [
                        'warga_id' => $item['warga_id'],
                        'periode_id' => $request->periode_id,
                        'tanggal' => $item['tanggal'],
                    ],
                    [
                        'status' => $item['status'],
                        'jumlah' => $item['jumlah'],
                        'admin_id' => $request->user()->id,
                        'updated_at' => now(),
                    ]
                );
            }

            DB::commit();
            return response()->json(['message' => 'Rekap arisan berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            // Log error
            return response()->json(['message' => 'Gagal menyimpan rekap arisan.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mengekspor data rekapitulasi arisan ke format CSV.
     * Endpoint: GET /api/arisan/rekap/export
     */
    public function exportRekap(Request $request)
    {
        // 1. Ambil Data (tanpa paginasi)
        $result = $this->getRekapData($request, false);

        if ($result['error']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        $allTransactions = $result['data'];
        $dates = $result['dates'];
        $periodeNama = $result['periodeNama'];
        $nominalArisan = $result['nominalArisan'];

        // 2. Re-organisasi data ke format Rekapitulasi per Warga
        $rekapData = $allTransactions->groupBy('warga_id')->map(function ($transactions) use ($dates) {
            /** @var \Illuminate\Support\Collection $transactions */

            // Perbaikan: Menggunakan first() method dengan tanda kurung
            $warga = $transactions->first();
            $totalSetoran = 0;
            $paymentStatus = []; // Status bayar per tanggal

            foreach ($dates as $date) {
                // Perbaikan: Menggunakan firstWhere() method dengan tanda kurung
                $trx = $transactions->firstWhere('tanggal', $date);

                if ($trx) {
                    $status = $trx->status === 'sudah_bayar' ? '✅' : '❌'; // Tanda checklist
                    $totalSetoran += $trx->jumlah;
                    $paymentStatus[$date] = [
                        'status' => $status,
                        'jumlah' => $trx->jumlah,
                    ];
                } else {
                    // Jika tidak ada transaksi
                    $paymentStatus[$date] = [
                        'status' => '⬜',
                        'jumlah' => 0,
                    ];
                }
            }

            return [
                'id' => $warga->warga_id,
                'nama' => $warga->nama,
                'rt' => $warga->rt,
                // Kolom yang diminta: status_arisan (pemenang arisan)
                'status_arisan' => $warga->status_arisan === 'sudah' ? 'Pemenang' : 'Belum',
                'total_setoran' => $totalSetoran,
                'payment_status' => $paymentStatus,
            ];
        })->values(); // Ambil nilainya

        // 3. Buat dan Kembalikan CSV
        $fileName = 'rekap_arisan_' . Str::slug($periodeNama) . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        // Buat StreamedResponse
        $callback = function () use ($rekapData, $dates, $nominalArisan) {
            $file = fopen('php://output', 'w');

            // Header Utama
            $mainHeader = ['Nama Warga', 'RT', 'Status Arisan', 'Total Setoran'];
            $dateHeaders = [];
            foreach ($dates as $date) {
                // Tambahkan header untuk setiap tanggal (kolom status)
                $dateHeaders[] = Carbon::parse($date)->format('d/m/Y');
            }

            fputcsv($file, array_merge($mainHeader, $dateHeaders));

            // Isi Data Rekap
            foreach ($rekapData as $data) {
                $row = [
                    $data['nama'],
                    $data['rt'],
                    $data['status_arisan'],
                    $data['total_setoran'],
                ];

                // Isi Status Bayar per tanggal
                foreach ($dates as $date) {
                    // Hanya tampilkan status (✅/❌/⬜)
                    $row[] = $data['payment_status'][$date]['status'] ?? '⬜';
                }

                fputcsv($file, $row);
            }

            // Baris Summary (Opsional)
            $totalSetoranSemua = $rekapData->sum('total_setoran');
            fputcsv($file, []); // Baris kosong
            fputcsv($file, ['Total Setoran Semua Warga:', '', '', $totalSetoranSemua]);
            if ($nominalArisan > 0) {
                fputcsv($file, ['Nominal Setoran Per Periode:', '', '', $nominalArisan]);
            }


            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
