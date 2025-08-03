# Hướng dẫn tích hợp VNPay vào hệ thống TKTShop

## 🔧 Cấu hình VNPay

### 1. Thông tin cấu hình hiện tại (Sandbox)
```php
// vnpay/config.php
$vnp_TmnCode = "RYNQXLGK"; // Terminal ID từ VNPay cung cấp
$vnp_HashSecret = "YCYJDMIDW0V2NA5OCER3OIHD36VS67NU"; // Secret Key từ VNPay
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html"; // URL thanh toán sandbox
$vnp_Returnurl = "http://localhost/tktshop/vnpay/return.php"; // URL return sau thanh toán
$vnp_apiUrl = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction"; // API URL
```

### 2. Cấu hình Production (khi deploy)
```php
// Uncomment các dòng này và comment các dòng sandbox
$vnp_Url = "https://vnpayment.vn/paymentv2/vpcpay.html";
$vnp_apiUrl = "https://vnpayment.vn/merchant_webapi/api/transaction";
```

## 📁 Cấu trúc thư mục VNPay

```
vnpay/
├── config.php          # Cấu hình VNPay
├── create_payment.php  # Tạo thanh toán
├── return.php          # Xử lý kết quả thanh toán
├── check_status.php    # Kiểm tra trạng thái giao dịch
└── logs/               # Thư mục log (tự động tạo)
```

## 🗄️ Cấu trúc Database

### Bảng `thanh_toan_vnpay`
```sql
CREATE TABLE `thanh_toan_vnpay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `don_hang_id` int NOT NULL,
  `vnp_txn_ref` varchar(100) NOT NULL,
  `vnp_transaction_no` varchar(100) DEFAULT NULL,
  `vnp_amount` bigint NOT NULL,
  `vnp_order_info` varchar(255) NOT NULL,
  `vnp_response_code` varchar(10) DEFAULT NULL,
  `vnp_transaction_status` varchar(10) DEFAULT NULL,
  `vnp_pay_date` varchar(20) DEFAULT NULL,
  `vnp_bank_code` varchar(20) DEFAULT NULL,
  `vnp_card_type` varchar(20) DEFAULT NULL,
  `vnp_bank_tran_no` varchar(100) DEFAULT NULL,
  `vnp_secure_hash` varchar(255) DEFAULT NULL,
  `trang_thai` enum('khoi_tao','cho_thanh_toan','thanh_cong','that_bai','huy','het_han') DEFAULT 'khoi_tao',
  `du_lieu_request` json DEFAULT NULL,
  `du_lieu_response` json DEFAULT NULL,
  `du_lieu_ipn` json DEFAULT NULL,
  `ma_qr` varchar(500) DEFAULT NULL,
  `url_thanh_toan` text,
  `thoi_gian_het_han_qr` datetime DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_thanh_toan` datetime DEFAULT NULL,
  `ngay_het_han` datetime DEFAULT NULL,
  `ngay_cap_nhat` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `don_hang_id` (`don_hang_id`),
  KEY `vnp_txn_ref` (`vnp_txn_ref`),
  CONSTRAINT `thanh_toan_vnpay_ibfk_1` FOREIGN KEY (`don_hang_id`) REFERENCES `don_hang` (`id`) ON DELETE CASCADE
);
```

### Cột mới trong bảng `don_hang`
```sql
ALTER TABLE `don_hang` 
ADD COLUMN `ngay_thanh_toan` datetime DEFAULT NULL AFTER `han_thanh_toan`;
```

## 🔄 Luồng thanh toán VNPay

### 1. Tạo đơn hàng (checkout.php)
- Khách hàng chọn phương thức thanh toán VNPay
- Lưu thông tin đơn hàng vào session
- Redirect đến `vnpay/create_payment.php`

### 2. Tạo thanh toán (create_payment.php)
- Lấy thông tin từ session
- Tạo giao dịch trong bảng `thanh_toan_vnpay`
- Tạo URL thanh toán VNPay
- Redirect khách hàng đến VNPay

