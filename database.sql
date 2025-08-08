-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th8 06, 2025 lúc 12:50 PM
-- Phiên bản máy phục vụ: 8.0.30
-- Phiên bản PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `tktshop`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bien_the_san_pham`
--

CREATE TABLE `bien_the_san_pham` (
  `id` int NOT NULL,
  `san_pham_id` int NOT NULL,
  `kich_co_id` int NOT NULL,
  `mau_sac_id` int NOT NULL,
  `ma_sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gia_ban` decimal(12,0) NOT NULL,
  `gia_so_sanh` decimal(12,0) DEFAULT NULL,
  `so_luong_ton_kho` int NOT NULL DEFAULT '0',
  `so_luong_da_ban` int DEFAULT '0',
  `nguong_canh_bao_het_hang` int DEFAULT '5',
  `hinh_anh_bien_the` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` enum('hoat_dong','het_hang','an') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `bien_the_san_pham`
--

INSERT INTO `bien_the_san_pham` (`id`, `san_pham_id`, `kich_co_id`, `mau_sac_id`, `ma_sku`, `gia_ban`, `gia_so_sanh`, `so_luong_ton_kho`, `so_luong_da_ban`, `nguong_canh_bao_het_hang`, `hinh_anh_bien_the`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(6, 2, 6, 1, 'ADIDAS001-40-BLACK', 3600000, NULL, 20, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(7, 2, 6, 4, 'ADIDAS001-40-BLUE', 3600000, NULL, 15, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(8, 2, 7, 1, 'ADIDAS001-41-BLACK', 3600000, NULL, 18, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(9, 2, 7, 4, 'ADIDAS001-41-BLUE', 3600000, NULL, 12, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(10, 3, 5, 1, 'CONVERSE001-39-BLACK', 1500000, NULL, 30, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(11, 3, 5, 2, 'CONVERSE001-39-WHITE', 1500000, NULL, 25, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(12, 3, 5, 3, 'CONVERSE001-39-RED', 1500000, NULL, 20, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(13, 3, 6, 1, 'CONVERSE001-40-BLACK', 1500000, NULL, 35, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(14, 3, 6, 2, 'CONVERSE001-40-WHITE', 1500000, NULL, 28, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(15, 4, 6, 1, 'VANS001-40-BLACK', 2200000, NULL, 22, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(16, 4, 6, 2, 'VANS001-40-WHITE', 2200000, NULL, 18, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(17, 4, 7, 1, 'VANS001-41-BLACK', 2200000, NULL, 20, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(18, 5, 6, 6, 'PUMA001-40-GRAY', 2300000, NULL, 15, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(19, 5, 6, 3, 'PUMA001-40-RED', 2300000, NULL, 12, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(20, 5, 7, 6, 'PUMA001-41-GRAY', 2300000, NULL, 18, 0, 5, NULL, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(21, 2, 1, 7, 'ADIDAS001-35-HONG', 3600000, NULL, 15, 0, 5, NULL, 'hoat_dong', '2025-08-01 15:12:27', '2025-08-01 15:12:27'),
(22, 2, 3, 2, 'ADIDAS001-37-TRANG', 3600000, NULL, 5, 0, 5, NULL, 'hoat_dong', '2025-08-01 15:40:06', '2025-08-01 15:40:06'),
(23, 3, 7, 2, 'CONVERSE001-41-', 1500000, NULL, 0, 0, 5, NULL, 'hoat_dong', '2025-08-01 15:55:59', '2025-08-01 15:55:59'),
(24, 2, 1, 2, 'ADIDAS001-35-', 3600000, NULL, 1, 0, 5, NULL, 'hoat_dong', '2025-08-01 16:07:14', '2025-08-01 16:07:14');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chi_tiet_don_hang`
--

CREATE TABLE `chi_tiet_don_hang` (
  `id` int NOT NULL,
  `don_hang_id` int NOT NULL,
  `san_pham_id` int NOT NULL,
  `bien_the_id` int NOT NULL,
  `ten_san_pham` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `thuong_hieu` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kich_co` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mau_sac` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hinh_anh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `so_luong` int NOT NULL,
  `gia_ban` decimal(12,0) NOT NULL,
  `thanh_tien` decimal(12,0) NOT NULL,
  `trang_thai` enum('binh_thuong','hoan_tra','huy') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'binh_thuong',
  `da_danh_gia` tinyint(1) DEFAULT '0',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danh_gia_san_pham`
--

CREATE TABLE `danh_gia_san_pham` (
  `id` int NOT NULL,
  `san_pham_id` int NOT NULL,
  `khach_hang_id` int NOT NULL,
  `don_hang_id` int DEFAULT NULL,
  `chi_tiet_don_hang_id` int DEFAULT NULL,
  `diem_danh_gia` tinyint NOT NULL,
  `tieu_de` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `noi_dung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `uu_diem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nhuoc_diem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hinh_anh_danh_gia` json DEFAULT NULL,
  `trang_thai` enum('cho_duyet','da_duyet','tu_choi','an') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'cho_duyet',
  `la_mua_hang_xac_thuc` tinyint(1) DEFAULT '0',
  `luot_thich` int DEFAULT '0',
  `luot_khong_thich` int DEFAULT '0',
  `ly_do_tu_choi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nguoi_duyet` int DEFAULT NULL,
  `ngay_duyet` datetime DEFAULT NULL,
  `phan_hoi_admin` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nguoi_phan_hoi` int DEFAULT NULL,
  `ngay_phan_hoi` datetime DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `danh_gia_san_pham`
--

INSERT INTO `danh_gia_san_pham` (`id`, `san_pham_id`, `khach_hang_id`, `don_hang_id`, `chi_tiet_don_hang_id`, `diem_danh_gia`, `tieu_de`, `noi_dung`, `uu_diem`, `nhuoc_diem`, `hinh_anh_danh_gia`, `trang_thai`, `la_mua_hang_xac_thuc`, `luot_thich`, `luot_khong_thich`, `ly_do_tu_choi`, `nguoi_duyet`, `ngay_duyet`, `phan_hoi_admin`, `nguoi_phan_hoi`, `ngay_phan_hoi`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 2, 12, NULL, NULL, 5, 'rất tốt', 'fdsfsdfdsfds', 'fsdsdfsd', 'ko có', NULL, 'cho_duyet', 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-03 15:14:38', '2025-08-03 15:14:38'),
(2, 2, 13, NULL, NULL, 5, 'ừdldsklfsd', 'fsdffd', 'fđsf', 'dsfsfd', NULL, 'cho_duyet', 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-04 10:56:42', '2025-08-04 10:56:42');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danh_muc_giay`
--

CREATE TABLE `danh_muc_giay` (
  `id` int NOT NULL,
  `ten_danh_muc` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hinh_anh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `danh_muc_cha_id` int DEFAULT NULL,
  `thu_tu_hien_thi` int DEFAULT '0',
  `trang_thai` enum('hoat_dong','an') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `danh_muc_giay`
--

INSERT INTO `danh_muc_giay` (`id`, `ten_danh_muc`, `slug`, `mo_ta`, `hinh_anh`, `danh_muc_cha_id`, `thu_tu_hien_thi`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 'Giày thể thao', 'giay-the-thao', 'Giày thể thao nam nữu', NULL, NULL, 1, 'hoat_dong', '2025-07-31 07:17:19', '2025-08-01 15:42:39'),
(4, 'Giày sandal', 'giay-sandal', 'Giày sandal hè', NULL, NULL, 4, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(5, 'Giày thể thao nam', 'giay-the-thao-nam', 'Giày thể thao dành cho nam', NULL, 1, 1, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(6, 'Giày thể thao nữ', 'giay-the-thao-nu', 'Giày thể thao dành cho nữ', NULL, 1, 2, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(7, 'Giày chạy bộ', 'giay-chay-bo', 'Giày chuyên dụng chạy bộ', NULL, 1, 3, 'hoat_dong', '2025-07-31 07:17:19', '2025-07-31 07:17:19'),
(8, 'Giày thể thao chính hãng', 'giay-the-thao-chinh-hang', 'dkaslsajdaskd', '', 1, 9, 'hoat_dong', '2025-08-01 15:43:15', '2025-08-01 15:43:15');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `don_hang`
--

CREATE TABLE `don_hang` (
  `id` int NOT NULL,
  `ma_don_hang` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `khach_hang_id` int DEFAULT NULL,
  `ho_ten_nhan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_dien_thoai_nhan` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_nhan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dia_chi_nhan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ghi_chu_khach_hang` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ghi_chu_admin` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tong_tien_hang` decimal(12,0) NOT NULL,
  `tien_giam_gia` decimal(12,0) DEFAULT '0',
  `phi_van_chuyen` decimal(12,0) DEFAULT '0',
  `tong_thanh_toan` decimal(12,0) NOT NULL,
  `phuong_thuc_thanh_toan` enum('vnpay','cod') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phuong_thuc_van_chuyen` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'giao_hang_nhanh',
  `trang_thai_don_hang` enum('cho_xac_nhan','da_xac_nhan','dang_chuan_bi','dang_giao','da_giao','da_huy','hoan_tra') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'cho_xac_nhan',
  `trang_thai_thanh_toan` enum('chua_thanh_toan','da_thanh_toan','cho_thanh_toan','that_bai','het_han','hoan_tien') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'chua_thanh_toan',
  `ngay_dat_hang` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_xac_nhan` datetime DEFAULT NULL,
  `ngay_giao_hang` datetime DEFAULT NULL,
  `ngay_hoan_thanh` datetime DEFAULT NULL,
  `ngay_huy` datetime DEFAULT NULL,
  `ngay_thanh_toan` datetime DEFAULT NULL,
  `han_thanh_toan` datetime DEFAULT NULL,
  `ly_do_huy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nguoi_xu_ly` int DEFAULT NULL,
  `ma_van_don` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `gio_hang`
--

CREATE TABLE `gio_hang` (
  `id` int NOT NULL,
  `khach_hang_id` int DEFAULT NULL,
  `session_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bien_the_id` int NOT NULL,
  `so_luong` int NOT NULL DEFAULT '1',
  `gia_tai_thoi_diem` decimal(12,0) NOT NULL,
  `ghi_chu` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_them` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `gio_hang`
--

INSERT INTO `gio_hang` (`id`, `khach_hang_id`, `session_id`, `bien_the_id`, `so_luong`, `gia_tai_thoi_diem`, `ghi_chu`, `ngay_them`, `ngay_cap_nhat`) VALUES
(7, 12, NULL, 14, 1, 1500000, NULL, '2025-08-03 14:27:35', '2025-08-03 14:27:35'),
(8, 12, NULL, 16, 1, 2200000, NULL, '2025-08-03 14:29:59', '2025-08-03 14:29:59'),
(11, 14, NULL, 14, 1, 1500000, NULL, '2025-08-06 12:05:50', '2025-08-06 12:05:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `kich_co`
--

CREATE TABLE `kich_co` (
  `id` int NOT NULL,
  `kich_co` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thu_tu_sap_xep` int DEFAULT '0',
  `trang_thai` enum('hoat_dong','an') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `kich_co`
--

INSERT INTO `kich_co` (`id`, `kich_co`, `mo_ta`, `thu_tu_sap_xep`, `trang_thai`) VALUES
(1, '35', 'Size 35', 35, 'hoat_dong'),
(3, '37', 'Size 37', 37, 'hoat_dong'),
(5, '39', 'Size50', 39, 'hoat_dong'),
(6, '40', 'Size 40', 40, 'hoat_dong'),
(7, '41', 'Size 41', 41, 'hoat_dong'),
(9, '43', 'Size 43', 43, 'hoat_dong'),
(10, '44', 'Size 44', 44, 'hoat_dong'),
(12, '39.5', 'Size 39.5', 395, 'hoat_dong'),
(13, '47', 'Size 42', 420, 'hoat_dong');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `log_hoat_dong_admin`
--

CREATE TABLE `log_hoat_dong_admin` (
  `id` int NOT NULL,
  `admin_id` int NOT NULL,
  `hanh_dong` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ban_ghi_id` int DEFAULT NULL,
  `mo_ta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phuong_thuc_http` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_request` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `du_lieu_cu` json DEFAULT NULL,
  `du_lieu_moi` json DEFAULT NULL,
  `muc_do_nghiem_trong` enum('thap','trung_binh','cao','nghiem_trong') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'trung_binh',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `mau_sac`
--

CREATE TABLE `mau_sac` (
  `id` int NOT NULL,
  `ten_mau` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_mau` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hinh_anh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thu_tu_hien_thi` int DEFAULT '0',
  `trang_thai` enum('hoat_dong','an') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `mau_sac`
--

INSERT INTO `mau_sac` (`id`, `ten_mau`, `ma_mau`, `hinh_anh`, `thu_tu_hien_thi`, `trang_thai`) VALUES
(1, 'Đen', '#000000', NULL, 1, 'hoat_dong'),
(2, 'Trắng', '#FFFFFF', NULL, 2, 'hoat_dong'),
(3, 'Đỏ', '#FF0000', NULL, 3, 'hoat_dong'),
(4, 'Xanh dương', '#0000FF', NULL, 4, 'hoat_dong'),
(5, 'Nâu', '#8B4513', NULL, 5, 'hoat_dong'),
(6, 'Xám', '#808080', NULL, 6, 'hoat_dong'),
(7, 'Hồng', '#FFC0CB', NULL, 7, 'hoat_dong'),
(8, 'Vàng', '#FFFF00', NULL, 8, 'hoat_dong'),
(9, 'Xanh lá', '#008000', NULL, 9, 'hoat_dong'),
(10, 'Tím', '#800080', NULL, 10, 'hoat_dong'),
(11, 'Xanh nhạt', '#ADD8E6', NULL, 110, 'hoat_dong'),
(12, 'đỏ đậm', '#9E3333', NULL, 111, 'hoat_dong'),
(13, 'cam', '#CE8027', NULL, 112, 'hoat_dong');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `id` int NOT NULL,
  `ten_dang_nhap` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mat_khau` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ho_ten` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_dien_thoai` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dia_chi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ngay_sinh` date DEFAULT NULL,
  `gioi_tinh` enum('nam','nu','khac') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'khac',
  `vai_tro` enum('khach_hang','admin','nhan_vien') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'khach_hang',
  `trang_thai` enum('hoat_dong','bi_khoa','chua_kich_hoat') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `nguoi_dung`
--

INSERT INTO `nguoi_dung` (`id`, `ten_dang_nhap`, `mat_khau`, `ho_ten`, `email`, `so_dien_thoai`, `dia_chi`, `ngay_sinh`, `gioi_tinh`, `vai_tro`, `trang_thai`, `avatar`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(1, 'admin', 'admin123', 'TKT Shop Administrator', 'admin@tktshop.com', NULL, NULL, NULL, 'khac', 'admin', 'hoat_dong', NULL, '2025-07-31 07:17:19', '2025-08-01 15:57:21'),
(5, 'tet123_1754009271', '$2y$10$SWkYrwY1vbSsCEBC6/VcdOUmN7cnLf719LbvjWnMbm0PXcWZC.Ooq', 'ngoquangtruong', 'tet123@gmail.com', '0866792996', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-01 00:47:51', '2025-08-01 00:47:51'),
(8, 'kien123124', 't123456', 'do van kien', 'admin@tkt.shop', '', '', '2004-02-07', 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-01 15:23:22', '2025-08-01 15:23:22'),
(9, 'ngoquangtruong_1754064998', '$2y$10$VhcwWrC/cotmM/7YCFp6fOO0L3kP90HOsz0NLGwqKmPFSiQIdqnbO', 'ngoquangtruong', 'ngoquangtruong@gmail.com', '0866792996', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-01 16:16:38', '2025-08-01 16:16:38'),
(10, 'dvk_1754116608', '$2y$10$xlt1UjshG0OHBb26WV2OjO4Or3GWjWIBifHS.NrJr0b0LFmlECXsC', 'do van kien12', 'dvk@gmail.comm', '08667929963', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-02 06:36:48', '2025-08-02 06:36:48'),
(11, 'dv1k_1754116680', '$2y$10$G21yRdvg7a7q.cJiq82aNOrJZ42aSIuZ5UB2bRoIvd.nCijXSoByi', 'do van kien1', 'dv1k@gmail.comm', '0866792996', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-02 06:38:00', '2025-08-02 06:38:00'),
(12, 'tet1243_1754228461', '$2y$10$t4vE6RJlL4tU94ESOX3lW.CilF9.pIl6wAygm10UWW0QZ13TwDWNS', 'ngoquangtruongg', 'tet1243@gmail.com', '0866792996', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-03 13:41:01', '2025-08-03 13:41:01'),
(13, 'ngoquangtruonggg_1754304810', '$2y$10$aBLxKXH.bdihNvstpPcNGObr5TU4zR47N0wOg/uXnvo4nDkyFK4Wu', 'ngoquangtruonggg', 'ngoquangtruonggg@gmail.com', '0866763212', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-04 10:53:30', '2025-08-04 10:53:30'),
(14, 'dovankien072211_1754481935', '$2y$10$588F/EcHzy9qqDYfMN9OnOhp4KlVSVDgOxpWmhWNvGMrQtF6UkOTK', 'do van kien', 'dovankien072211@gmail.com', '0866792996', NULL, NULL, 'khac', 'khach_hang', 'hoat_dong', NULL, '2025-08-06 12:05:35', '2025-08-06 12:05:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `san_pham_chinh`
--

CREATE TABLE `san_pham_chinh` (
  `id` int NOT NULL,
  `ten_san_pham` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_san_pham` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mo_ta_ngan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mo_ta_chi_tiet` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `danh_muc_id` int NOT NULL,
  `thuong_hieu` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hinh_anh_chinh` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album_hinh_anh` json DEFAULT NULL,
  `gia_goc` decimal(12,0) NOT NULL,
  `gia_khuyen_mai` decimal(12,0) DEFAULT NULL,
  `ngay_bat_dau_km` datetime DEFAULT NULL,
  `ngay_ket_thuc_km` datetime DEFAULT NULL,
  `san_pham_noi_bat` tinyint(1) DEFAULT '0',
  `san_pham_moi` tinyint(1) DEFAULT '0',
  `san_pham_ban_chay` tinyint(1) DEFAULT '0',
  `luot_xem` int DEFAULT '0',
  `so_luong_ban` int DEFAULT '0',
  `diem_danh_gia_tb` decimal(3,2) DEFAULT '0.00',
  `so_luong_danh_gia` int DEFAULT '0',
  `meta_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `trang_thai` enum('hoat_dong','het_hang','an') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `nguoi_tao` int DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `san_pham_chinh`
--

INSERT INTO `san_pham_chinh` (`id`, `ten_san_pham`, `slug`, `ma_san_pham`, `mo_ta_ngan`, `mo_ta_chi_tiet`, `danh_muc_id`, `thuong_hieu`, `hinh_anh_chinh`, `album_hinh_anh`, `gia_goc`, `gia_khuyen_mai`, `ngay_bat_dau_km`, `ngay_ket_thuc_km`, `san_pham_noi_bat`, `san_pham_moi`, `san_pham_ban_chay`, `luot_xem`, `so_luong_ban`, `diem_danh_gia_tb`, `so_luong_danh_gia`, `meta_title`, `meta_description`, `tags`, `trang_thai`, `nguoi_tao`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(2, 'Adidas Ultraboost 22', 'adidas-ultraboost-22', 'ADIDAS001', 'Giày chạy bộ Adidas Ultraboost', 'Adidas Ultraboost 22 với công nghệ Boost mang lại năng lượng phản hồi tuyệt vời. Đế giày có độ bám cao, phù hợp cho việc chạy bộ và tập luyện.', 5, 'Adidas', NULL, NULL, 4200000, 3600000, NULL, NULL, 1, 1, 1, 11, 0, NULL, 0, '', '', NULL, 'hoat_dong', 1, '2025-07-31 07:17:19', '2025-08-04 10:56:42'),
(3, 'Converse Chuck Taylor All Star', 'converse-chuck-taylor', 'CONVERSE001', 'Giày cổ điển Converse Chuck Taylor', 'Converse Chuck Taylor All Star - biểu tượng thời trang đường phố với thiết kế cổ điển, không bao giờ lỗi thời. Chất liệu canvas bền bỉ.', 1, 'Converse', NULL, NULL, 1800000, 1500000, NULL, NULL, 1, 0, 1, 13, 0, 0.00, 0, '', '', NULL, 'hoat_dong', 1, '2025-07-31 07:17:19', '2025-08-06 12:05:50'),
(4, 'Vans Old Skool', 'vans-old-skool', 'VANS001', 'Giày skateboard Vans Old Skool', 'Vans Old Skool với thiết kế side stripe đặc trưng. Giày skateboard chính hãng với đế cao su bền chắc, grip tốt.', 1, 'Vans', NULL, NULL, 2200000, NULL, NULL, NULL, 1, 0, 0, 3, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1, '2025-07-31 07:17:19', '2025-08-03 14:29:59'),
(5, 'Puma RS-X', 'puma-rs-x', 'PUMA001', 'Giày thể thao Puma RS-X', 'Puma RS-X với thiết kế chunky sneaker đầy cá tính. Phối màu bắt mắt, phù hợp với phong cách streetwear hiện đại.', 1, 'Puma', NULL, NULL, 2800000, 2300000, NULL, NULL, 0, 1, 0, 0, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1, '2025-07-31 07:17:19', '2025-07-31 07:17:19');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thanh_toan_vnpay`
--

CREATE TABLE `thanh_toan_vnpay` (
  `id` int NOT NULL,
  `don_hang_id` int NOT NULL,
  `vnp_txn_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vnp_transaction_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_amount` bigint NOT NULL,
  `vnp_order_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vnp_response_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_transaction_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_pay_date` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_bank_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_card_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_bank_tran_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_secure_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` enum('khoi_tao','cho_thanh_toan','thanh_cong','that_bai','huy','het_han') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'khoi_tao',
  `du_lieu_request` json DEFAULT NULL,
  `du_lieu_response` json DEFAULT NULL,
  `du_lieu_ipn` json DEFAULT NULL,
  `ma_qr` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_thanh_toan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `thoi_gian_het_han_qr` datetime DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_thanh_toan` datetime DEFAULT NULL,
  `ngay_het_han` datetime DEFAULT NULL,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `bien_the_san_pham`
--
ALTER TABLE `bien_the_san_pham`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_sku` (`ma_sku`),
  ADD UNIQUE KEY `unique_bien_the` (`san_pham_id`,`kich_co_id`,`mau_sac_id`),
  ADD KEY `kich_co_id` (`kich_co_id`),
  ADD KEY `mau_sac_id` (`mau_sac_id`);

--
-- Chỉ mục cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `don_hang_id` (`don_hang_id`),
  ADD KEY `san_pham_id` (`san_pham_id`),
  ADD KEY `bien_the_id` (`bien_the_id`);

--
-- Chỉ mục cho bảng `danh_gia_san_pham`
--
ALTER TABLE `danh_gia_san_pham`
  ADD PRIMARY KEY (`id`),
  ADD KEY `san_pham_id` (`san_pham_id`),
  ADD KEY `khach_hang_id` (`khach_hang_id`),
  ADD KEY `don_hang_id` (`don_hang_id`),
  ADD KEY `chi_tiet_don_hang_id` (`chi_tiet_don_hang_id`),
  ADD KEY `nguoi_duyet` (`nguoi_duyet`),
  ADD KEY `nguoi_phan_hoi` (`nguoi_phan_hoi`);

--
-- Chỉ mục cho bảng `danh_muc_giay`
--
ALTER TABLE `danh_muc_giay`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_danh_muc_cha` (`danh_muc_cha_id`);

--
-- Chỉ mục cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_don_hang` (`ma_don_hang`),
  ADD KEY `khach_hang_id` (`khach_hang_id`),
  ADD KEY `nguoi_xu_ly` (`nguoi_xu_ly`);

--
-- Chỉ mục cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `khach_hang_id` (`khach_hang_id`),
  ADD KEY `bien_the_id` (`bien_the_id`);

--
-- Chỉ mục cho bảng `kich_co`
--
ALTER TABLE `kich_co`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kich_co` (`kich_co`);

--
-- Chỉ mục cho bảng `log_hoat_dong_admin`
--
ALTER TABLE `log_hoat_dong_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Chỉ mục cho bảng `mau_sac`
--
ALTER TABLE `mau_sac`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `san_pham_chinh`
--
ALTER TABLE `san_pham_chinh`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `ma_san_pham` (`ma_san_pham`),
  ADD KEY `danh_muc_id` (`danh_muc_id`),
  ADD KEY `nguoi_tao` (`nguoi_tao`);

--
-- Chỉ mục cho bảng `thanh_toan_vnpay`
--
ALTER TABLE `thanh_toan_vnpay`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_txn_ref` (`vnp_txn_ref`),
  ADD KEY `don_hang_id` (`don_hang_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `bien_the_san_pham`
--
ALTER TABLE `bien_the_san_pham`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `danh_gia_san_pham`
--
ALTER TABLE `danh_gia_san_pham`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `danh_muc_giay`
--
ALTER TABLE `danh_muc_giay`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `kich_co`
--
ALTER TABLE `kich_co`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `log_hoat_dong_admin`
--
ALTER TABLE `log_hoat_dong_admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `mau_sac`
--
ALTER TABLE `mau_sac`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `san_pham_chinh`
--
ALTER TABLE `san_pham_chinh`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `thanh_toan_vnpay`
--
ALTER TABLE `thanh_toan_vnpay`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `bien_the_san_pham`
--
ALTER TABLE `bien_the_san_pham`
  ADD CONSTRAINT `bien_the_san_pham_ibfk_1` FOREIGN KEY (`san_pham_id`) REFERENCES `san_pham_chinh` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bien_the_san_pham_ibfk_2` FOREIGN KEY (`kich_co_id`) REFERENCES `kich_co` (`id`),
  ADD CONSTRAINT `bien_the_san_pham_ibfk_3` FOREIGN KEY (`mau_sac_id`) REFERENCES `mau_sac` (`id`);

--
-- Các ràng buộc cho bảng `chi_tiet_don_hang`
--
ALTER TABLE `chi_tiet_don_hang`
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_1` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_2` FOREIGN KEY (`san_pham_id`) REFERENCES `san_pham_chinh` (`id`),
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_3` FOREIGN KEY (`bien_the_id`) REFERENCES `bien_the_san_pham` (`id`);

--
-- Các ràng buộc cho bảng `danh_gia_san_pham`
--
ALTER TABLE `danh_gia_san_pham`
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_1` FOREIGN KEY (`san_pham_id`) REFERENCES `san_pham_chinh` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_2` FOREIGN KEY (`khach_hang_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_3` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_4` FOREIGN KEY (`chi_tiet_don_hang_id`) REFERENCES `chi_tiet_don_hang` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_5` FOREIGN KEY (`nguoi_duyet`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_6` FOREIGN KEY (`nguoi_phan_hoi`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `danh_muc_giay`
--
ALTER TABLE `danh_muc_giay`
  ADD CONSTRAINT `danh_muc_giay_ibfk_1` FOREIGN KEY (`danh_muc_cha_id`) REFERENCES `danh_muc_giay` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `don_hang`
--
ALTER TABLE `don_hang`
  ADD CONSTRAINT `don_hang_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `don_hang_ibfk_2` FOREIGN KEY (`nguoi_xu_ly`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `gio_hang`
--
ALTER TABLE `gio_hang`
  ADD CONSTRAINT `gio_hang_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gio_hang_ibfk_2` FOREIGN KEY (`bien_the_id`) REFERENCES `bien_the_san_pham` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `log_hoat_dong_admin`
--
ALTER TABLE `log_hoat_dong_admin`
  ADD CONSTRAINT `log_hoat_dong_admin_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `san_pham_chinh`
--
ALTER TABLE `san_pham_chinh`
  ADD CONSTRAINT `san_pham_chinh_ibfk_1` FOREIGN KEY (`danh_muc_id`) REFERENCES `danh_muc_giay` (`id`),
  ADD CONSTRAINT `san_pham_chinh_ibfk_2` FOREIGN KEY (`nguoi_tao`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `thanh_toan_vnpay`
--
ALTER TABLE `thanh_toan_vnpay`
  ADD CONSTRAINT `thanh_toan_vnpay_ibfk_1` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE CASCADE;
COMMIT;

--
-- Stored Procedures
--
DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `TaoMaDonHang` ()   BEGIN
    DECLARE new_ma_don_hang VARCHAR(20);
    DECLARE is_unique BOOLEAN DEFAULT FALSE;

    WHILE NOT is_unique DO
        -- Tạo mã đơn hàng mới: TKT + YYYYMMDD + 4 số ngẫu nhiên
        SET new_ma_don_hang = CONCAT('TKT', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
        
        -- Kiểm tra xem mã đã tồn tại chưa
        IF (SELECT COUNT(*) FROM `don_hang` WHERE `ma_don_hang` = new_ma_don_hang) = 0 THEN
            SET is_unique = TRUE;
        END IF;
    END WHILE;

    -- Trả về mã đơn hàng duy nhất
    SELECT new_ma_don_hang AS ma_don_hang;
END$$
DELIMITER ;

--
-- Views
--
DROP VIEW IF EXISTS `view_don_hang_tong_quan`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_don_hang_tong_quan`  AS SELECT `dh`.`id` AS `id`, `dh`.`ma_don_hang` AS `ma_don_hang`, `dh`.`khach_hang_id` AS `khach_hang_id`, `dh`.`ho_ten_nhan` AS `ho_ten_nhan`, `dh`.`so_dien_thoai_nhan` AS `so_dien_thoai_nhan`, `dh`.`email_nhan` AS `email_nhan`, `dh`.`dia_chi_nhan` AS `dia_chi_nhan`, `dh`.`ghi_chu_khach_hang` AS `ghi_chu_khach_hang`, `dh`.`ghi_chu_admin` AS `ghi_chu_admin`, `dh`.`tong_tien_hang` AS `tong_tien_hang`, `dh`.`tien_giam_gia` AS `tien_giam_gia`, `dh`.`phi_van_chuyen` AS `phi_van_chuyen`, `dh`.`tong_thanh_toan` AS `tong_thanh_toan`, `dh`.`phuong_thuc_thanh_toan` AS `phuong_thuc_thanh_toan`, `dh`.`phuong_thuc_van_chuyen` AS `phuong_thuc_van_chuyen`, `dh`.`trang_thai_don_hang` AS `trang_thai_don_hang`, `dh`.`trang_thai_thanh_toan` AS `trang_thai_thanh_toan`, `dh`.`ngay_dat_hang` AS `ngay_dat_hang`, `dh`.`ngay_xac_nhan` AS `ngay_xac_nhan`, `dh`.`ngay_giao_hang` AS `ngay_giao_hang`, `dh`.`ngay_hoan_thanh` AS `ngay_hoan_thanh`, `dh`.`ngay_huy` AS `ngay_huy`, `dh`.`ngay_thanh_toan` AS `ngay_thanh_toan`, `dh`.`han_thanh_toan` AS `han_thanh_toan`, `dh`.`ly_do_huy` AS `ly_do_huy`, `dh`.`nguoi_xu_ly` AS `nguoi_xu_ly`, `dh`.`ma_van_don` AS `ma_van_don`, `dh`.`ngay_cap_nhat` AS `ngay_cap_nhat`, `nd`.`ho_ten` AS `ten_khach_hang`, `nd`.`email` AS `email_khach_hang`, count(distinct `ct`.`id`) AS `so_san_pham`, sum(`ct`.`so_luong`) AS `tong_so_luong`, `vnp`.`vnp_transaction_no` AS `vnp_transaction_no`, `vnp`.`trang_thai` AS `trang_thai_vnpay` FROM (((`don_hang` `dh` left join `nguoi_dung` `nd` on((`dh`.`khach_hang_id` = `nd`.`id`))) left join `chi_tiet_don_hang` `ct` on((`dh`.`id` = `ct`.`don_hang_id`))) left join `thanh_toan_vnpay` `vnp` on((`dh`.`id` = `vnp`.`don_hang_id`))) GROUP BY `dh`.`id` ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
