-- Cập nhật database cho VNPay
-- Thêm cột ngay_thanh_toan vào bảng don_hang

ALTER TABLE `don_hang` 
ADD COLUMN `ngay_thanh_toan` datetime DEFAULT NULL AFTER `han_thanh_toan`;

-- Cập nhật view để bao gồm cột ngay_thanh_toan
DROP VIEW IF EXISTS `view_don_hang_tong_quan`;

CREATE VIEW `view_don_hang_tong_quan` AS 
SELECT 
    dh.id,
    dh.ma_don_hang,
    dh.khach_hang_id,
    dh.ho_ten_nhan,
    dh.so_dien_thoai_nhan,
    dh.email_nhan,
    dh.dia_chi_nhan,
    dh.ghi_chu_khach_hang,
    dh.ghi_chu_admin,
    dh.tong_tien_hang,
    dh.tien_giam_gia,
    dh.phi_van_chuyen,
    dh.tong_thanh_toan,
    dh.phuong_thuc_thanh_toan,
    dh.phuong_thuc_van_chuyen,
    dh.trang_thai_don_hang,
    dh.trang_thai_thanh_toan,
    dh.ngay_dat_hang,
    dh.ngay_xac_nhan,
    dh.ngay_giao_hang,
    dh.ngay_hoan_thanh,
    dh.ngay_huy,
    dh.han_thanh_toan,
    dh.ngay_thanh_toan,
    dh.ly_do_huy,
    dh.nguoi_xu_ly,
    dh.ma_van_don,
    dh.ngay_cap_nhat,
    nd.ho_ten as ten_khach_hang,
    nd.email as email_khach_hang,
    COUNT(DISTINCT ct.id) as so_san_pham,
    SUM(ct.so_luong) as tong_so_luong,
    vnp.vnp_transaction_no,
    vnp.trang_thai as trang_thai_vnpay
FROM don_hang dh
LEFT JOIN nguoi_dung nd ON dh.khach_hang_id = nd.id
LEFT JOIN chi_tiet_don_hang ct ON dh.id = ct.don_hang_id
LEFT JOIN thanh_toan_vnpay vnp ON dh.id = vnp.don_hang_id
GROUP BY dh.id; 