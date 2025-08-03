-- =====================================
-- KIỂM TRA VÀ SỬA LỖI SQL (SIMPLE PASSWORD)
-- File: database_simple.sql
-- =====================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Tạo database nếu chưa có
CREATE DATABASE IF NOT EXISTS `tktshop` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tktshop`;

-- =====================================
-- 1. TẠO CÁC BẢNG CƠ BẢN TRƯỚC
-- =====================================

-- Bảng nguoi_dung (cần tạo trước vì các bảng khác tham chiếu)
CREATE TABLE `nguoi_dung` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ten_dang_nhap` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mat_khau` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ho_ten` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_dien_thoai` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dia_chi` text COLLATE utf8mb4_unicode_ci,
  `ngay_sinh` date DEFAULT NULL,
  `gioi_tinh` enum('nam','nu','khac') COLLATE utf8mb4_unicode_ci DEFAULT 'khac',
  `vai_tro` enum('khach_hang','admin','nhan_vien') COLLATE utf8mb4_unicode_ci DEFAULT 'khach_hang',
  `trang_thai` enum('hoat_dong','bi_khoa','chua_kich_hoat') COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng danh_muc_giay
CREATE TABLE `danh_muc_giay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ten_danh_muc` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci,
  `hinh_anh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `danh_muc_cha_id` int DEFAULT NULL,
  `thu_tu_hien_thi` int DEFAULT '0',
  `trang_thai` enum('hoat_dong','an') COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_danh_muc_cha` (`danh_muc_cha_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng kich_co
CREATE TABLE `kich_co` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kich_co` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thu_tu_sap_xep` int DEFAULT '0',
  `trang_thai` enum('hoat_dong','an') COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kich_co` (`kich_co`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng mau_sac
CREATE TABLE `mau_sac` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ten_mau` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_mau` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hinh_anh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thu_tu_hien_thi` int DEFAULT '0',
  `trang_thai` enum('hoat_dong','an') COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng san_pham_chinh
CREATE TABLE `san_pham_chinh` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ten_san_pham` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_san_pham` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mo_ta_ngan` text COLLATE utf8mb4_unicode_ci,
  `mo_ta_chi_tiet` longtext COLLATE utf8mb4_unicode_ci,
  `danh_muc_id` int NOT NULL,
  `thuong_hieu` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hinh_anh_chinh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `meta_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `trang_thai` enum('hoat_dong','het_hang','an') COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `nguoi_tao` int DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `ma_san_pham` (`ma_san_pham`),
  KEY `danh_muc_id` (`danh_muc_id`),
  KEY `nguoi_tao` (`nguoi_tao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng bien_the_san_pham
CREATE TABLE `bien_the_san_pham` (
  `id` int NOT NULL AUTO_INCREMENT,
  `san_pham_id` int NOT NULL,
  `kich_co_id` int NOT NULL,
  `mau_sac_id` int NOT NULL,
  `ma_sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gia_ban` decimal(12,0) NOT NULL,
  `gia_so_sanh` decimal(12,0) DEFAULT NULL,
  `so_luong_ton_kho` int NOT NULL DEFAULT '0',
  `so_luong_da_ban` int DEFAULT '0',
  `nguong_canh_bao_het_hang` int DEFAULT '5',
  `hinh_anh_bien_the` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` enum('hoat_dong','het_hang','an') COLLATE utf8mb4_unicode_ci DEFAULT 'hoat_dong',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_sku` (`ma_sku`),
  UNIQUE KEY `unique_bien_the` (`san_pham_id`,`kich_co_id`,`mau_sac_id`),
  KEY `kich_co_id` (`kich_co_id`),
  KEY `mau_sac_id` (`mau_sac_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng don_hang
CREATE TABLE `don_hang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ma_don_hang` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `khach_hang_id` int DEFAULT NULL,
  `ho_ten_nhan` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_dien_thoai_nhan` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_nhan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dia_chi_nhan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ghi_chu_khach_hang` text COLLATE utf8mb4_unicode_ci,
  `ghi_chu_admin` text COLLATE utf8mb4_unicode_ci,
  `tong_tien_hang` decimal(12,0) NOT NULL,
  `tien_giam_gia` decimal(12,0) DEFAULT '0',
  `phi_van_chuyen` decimal(12,0) DEFAULT '0',
  `tong_thanh_toan` decimal(12,0) NOT NULL,
  `phuong_thuc_thanh_toan` enum('vnpay','cod') COLLATE utf8mb4_unicode_ci NOT NULL,
  `phuong_thuc_van_chuyen` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'giao_hang_nhanh',
  `trang_thai_don_hang` enum('cho_xac_nhan','da_xac_nhan','dang_chuan_bi','dang_giao','da_giao','da_huy','hoan_tra') COLLATE utf8mb4_unicode_ci DEFAULT 'cho_xac_nhan',
  `trang_thai_thanh_toan` enum('chua_thanh_toan','da_thanh_toan','cho_thanh_toan','that_bai','het_han','hoan_tien') COLLATE utf8mb4_unicode_ci DEFAULT 'chua_thanh_toan',
  `ngay_dat_hang` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_xac_nhan` datetime DEFAULT NULL,
  `ngay_giao_hang` datetime DEFAULT NULL,
  `ngay_hoan_thanh` datetime DEFAULT NULL,
  `ngay_huy` datetime DEFAULT NULL,
  `han_thanh_toan` datetime DEFAULT NULL,
  `ly_do_huy` text COLLATE utf8mb4_unicode_ci,
  `nguoi_xu_ly` int DEFAULT NULL,
  `ma_van_don` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_don_hang` (`ma_don_hang`),
  KEY `khach_hang_id` (`khach_hang_id`),
  KEY `nguoi_xu_ly` (`nguoi_xu_ly`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng chi_tiet_don_hang
CREATE TABLE `chi_tiet_don_hang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `don_hang_id` int NOT NULL,
  `san_pham_id` int NOT NULL,
  `bien_the_id` int NOT NULL,
  `ten_san_pham` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thuong_hieu` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kich_co` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mau_sac` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ma_sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hinh_anh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `so_luong` int NOT NULL,
  `gia_ban` decimal(12,0) NOT NULL,
  `thanh_tien` decimal(12,0) NOT NULL,
  `trang_thai` enum('binh_thuong','hoan_tra','huy') COLLATE utf8mb4_unicode_ci DEFAULT 'binh_thuong',
  `da_danh_gia` tinyint(1) DEFAULT '0',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `don_hang_id` (`don_hang_id`),
  KEY `san_pham_id` (`san_pham_id`),
  KEY `bien_the_id` (`bien_the_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng gio_hang
CREATE TABLE `gio_hang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `khach_hang_id` int DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bien_the_id` int NOT NULL,
  `so_luong` int NOT NULL DEFAULT '1',
  `gia_tai_thoi_diem` decimal(12,0) NOT NULL,
  `ghi_chu` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_them` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `khach_hang_id` (`khach_hang_id`),
  KEY `bien_the_id` (`bien_the_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng danh_gia_san_pham
CREATE TABLE `danh_gia_san_pham` (
  `id` int NOT NULL AUTO_INCREMENT,
  `san_pham_id` int NOT NULL,
  `khach_hang_id` int NOT NULL,
  `don_hang_id` int DEFAULT NULL,
  `chi_tiet_don_hang_id` int DEFAULT NULL,
  `diem_danh_gia` tinyint NOT NULL,
  `tieu_de` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `noi_dung` text COLLATE utf8mb4_unicode_ci,
  `uu_diem` text COLLATE utf8mb4_unicode_ci,
  `nhuoc_diem` text COLLATE utf8mb4_unicode_ci,
  `hinh_anh_danh_gia` json DEFAULT NULL,
  `trang_thai` enum('cho_duyet','da_duyet','tu_choi','an') COLLATE utf8mb4_unicode_ci DEFAULT 'cho_duyet',
  `la_mua_hang_xac_thuc` tinyint(1) DEFAULT '0',
  `luot_thich` int DEFAULT '0',
  `luot_khong_thich` int DEFAULT '0',
  `ly_do_tu_choi` text COLLATE utf8mb4_unicode_ci,
  `nguoi_duyet` int DEFAULT NULL,
  `ngay_duyet` datetime DEFAULT NULL,
  `phan_hoi_admin` text COLLATE utf8mb4_unicode_ci,
  `nguoi_phan_hoi` int DEFAULT NULL,
  `ngay_phan_hoi` datetime DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `san_pham_id` (`san_pham_id`),
  KEY `khach_hang_id` (`khach_hang_id`),
  KEY `don_hang_id` (`don_hang_id`),
  KEY `chi_tiet_don_hang_id` (`chi_tiet_don_hang_id`),
  KEY `nguoi_duyet` (`nguoi_duyet`),
  KEY `nguoi_phan_hoi` (`nguoi_phan_hoi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng thanh_toan_vnpay
CREATE TABLE `thanh_toan_vnpay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `don_hang_id` int NOT NULL,
  `vnp_txn_ref` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vnp_transaction_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_amount` bigint NOT NULL,
  `vnp_order_info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vnp_response_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_transaction_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_pay_date` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_bank_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_card_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_bank_tran_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vnp_secure_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trang_thai` enum('khoi_tao','cho_thanh_toan','thanh_cong','that_bai','huy','het_han') COLLATE utf8mb4_unicode_ci DEFAULT 'khoi_tao',
  `du_lieu_request` json DEFAULT NULL,
  `du_lieu_response` json DEFAULT NULL,
  `du_lieu_ipn` json DEFAULT NULL,
  `ma_qr` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_thanh_toan` text COLLATE utf8mb4_unicode_ci,
  `thoi_gian_het_han_qr` datetime DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_thanh_toan` datetime DEFAULT NULL,
  `ngay_het_han` datetime DEFAULT NULL,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_txn_ref` (`vnp_txn_ref`),
  KEY `don_hang_id` (`don_hang_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng log_hoat_dong_admin
CREATE TABLE `log_hoat_dong_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `hanh_dong` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ban_ghi_id` int DEFAULT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `phuong_thuc_http` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_request` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `du_lieu_cu` json DEFAULT NULL,
  `du_lieu_moi` json DEFAULT NULL,
  `muc_do_nghiem_trong` enum('thap','trung_binh','cao','nghiem_trong') COLLATE utf8mb4_unicode_ci DEFAULT 'trung_binh',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- 2. THÊM FOREIGN KEY CONSTRAINTS
-- =====================================

-- Constraints cho danh_muc_giay
ALTER TABLE `danh_muc_giay`
  ADD CONSTRAINT `danh_muc_giay_ibfk_1` FOREIGN KEY (`danh_muc_cha_id`) REFERENCES `danh_muc_giay` (`id`) ON DELETE SET NULL;

-- Constraints cho san_pham_chinh
ALTER TABLE `san_pham_chinh`
  ADD CONSTRAINT `san_pham_chinh_ibfk_1` FOREIGN KEY (`danh_muc_id`) REFERENCES `danh_muc_giay` (`id`),
  ADD CONSTRAINT `san_pham_chinh_ibfk_2` FOREIGN KEY (`nguoi_tao`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL;

-- Constraints cho bien_the_san_pham
ALTER TABLE `bien_the_san_pham`
  ADD CONSTRAINT `bien_the_san_pham_ibfk_1` FOREIGN KEY (`san_pham_id`) REFERENCES `san_pham_chinh` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bien_the_san_pham_ibfk_2` FOREIGN KEY (`kich_co_id`) REFERENCES `kich_co` (`id`),
  ADD CONSTRAINT `bien_the_san_pham_ibfk_3` FOREIGN KEY (`mau_sac_id`) REFERENCES `mau_sac` (`id`);

-- Constraints cho don_hang
ALTER TABLE `don_hang`
  ADD CONSTRAINT `don_hang_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `don_hang_ibfk_2` FOREIGN KEY (`nguoi_xu_ly`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL;

-- Constraints cho chi_tiet_don_hang
ALTER TABLE `chi_tiet_don_hang`
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_1` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_2` FOREIGN KEY (`san_pham_id`) REFERENCES `san_pham_chinh` (`id`),
  ADD CONSTRAINT `chi_tiet_don_hang_ibfk_3` FOREIGN KEY (`bien_the_id`) REFERENCES `bien_the_san_pham` (`id`);

-- Constraints cho gio_hang
ALTER TABLE `gio_hang`
  ADD CONSTRAINT `gio_hang_ibfk_1` FOREIGN KEY (`khach_hang_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gio_hang_ibfk_2` FOREIGN KEY (`bien_the_id`) REFERENCES `bien_the_san_pham` (`id`) ON DELETE CASCADE;

-- Constraints cho danh_gia_san_pham
ALTER TABLE `danh_gia_san_pham`
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_1` FOREIGN KEY (`san_pham_id`) REFERENCES `san_pham_chinh` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_2` FOREIGN KEY (`khach_hang_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_3` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_4` FOREIGN KEY (`chi_tiet_don_hang_id`) REFERENCES `chi_tiet_don_hang` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_5` FOREIGN KEY (`nguoi_duyet`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `danh_gia_san_pham_ibfk_6` FOREIGN KEY (`nguoi_phan_hoi`) REFERENCES `nguoi_dung` (`id`) ON DELETE SET NULL;

-- Constraints cho thanh_toan_vnpay
ALTER TABLE `thanh_toan_vnpay`
  ADD CONSTRAINT `thanh_toan_vnpay_ibfk_1` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE CASCADE;

-- Constraints cho log_hoat_dong_admin
ALTER TABLE `log_hoat_dong_admin`
  ADD CONSTRAINT `log_hoat_dong_admin_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE;

-- =====================================
-- 3. CHÈN DỮ LIỆU MẪU (KHÔNG MÃ HÓA PASSWORD)
-- =====================================

-- Dữ liệu cho nguoi_dung (mật khẩu: admin123)
INSERT INTO `nguoi_dung` (`id`, `ten_dang_nhap`, `mat_khau`, `ho_ten`, `email`, `so_dien_thoai`, `dia_chi`, `ngay_sinh`, `gioi_tinh`, `vai_tro`, `trang_thai`) VALUES
(1, 'admin', 'admin123', 'TKT Shop Administrator', 'admin@tktshop.com', NULL, NULL, NULL, 'khac', 'admin', 'hoat_dong'),
(2, 'khachhang1', '123456', 'Nguyen Van A', 'customer1@gmail.com', '0987654321', NULL, NULL, 'nam', 'khach_hang', 'hoat_dong'),
(3, 'khachhang2', '123456', 'Tran Thi B', 'customer2@gmail.com', '0123456789', NULL, NULL, 'nu', 'khach_hang', 'hoat_dong'),
(4, 'nhanvien1', '123456', 'Le Van C', 'staff1@tktshop.com', '0111222333', NULL, NULL, 'nam', 'nhan_vien', 'hoat_dong');

-- Dữ liệu cho danh_muc_giay
INSERT INTO `danh_muc_giay` (`id`, `ten_danh_muc`, `slug`, `mo_ta`, `hinh_anh`, `danh_muc_cha_id`, `thu_tu_hien_thi`, `trang_thai`) VALUES
(1, 'Giày thể thao', 'giay-the-thao', 'Giày thể thao nam nữ', NULL, NULL, 1, 'hoat_dong'),
(2, 'Giày cao gót', 'giay-cao-got', 'Giày cao gót nữ', NULL, NULL, 2, 'hoat_dong'),
(3, 'Giày boot', 'giay-boot', 'Giày boot thời trang', NULL, NULL, 3, 'hoat_dong'),
(4, 'Giày sandal', 'giay-sandal', 'Giày sandal hè', NULL, NULL, 4, 'hoat_dong'),
(5, 'Giày thể thao nam', 'giay-the-thao-nam', 'Giày thể thao dành cho nam', NULL, 1, 1, 'hoat_dong'),
(6, 'Giày thể thao nữ', 'giay-the-thao-nu', 'Giày thể thao dành cho nữ', NULL, 1, 2, 'hoat_dong'),
(7, 'Giày chạy bộ', 'giay-chay-bo', 'Giày chuyên dụng chạy bộ', NULL, 1, 3, 'hoat_dong');

-- Dữ liệu cho kich_co
INSERT INTO `kich_co` (`id`, `kich_co`, `mo_ta`, `thu_tu_sap_xep`, `trang_thai`) VALUES
(1, '35', 'Size 35', 35, 'hoat_dong'),
(2, '36', 'Size 36', 36, 'hoat_dong'),
(3, '37', 'Size 37', 37, 'hoat_dong'),
(4, '38', 'Size 38', 38, 'hoat_dong'),
(5, '39', 'Size 39', 39, 'hoat_dong'),
(6, '40', 'Size 40', 40, 'hoat_dong'),
(7, '41', 'Size 41', 41, 'hoat_dong'),
(8, '42', 'Size 42', 42, 'hoat_dong'),
(9, '43', 'Size 43', 43, 'hoat_dong'),
(10, '44', 'Size 44', 44, 'hoat_dong'),
(11, '45', 'Size 45', 45, 'hoat_dong');

-- Dữ liệu cho mau_sac
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
(10, 'Tím', '#800080', NULL, 10, 'hoat_dong');

-- Dữ liệu cho san_pham_chinh
INSERT INTO `san_pham_chinh` (`id`, `ten_san_pham`, `slug`, `ma_san_pham`, `mo_ta_ngan`, `mo_ta_chi_tiet`, `danh_muc_id`, `thuong_hieu`, `hinh_anh_chinh`, `album_hinh_anh`, `gia_goc`, `gia_khuyen_mai`, `ngay_bat_dau_km`, `ngay_ket_thuc_km`, `san_pham_noi_bat`, `san_pham_moi`, `san_pham_ban_chay`, `luot_xem`, `so_luong_ban`, `diem_danh_gia_tb`, `so_luong_danh_gia`, `meta_title`, `meta_description`, `tags`, `trang_thai`, `nguoi_tao`) VALUES
(1, 'Nike Air Max 270', 'nike-air-max-270', 'NIKE001', 'Giày thể thao Nike Air Max 270', 'Giày thể thao Nike Air Max 270 với công nghệ đệm khí Max Air mang lại sự thoải mái tối đa cho đôi chân. Thiết kế hiện đại, phù hợp cho cả nam và nữ.', 1, 'Nike', NULL, NULL, 3500000, 2800000, NULL, NULL, 1, 1, 0, 0, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1),
(2, 'Adidas Ultraboost 22', 'adidas-ultraboost-22', 'ADIDAS001', 'Giày chạy bộ Adidas Ultraboost', 'Adidas Ultraboost 22 với công nghệ Boost mang lại năng lượng phản hồi tuyệt vời. Đế giày có độ bám cao, phù hợp cho việc chạy bộ và tập luyện.', 5, 'Adidas', NULL, NULL, 4200000, 3600000, NULL, NULL, 1, 1, 0, 0, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1),
(3, 'Converse Chuck Taylor All Star', 'converse-chuck-taylor', 'CONVERSE001', 'Giày cổ điển Converse Chuck Taylor', 'Converse Chuck Taylor All Star - biểu tượng thời trang đường phố với thiết kế cổ điển, không bao giờ lỗi thời. Chất liệu canvas bền bỉ.', 1, 'Converse', NULL, NULL, 1800000, 1500000, NULL, NULL, 0, 0, 0, 0, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1),
(4, 'Vans Old Skool', 'vans-old-skool', 'VANS001', 'Giày skateboard Vans Old Skool', 'Vans Old Skool với thiết kế side stripe đặc trưng. Giày skateboard chính hãng với đế cao su bền chắc, grip tốt.', 1, 'Vans', NULL, NULL, 2200000, NULL, NULL, NULL, 1, 0, 0, 0, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1),
(5, 'Puma RS-X', 'puma-rs-x', 'PUMA001', 'Giày thể thao Puma RS-X', 'Puma RS-X với thiết kế chunky sneaker đầy cá tính. Phối màu bắt mắt, phù hợp với phong cách streetwear hiện đại.', 1, 'Puma', NULL, NULL, 2800000, 2300000, NULL, NULL, 0, 1, 0, 0, 0, 0.00, 0, NULL, NULL, NULL, 'hoat_dong', 1);

-- Dữ liệu cho bien_the_san_pham
INSERT INTO `bien_the_san_pham` (`id`, `san_pham_id`, `kich_co_id`, `mau_sac_id`, `ma_sku`, `gia_ban`, `gia_so_sanh`, `so_luong_ton_kho`, `so_luong_da_ban`, `nguong_canh_bao_het_hang`, `hinh_anh_bien_the`, `trang_thai`) VALUES
(1, 1, 6, 1, 'NIKE001-40-BLACK', 2800000, NULL, 50, 0, 5, NULL, 'hoat_dong'),
(2, 1, 6, 2, 'NIKE001-40-WHITE', 2800000, NULL, 30, 0, 5, NULL, 'hoat_dong'),
(3, 1, 7, 1, 'NIKE001-41-BLACK', 2800000, NULL, 25, 0, 5, NULL, 'hoat_dong'),
(4, 1, 7, 2, 'NIKE001-41-WHITE', 2800000, NULL, 20, 0, 5, NULL, 'hoat_dong'),
(5, 1, 8, 1, 'NIKE001-42-BLACK', 2800000, NULL, 15, 0, 5, NULL, 'hoat_dong'),
(6, 2, 6, 1, 'ADIDAS001-40-BLACK', 3600000, NULL, 20, 0, 5, NULL, 'hoat_dong'),
(7, 2, 6, 4, 'ADIDAS001-40-BLUE', 3600000, NULL, 15, 0, 5, NULL, 'hoat_dong'),
(8, 2, 7, 1, 'ADIDAS001-41-BLACK', 3600000, NULL, 18, 0, 5, NULL, 'hoat_dong'),
(9, 2, 7, 4, 'ADIDAS001-41-BLUE', 3600000, NULL, 12, 0, 5, NULL, 'hoat_dong'),
(10, 3, 5, 1, 'CONVERSE001-39-BLACK', 1500000, NULL, 30, 0, 5, NULL, 'hoat_dong'),
(11, 3, 5, 2, 'CONVERSE001-39-WHITE', 1500000, NULL, 25, 0, 5, NULL, 'hoat_dong'),
(12, 3, 5, 3, 'CONVERSE001-39-RED', 1500000, NULL, 20, 0, 5, NULL, 'hoat_dong'),
(13, 3, 6, 1, 'CONVERSE001-40-BLACK', 1500000, NULL, 35, 0, 5, NULL, 'hoat_dong'),
(14, 3, 6, 2, 'CONVERSE001-40-WHITE', 1500000, NULL, 28, 0, 5, NULL, 'hoat_dong'),
(15, 4, 6, 1, 'VANS001-40-BLACK', 2200000, NULL, 22, 0, 5, NULL, 'hoat_dong'),
(16, 4, 6, 2, 'VANS001-40-WHITE', 2200000, NULL, 18, 0, 5, NULL, 'hoat_dong'),
(17, 4, 7, 1, 'VANS001-41-BLACK', 2200000, NULL, 20, 0, 5, NULL, 'hoat_dong'),
(18, 5, 6, 6, 'PUMA001-40-GRAY', 2300000, NULL, 15, 0, 5, NULL, 'hoat_dong'),
(19, 5, 6, 3, 'PUMA001-40-RED', 2300000, NULL, 12, 0, 5, NULL, 'hoat_dong'),
(20, 5, 7, 6, 'PUMA001-41-GRAY', 2300000, NULL, 18, 0, 5, NULL, 'hoat_dong');

-- =====================================
-- 4. AUTO INCREMENT VALUES
-- =====================================

ALTER TABLE `bien_the_san_pham` AUTO_INCREMENT = 21;
ALTER TABLE `chi_tiet_don_hang` AUTO_INCREMENT = 1;
ALTER TABLE `danh_gia_san_pham` AUTO_INCREMENT = 1;
ALTER TABLE `danh_muc_giay` AUTO_INCREMENT = 8;
ALTER TABLE `don_hang` AUTO_INCREMENT = 1;
ALTER TABLE `gio_hang` AUTO_INCREMENT = 7;
ALTER TABLE `kich_co` AUTO_INCREMENT = 12;
ALTER TABLE `log_hoat_dong_admin` AUTO_INCREMENT = 1;
ALTER TABLE `mau_sac` AUTO_INCREMENT = 11;
ALTER TABLE `nguoi_dung` AUTO_INCREMENT = 5;
ALTER TABLE `san_pham_chinh` AUTO_INCREMENT = 6;
ALTER TABLE `thanh_toan_vnpay` AUTO_INCREMENT = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;