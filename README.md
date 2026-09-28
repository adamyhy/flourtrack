# FlourTrack - Production House Inventory System

Production-ready web application for high-turnover bakery production house supplying wholesale pastries to retail outlets (e.g. Insyira Oleh-oleh).

## Features
- **Zero-Friction Kitchen Action**: Numpad sentuh 1-tap & kamera instan capture bukti waste.
- **Material Auto-Deduction & Reconciliation Engine**: Otomatis potong stok adonan bahan baku berdasarkan resep BOM. Toleransi selisih >3% otomatis flag sebagai `DISPUTED`.
- **FEFO Dispatch & Surat Jalan**: Pengeluaran barang jadi berdasarkan kadaluarsa terdekat (First Expired, First Out).
- **Audit Margin Owner**: Revenue, Cost HPP Bahan, Spoilage Loss, Net Margin Kotor.
- **PWA Ready**: Mobile-first responsive UI dengan PWA manifest & icon.
- **Strict 2 Roles**: `OWNER` & `ADMIN`.

## Credentials Demo
- **Owner**: `owner@flourtrack.local` / `FlourTrack2026!`
- **Admin Dapur**: `admin@flourtrack.local` / `DapurTrack2026!`

## Infrastructure
- URL: http://flourtrack.sucicookies.my.id
- Database: MySQL (`sql_flourtrack`)
