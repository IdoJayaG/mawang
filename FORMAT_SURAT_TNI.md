# Format Surat Tugas TNI - Sesuai Standar Resmi

## Overview
Sistem surat tugas RANDIS telah diperbaiki dan disesuaikan dengan format resmi TNI berdasarkan contoh surat yang diberikan. Format ini mengikuti struktur surat dinas TNI yang standar.

## Format PDF yang Diimplementasikan

### 1. Header Resmi TNI
```
KEMENTERIAN PERTAHANAN REPUBLIK INDONESIA
SPBT KEMHAN CAWANG
Jalan Medan Merdeka Barat No. 13-14, Jakarta Pusat 10110
```

### 2. Kop Surat (Sesuai Standar TNI)
- **Nomor**: Format standar dengan nomor urut/bulan/tahun
- **Klasifikasi**: Biasa (default untuk surat tugas)
- **Lampiran**: - (kosong jika tidak ada)
- **Perihal**: Permohonan peminjaman kendaraan dinas bus dan tenaga medis
- **Kepada**: Yth. Dandenma Mabes TNI di Jakarta
- **Tanggal**: Format Indonesia (DD MMMM YYYY)

### 3. Isi Surat Formal
Mengikuti struktur surat dinas TNI:
1. **Dasar** - Referensi peraturan dan perintah
2. **Perlengkapan** - Maksud surat
3. **Permohonan** - Detail kebutuhan dengan sub-poin:
   - a. hari, tanggal
   - b. waktu 
   - c. berangkat dari
   - d. tujuan
4. **Penutup** - "Demikian mohon dimaklumi"

### 4. Tanda Tangan Resmi
- Format: "a.n Kepala SPBT Kemhan Cawang"
- Jabatan: "Waka," (Wakil Kepala)
- Nama: Sesuai data atau default
- Pangkat: Kolonel Laut (E) NRP 13475/P

### 5. Tembusan
Daftar pejabat yang menerima tembusan:
1. Kepala SPBT Kemhan Cawang
2. Asops Denma Mabes TNI  
3. Dansetang Denma Mabes TNI
4. Dansakdok Denma Mabes TNI

## Fitur Teknis PDF

### Styling CSS Print-Ready
```css
@page { 
    size: A4; 
    margin: 2.5cm 2cm 2cm 2cm;
}
@media print {
    .no-print { display: none !important; }
    body { font-family: 'Times New Roman', serif; font-size: 12pt; }
}
```

### Font dan Layout
- **Font**: Times New Roman (standar dokumen resmi)
- **Size**: 12pt untuk isi, 14pt untuk header
- **Line Height**: 1.4 untuk readability
- **Margin**: Disesuaikan untuk format A4

### Format Tanggal Indonesia
- Implementasi konversi bulan ke bahasa Indonesia
- Format: "21 Agustus 2025" 
- Hari dalam bahasa Indonesia: "Senin, 21 Agustus 2025"

## Halaman Kedua - Detail Perjalanan
Ditambahkan halaman kedua dengan:
- **Border box** untuk detail perjalanan
- **Table format** untuk informasi kendaraan
- **Status dan laporan** (jika sudah selesai)
- **Layout terpisah** dengan page-break

## Cara Penggunaan

### 1. Akses Preview
- Klik tombol 👁️ (eye) di daftar surat tugas
- Akan tampil preview format resmi TNI

### 2. Download PDF
- Dari preview, klik "Download PDF"
- Browser membuka tab baru dengan format print-ready
- Gunakan Ctrl+P untuk print atau save as PDF

### 3. Print Langsung
- Tombol "Print/Save as PDF" di bagian atas
- Format otomatis disesuaikan untuk kertas A4
- Menu browser disembunyikan saat print

## Kepatuhan Standar TNI

### ✅ Format Header
- Logo dan nama institusi sesuai hierarki TNI
- Alamat lengkap markas besar

### ✅ Struktur Surat Dinas
- Nomor, klasifikasi, lampiran, perihal
- Format "Yang bertanda tangan di bawah ini"
- Struktur dasar-isi-penutup

### ✅ Bahasa Formal
- Penggunaan bahasa Indonesia baku
- Terminologi militer yang tepat
- Format tanggal dan waktu standar

### ✅ Tanda Tangan
- Format "a.n" (atas nama) 
- Jabatan dan pangkat sesuai hierarki
- Ruang untuk tanda tangan fisik

### ✅ Tembusan
- Daftar pejabat sesuai struktur organisasi
- Urutan berdasarkan hierarki jabatan

## Validasi Format

### Checklist Kesesuaian:
- [x] Header resmi TNI
- [x] Nomor surat sesuai format
- [x] Struktur isi formal
- [x] Bahasa Indonesia baku
- [x] Format tanggal Indonesia
- [x] Tanda tangan dengan jabatan
- [x] Tembusan sesuai hierarki
- [x] Layout print-ready A4
- [x] Font Times New Roman
- [x] Margin dan spacing standar

## Customization Options

### Data Variable
- **Pejabat TTD**: Dapat disesuaikan per surat
- **Tujuan & Keperluan**: Input dinamis
- **Kendaraan**: Auto-populate dari database
- **Petugas**: Linked ke data pengguna

### Template Flexibility
- Format dasar tetap (sesuai standar TNI)
- Detail perjalanan dapat disesuaikan
- Status tracking untuk laporan

## Technical Implementation

### Database Integration
- Auto-populate dari tabel surat_tugas
- JOIN dengan kendaraan dan pengguna
- Foreign key relationships

### Error Handling
- Fallback untuk data kosong
- Default values untuk field opsional
- Validation sebelum generate PDF

### Browser Compatibility
- Chrome/Edge: Full support
- Firefox: Compatible  
- Safari: Compatible
- Mobile: Responsive layout

---
**Standar**: Mengikuti format surat dinas TNI
**Validasi**: Sesuai contoh yang diberikan
**Status**: Production Ready ✅
