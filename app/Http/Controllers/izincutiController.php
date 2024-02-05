<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class izincutiController extends Controller
{
    public function create()
    {
        $mastercuti = DB::table('master_cuti')->orderBY('kode_cuti')->get();
        return view('izincuti.create', compact('mastercuti'));
    }

    public function store(Request $request)
    {
        $nik = Auth::guard('karyawan')->user()->nik;
        $tgl_izin_dari = $request->tgl_izin_dari;
        $tgl_izin_sampai = $request->tgl_izin_sampai;
        $kode_cuti = $request->kode_cuti;
        $status = "c";
        $keterangan = $request->keterangan;

        $bulan = date("m", strtotime($tgl_izin_dari));
        $tahun = date("Y", strtotime($tgl_izin_dari));
        $thn = substr($tahun, 2, 2);

        $lastizin = DB::table('pengajuan_izin')
            ->whereRaw('MONTH(tgl_izin_dari)="' . $bulan . '"')
            ->whereRaw('YEAR(tgl_izin_dari)="' . $tahun . '"')
            ->orderBy('kode_izin', 'desc')
            ->first();

        $lastkodeizin = $lastizin != null ? $lastizin->kode_izin : "";
        $format = "IZ" . $bulan . $thn;
        $kode_izin = buatkode($lastkodeizin, $format, 3);

        //hitung jumlah hari yang di ajukan
        $jmlhari = hitunghari($tgl_izin_dari, $tgl_izin_sampai);

        // Cek jumlah maksimal cuti
        $cuti = DB::table('master_cuti')->where('kode_cuti', $kode_cuti)->first();
        $jmlmaxcuti = $cuti->jml_hari;

        //cek jumlah cuti yang sudah di gunakan pada tahun aktif
        $cutidigunakan = DB::table('presensi')
            ->whereRaw('YEAR(tgl_presensi)="' . $tahun . '"')
            ->where('status', 'c')
            ->where('nik', $nik)
            ->count();

        //sisa cuti
        $sisacuti = $jmlmaxcuti - $cutidigunakan;



        $data = [
            'kode_izin' => $kode_izin,
            'nik' => $nik,
            'tgl_izin_dari' => $tgl_izin_dari,
            'tgl_izin_sampai' => $tgl_izin_sampai,
            'kode_cuti' => $kode_cuti,
            'status' => $status,
            'keterangan' => $keterangan
        ];

        //Cek Sudah Absen / Belum
        $cekpresensi = DB::table('presensi')
            ->whereBetween('tgl_presensi', [$tgl_izin_dari, $tgl_izin_sampai])
            ->where('nik', $nik);



        //Cek Sudah Di Ajukan / Belum
        $cekpengajuan = DB::table('pengajuan_izin')
            ->where('nik', $nik)
            ->whereRaw('"' . $tgl_izin_dari . '" BETWEEN tgl_izin_dari AND tgl_izin_sampai');



        $datapresensi = $cekpresensi->get();

        if ($jmlhari > $sisacuti) {
            return redirect('/presensi/izin')->with(['error' => 'Jumlah Hari Melebihi Batas Maksimal Jatah Cuti Tahunan,Sisa Cuti Tahunan ' . $sisacuti .  ' Hari']);
        } else if ($cekpresensi->count() > 0) {
            $blacklistdate = "";
            foreach ($datapresensi as $d) {
                $blacklistdate .= date('d-m-Y', strtotime($d->tgl_presensi)) . ",";
            }
            return redirect('/presensi/izin')->with(['error' => 'Sudah Melakukan Presensi Pada Tanggal ' . $blacklistdate . ' Tidak Bisa Melakukan Pengajuan']);
        } else if ($cekpengajuan->count() > 0) {
            return redirect('/presensi/izin')->with(['error' => 'Sudah Melakukan Pengajuan Pada Tanggal Tersebut , Tidak Bisa Melakukan Pengajuan']);
        } else {

            $simpan = DB::table('pengajuan_izin')->insert($data);

            if ($simpan) {
                return redirect('/presensi/izin')->with(['success' => 'Data Berhasil Disimpan']);
            } else {
                return redirect('/presensi/izin')->with(['error' => 'Data Gagal Disimpan']);
            }
        }
    }


    public function edit($kode_izin)
    {
        $dataizin = DB::table('pengajuan_izin')->where('kode_izin', $kode_izin)->first();
        $mastercuti = DB::table('master_cuti')->orderBY('kode_cuti')->get();
        return view('izincuti.edit', compact('mastercuti', 'dataizin'));
    }



    public function update($kode_izin, Request $request)
    {
        $tgl_izin_dari = $request->tgl_izin_dari;
        $tgl_izin_sampai = $request->tgl_izin_sampai;
        $keterangan = $request->keterangan;
        $kode_cuti = $request->kode_cuti;

        try {
            $data = [
                'tgl_izin_dari' => $tgl_izin_dari,
                'tgl_izin_sampai' => $tgl_izin_sampai,
                'kode_cuti' => $kode_cuti,
                'keterangan' => $keterangan
            ];

            DB::table('pengajuan_izin')->where('kode_izin', $kode_izin)->update($data);
            return redirect('/presensi/izin')->with(['success' => 'Data Berhasil Diupdate']);
        } catch (\Exception $e) {
            return redirect('/presensi/izin')->with(['error' => 'Data Gagal Diupdate']);
        }
    }
}