### 3. Xử lý kết quả (return.php)
- VNPay redirect về với kết quả thanh toán
- Verify chữ ký bảo mật
- Cập nhật trạng thái giao dịch và đơn hàng
- Hiển thị kết quả cho khách hàng

### 4. Kiểm tra trạng thái (check_status.php)
- API để kiểm tra trạng thái giao dịch
- Query từ VNPay API
- Cập nhật database nếu cần

## 🛠️ Các hàm hỗ trợ

### Tạo secure hash
```php
function createVNPaySecureHash($inputData, $hashSecret)
```

### Verify secure hash
```php
function verifyVNPaySecureHash($inputData, $hashSecret, $vnpSecureHash)
```

### Format số tiền
```php
function formatVNPayAmount($amount) // Nhân 100
function parseVNPayAmount($amount)  // Chia 100
```

### Log giao dịch
```php
function logVNPayTransaction($type, $data, $response = null)
```

### Tạo mã giao dịch
```php
function generateVNPayOrderCode($orderId = null)
```

## 📊 Trạng thái giao dịch

### Trạng thái trong database
- `khoi_tao`: Giao dịch mới tạo
- `cho_thanh_toan`: Đang chờ thanh toán
- `thanh_cong`: Thanh toán thành công
- `that_bai`: Thanh toán thất bại
- `huy`: Giao dịch bị hủy
- `het_han`: Giao dịch hết hạn

### Mã phản hồi VNPay
- `00`: Giao dịch thành công
- `07`: Giao dịch bị nghi ngờ
- `09`: Thẻ chưa đăng ký Internet Banking
- `10`: Xác thực thông tin sai quá 3 lần
- `11`: Hết hạn chờ thanh toán
- `12`: Thẻ bị khóa
- `13`: Nhập sai OTP
- `24`: Khách hàng hủy giao dịch
- `51`: Tài khoản không đủ số dư
- `65`: Vượt quá hạn mức giao dịch
- `75`: Ngân hàng đang bảo trì
- `79`: Nhập sai mật khẩu quá số lần quy định
- `99`: Lỗi khác

## 🔒 Bảo mật

### Kiểm tra IP VNPay
```php
function isVNPayIP($ip)
```

### IP VNPay hợp lệ
- `103.220.87.4`
- `103.220.87.5`
- `203.171.19.146`
- `203.171.19.147`

## 📝 Log và Debug

### Thư mục log
```
vnpay/logs/vnpay_YYYY-MM-DD.log
```

### Format log
```json
{
  "timestamp": "2025-07-31 12:30:00",
  "type": "create_payment",
  "ip": "192.168.1.1",
  "data": {...},
  "response": {...}
}
```

## 🚀 Triển khai

### 1. Cập nhật database
```sql
-- Chạy file update_database.sql
source update_database.sql;
```

### 2. Tạo thư mục logs
```bash
mkdir -p vnpay/logs
chmod 755 vnpay/logs
```

### 3. Cập nhật cấu hình
- Thay đổi URL return cho production
- Cập nhật Terminal ID và Secret Key thật
- Chuyển sang production URL

### 4. Test thanh toán
- Sử dụng thẻ test VNPay
- Kiểm tra log giao dịch
- Verify kết quả thanh toán

## ⚠️ Lưu ý quan trọng

1. **Bảo mật**: Không commit Secret Key lên git
2. **URL Return**: Phải là URL public có thể truy cập từ internet
3. **Timeout**: Giao dịch VNPay có thời hạn 15 phút
4. **Duplicate**: Xử lý tránh duplicate payment
5. **Log**: Luôn log để debug và audit
6. **Error Handling**: Xử lý lỗi gracefully

## 🆘 Troubleshooting

### Lỗi thường gặp
1. **Chữ ký không hợp lệ**: Kiểm tra HashSecret
2. **URL return không đúng**: Cập nhật URL trong config
3. **Database error**: Kiểm tra cấu trúc bảng
4. **Session lost**: Kiểm tra session configuration
5. **Timeout**: Tăng timeout cho API calls

### Debug
1. Kiểm tra log file
2. Verify database records
3. Test với sandbox trước
4. Kiểm tra network connectivity 